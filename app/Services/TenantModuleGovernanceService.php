<?php

namespace App\Services;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\TenantModuleActivationRequest;
use App\Models\TenantModuleEntitlement;
use App\Models\TenantModuleGovernanceAudit;
use App\Models\User;
use App\Models\UserActiveModule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class TenantModuleGovernanceService
{
    public function settingsPayload(User $actor, ?int $selectedTenantId = null): array
    {
        $targetTenant = $this->resolveTargetTenant($actor, $selectedTenantId);

        if (! $targetTenant) {
            return [
                'selectedTenantId' => null,
                'companies' => [],
                'modules' => [],
                'pendingRequests' => [],
                'recentAudits' => [],
            ];
        }

        $catalog = AddOn::query()
            ->where('is_enable', true)
            ->orderBy('priority')
            ->orderBy('name')
            ->get(['module', 'name']);

        $entitlements = TenantModuleEntitlement::query()
            ->where('tenant_id', $targetTenant->id)
            ->get()
            ->keyBy('module_key');

        $activeModules = UserActiveModule::query()
            ->where('user_id', $targetTenant->id)
            ->pluck('module')
            ->toArray();

        $baseEntitledModules = $this->baseEntitledModules($targetTenant);

        $modules = $catalog->map(function (AddOn $addon) use ($entitlements, $activeModules, $baseEntitledModules) {
            $record = $entitlements->get($addon->module);

            $isEntitled = $record
                ? (bool) $record->is_entitled
                : in_array($addon->module, $baseEntitledModules, true);

            return [
                'module' => $addon->module,
                'name' => $addon->name,
                'is_entitled' => $isEntitled,
                'requires_approval' => $record ? (bool) $record->requires_approval : false,
                'is_active' => in_array($addon->module, $activeModules, true),
            ];
        })->values()->toArray();

        $requestQuery = TenantModuleActivationRequest::query()->with([])->latest('id');

        if ($actor->type === 'superadmin') {
            $requestQuery->where('status', 'pending');
        } else {
            $requestQuery->where('tenant_id', $targetTenant->id);
        }

        $pendingRequests = $requestQuery
            ->limit(20)
            ->get(['id', 'tenant_id', 'module_key', 'status', 'request_note', 'review_note', 'requested_at', 'reviewed_at'])
            ->map(function (TenantModuleActivationRequest $request) {
                return [
                    'id' => $request->id,
                    'tenant_id' => $request->tenant_id,
                    'module_key' => $request->module_key,
                    'status' => $request->status,
                    'request_note' => $request->request_note,
                    'review_note' => $request->review_note,
                    'requested_at' => $request->requested_at,
                    'reviewed_at' => $request->reviewed_at,
                ];
            })
            ->toArray();

        $recentAudits = TenantModuleGovernanceAudit::query()
            ->where('tenant_id', $targetTenant->id)
            ->latest('id')
            ->limit(20)
            ->get(['id', 'action', 'module_key', 'change_reason', 'changed_at'])
            ->map(fn (TenantModuleGovernanceAudit $audit) => [
                'id' => $audit->id,
                'action' => $audit->action,
                'module_key' => $audit->module_key,
                'change_reason' => $audit->change_reason,
                'changed_at' => $audit->changed_at,
            ])
            ->toArray();

        $companies = [];
        if ($actor->type === 'superadmin') {
            $companies = User::query()->where('type', 'company')->orderBy('name')->get(['id', 'name', 'email'])->toArray();
        }

        return [
            'selectedTenantId' => $targetTenant->id,
            'companies' => $companies,
            'modules' => $modules,
            'pendingRequests' => $pendingRequests,
            'recentAudits' => $recentAudits,
        ];
    }

    public function updateEntitlement(User $actor, int $tenantId, string $moduleKey, bool $isEntitled, bool $requiresApproval): void
    {
        if ($actor->type !== 'superadmin') {
            throw new RuntimeException('Only superadmin can change module entitlements.');
        }

        DB::transaction(function () use ($actor, $tenantId, $moduleKey, $isEntitled, $requiresApproval) {
            $record = TenantModuleEntitlement::query()->firstOrNew([
                'tenant_id' => $tenantId,
                'module_key' => $moduleKey,
            ]);

            $old = $record->exists ? $record->toArray() : null;

            $record->is_entitled = $isEntitled;
            $record->requires_approval = $requiresApproval;
            $record->set_by = $actor->id;
            $record->set_at = Carbon::now();
            $record->save();

            if (! $isEntitled) {
                $this->deactivateModuleInternal($tenantId, $moduleKey, $actor->id, 'entitlement_revoked');
            }

            $this->audit($tenantId, 'entitlement_updated', $moduleKey, $old, $record->toArray(), $actor->id, null);
        });
    }

    public function activateModule(User $actor, string $moduleKey, ?string $requestNote = null): string
    {
        $tenant = $this->resolveCompanyContext($actor);
        if (! $tenant) {
            throw new RuntimeException('Tenant context is missing.');
        }

        $state = $this->resolveModuleState($tenant, $moduleKey);

        if (! $state['is_entitled']) {
            throw new RuntimeException('Module is not entitled for this company.');
        }

        if ($state['is_active']) {
            return 'already_active';
        }

        if ($state['requires_approval']) {
            $pending = TenantModuleActivationRequest::query()
                ->where('tenant_id', $tenant->id)
                ->where('module_key', $moduleKey)
                ->where('status', 'pending')
                ->exists();

            if ($pending) {
                return 'request_pending';
            }

            TenantModuleActivationRequest::query()->create([
                'tenant_id' => $tenant->id,
                'module_key' => $moduleKey,
                'status' => 'pending',
                'request_note' => $requestNote,
                'requested_by' => $actor->id,
                'requested_at' => Carbon::now(),
            ]);

            $this->audit($tenant->id, 'activation_requested', $moduleKey, null, ['status' => 'pending'], $actor->id, $requestNote);

            return 'requested';
        }

        UserActiveModule::query()->firstOrCreate([
            'user_id' => $tenant->id,
            'module' => $moduleKey,
        ]);

        $this->audit($tenant->id, 'activated', $moduleKey, ['active' => false], ['active' => true], $actor->id, null);

        return 'activated';
    }

    public function deactivateModule(User $actor, string $moduleKey, ?string $reason = null): void
    {
        $tenant = $this->resolveCompanyContext($actor);
        if (! $tenant) {
            throw new RuntimeException('Tenant context is missing.');
        }

        $this->deactivateModuleInternal($tenant->id, $moduleKey, $actor->id, $reason);
    }

    public function reviewRequest(User $actor, int $requestId, string $decision, ?string $note = null): void
    {
        if ($actor->type !== 'superadmin') {
            throw new RuntimeException('Only superadmin can review module activation requests.');
        }

        $request = TenantModuleActivationRequest::query()->findOrFail($requestId);

        if ($request->status !== 'pending') {
            throw new RuntimeException('Only pending requests can be reviewed.');
        }

        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new RuntimeException('Invalid request decision.');
        }

        if ($decision === 'approved') {
            $tenant = User::query()->find($request->tenant_id);
            if (! $tenant) {
                throw new RuntimeException('Target company not found for approval.');
            }

            $state = $this->resolveModuleState($tenant, $request->module_key);
            if (! $state['is_entitled']) {
                throw new RuntimeException('Cannot approve request for non-entitled module.');
            }

            UserActiveModule::query()->firstOrCreate([
                'user_id' => $tenant->id,
                'module' => $request->module_key,
            ]);

            $this->audit($tenant->id, 'request_approved_and_activated', $request->module_key, null, ['active' => true], $actor->id, $note);
        } else {
            $this->audit($request->tenant_id, 'request_rejected', $request->module_key, null, ['status' => 'rejected'], $actor->id, $note);
        }

        $request->status = $decision;
        $request->review_note = $note;
        $request->reviewed_by = $actor->id;
        $request->reviewed_at = Carbon::now();
        $request->save();
    }

    private function resolveTargetTenant(User $actor, ?int $selectedTenantId): ?User
    {
        if ($actor->type === 'superadmin') {
            if ($selectedTenantId) {
                return User::query()->where('type', 'company')->where('id', $selectedTenantId)->first();
            }

            return User::query()->where('type', 'company')->orderBy('name')->first();
        }

        return $this->resolveCompanyContext($actor);
    }

    private function resolveCompanyContext(User $actor): ?User
    {
        if ($actor->type === 'company') {
            return $actor;
        }

        if (!empty($actor->created_by)) {
            return User::query()->where('type', 'company')->find($actor->created_by);
        }

        return null;
    }

    private function resolveModuleState(User $tenant, string $moduleKey): array
    {
        $baseEntitled = in_array($moduleKey, $this->baseEntitledModules($tenant), true);

        $record = TenantModuleEntitlement::query()->where('tenant_id', $tenant->id)->where('module_key', $moduleKey)->first();
        $isEntitled = $record ? (bool) $record->is_entitled : $baseEntitled;
        $requiresApproval = $record ? (bool) $record->requires_approval : false;
        $isActive = UserActiveModule::query()->where('user_id', $tenant->id)->where('module', $moduleKey)->exists();

        return [
            'is_entitled' => $isEntitled,
            'requires_approval' => $requiresApproval,
            'is_active' => $isActive,
        ];
    }

    private function baseEntitledModules(User $tenant): array
    {
        if (!$tenant->active_plan) {
            return [];
        }

        $plan = Plan::query()->find($tenant->active_plan);
        if (!$plan || !is_array($plan->modules)) {
            return [];
        }

        return array_values(array_unique($plan->modules));
    }

    private function deactivateModuleInternal(int $tenantId, string $moduleKey, ?int $actorId, ?string $reason): void
    {
        $active = UserActiveModule::query()->where('user_id', $tenantId)->where('module', $moduleKey)->exists();
        if (! $active) {
            return;
        }

        $blockers = $this->deactivationBlockers($tenantId, $moduleKey);
        if ($blockers !== []) {
            throw new RuntimeException('Module cannot be deactivated: ' . implode(' ', $blockers));
        }

        UserActiveModule::query()->where('user_id', $tenantId)->where('module', $moduleKey)->delete();

        $this->audit($tenantId, 'deactivated', $moduleKey, ['active' => true], ['active' => false], $actorId, $reason);
    }

    private function deactivationBlockers(int $tenantId, string $moduleKey): array
    {
        $blockers = [];

        if (stripos($moduleKey, 'TextileSales') !== false && Schema::hasTable('textile_workflow_documents')) {
            $count = DB::table('textile_workflow_documents')
                ->where('created_by', $tenantId)
                ->whereIn('document_type', ['sales_order', 'allocation', 'dispatch', 'challan', 'pod'])
                ->count();
            if ($count > 0) {
                $blockers[] = 'Sales workflow documents exist.';
            }
        }

        if (stripos($moduleKey, 'TextileProcurement') !== false && Schema::hasTable('textile_workflow_documents')) {
            $count = DB::table('textile_workflow_documents')
                ->where('created_by', $tenantId)
                ->whereIn('document_type', ['requisition', 'purchase_order', 'grn', 'incoming_qc'])
                ->count();
            if ($count > 0) {
                $blockers[] = 'Procurement workflow documents exist.';
            }
        }

        if (stripos($moduleKey, 'TextileManufacturing') !== false && Schema::hasTable('textile_workflow_documents')) {
            $count = DB::table('textile_workflow_documents')
                ->where('created_by', $tenantId)
                ->whereIn('document_type', ['beam', 'production_batch', 'weaving_output', 'waste', 'rework', 'warp_plan', 'yarn_allocation', 'warp_sheet', 'warp_production', 'sizing_recipe'])
                ->count();
            if ($count > 0) {
                $blockers[] = 'Manufacturing workflow documents exist.';
            }
        }

        if (stripos($moduleKey, 'TextileProcessing') !== false && Schema::hasTable('textile_workflow_documents')) {
            $count = DB::table('textile_workflow_documents')
                ->where('created_by', $tenantId)
                ->whereIn('document_type', ['job_work_outward', 'processing_batch', 'job_work_inward'])
                ->count();
            if ($count > 0) {
                $blockers[] = 'Processing workflow documents exist.';
            }
        }

        if (stripos($moduleKey, 'Account') !== false && Schema::hasTable('customers')) {
            $count = DB::table('customers')->where('created_by', $tenantId)->count();
            if ($count > 0) {
                $blockers[] = 'Account master records exist.';
            }
        }

        return $blockers;
    }

    private function audit(int $tenantId, string $action, string $moduleKey, ?array $oldPayload, ?array $newPayload, ?int $changedBy, ?string $reason): void
    {
        TenantModuleGovernanceAudit::query()->create([
            'tenant_id' => $tenantId,
            'action' => $action,
            'module_key' => $moduleKey,
            'old_payload' => $oldPayload,
            'new_payload' => $newPayload,
            'changed_by' => $changedBy,
            'change_reason' => $reason,
            'changed_at' => Carbon::now(),
        ]);
    }
}
