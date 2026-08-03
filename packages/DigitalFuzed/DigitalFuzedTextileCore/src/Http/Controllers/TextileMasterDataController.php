<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileQualityProfile;
use DigitalFuzed\TextileCore\Models\TextileReferenceMaster;
use DigitalFuzed\TextileCore\Models\TextileRouteRecipe;
use DigitalFuzed\TextileCore\Models\TextileUnitConversion;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class TextileMasterDataController extends Controller
{
    private const SOURCE_TYPE_MASTER = 'source_type';
    private const SOURCE_ACTION_MASTER = 'source_action';
    private const MACHINE_TYPE_MASTER = 'machine_type';
    private const COST_TYPE_MASTER = 'cost_type';
    private const INSPECTION_RESULT_MASTER = 'inspection_result';
    private const SHED_TYPE_MASTER = 'shed_type';
    private const LOOM_STATUS_MASTER = 'loom_status';
    private const BREAKDOWN_REASON_MASTER = 'breakdown_reason';
    private const MAINTENANCE_TYPE_MASTER = 'maintenance_type';
    private const DEFAULT_MASTER_DOMAIN = 'global';
    private const REFERENCE_DOMAINS = [
        'global',
        'manufacturing',
        'inventory',
        'procurement',
        'sales',
        'processing',
        'quality',
    ];

    public function qualityProfiles()
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/Masters/Index', [
            'master' => 'quality-profiles',
            'records' => TextileQualityProfile::where('created_by', creatorId())->where('is_active', true)->latest()->get(),
        ]);
    }

    public function storeQualityProfile(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'grade' => 'nullable|string|max:100',
            'parameters' => 'nullable|string|max:5000',
        ]);

        TextileQualityProfile::create([...$validated, 'is_active' => true, 'created_by' => creatorId(), 'creator_id' => Auth::id()]);

        return back()->with('success', __('Textile quality profile created successfully.'));
    }

    public function updateQualityProfile(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'record_id' => 'required|integer|min:1',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'grade' => 'nullable|string|max:100',
            'parameters' => 'nullable|string|max:5000',
        ]);

        $record = TextileQualityProfile::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['record_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->update([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'grade' => $validated['grade'] ?? null,
            'parameters' => $validated['parameters'] ?? null,
        ]);

        return back()->with('success', __('Textile quality profile updated successfully.'));
    }

    public function archiveQualityProfile(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'record_id' => 'required|integer|min:1',
        ]);

        $record = TextileQualityProfile::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['record_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->is_active = false;
        $record->save();

        return back()->with('success', __('Textile quality profile deactivated successfully.'));
    }

    public function routeRecipes()
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/Masters/Index', [
            'master' => 'route-recipes',
            'records' => TextileRouteRecipe::where('created_by', creatorId())->where('is_active', true)->latest()->get(),
        ]);
    }

    public function storeRouteRecipe(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'steps' => 'nullable|string|max:5000',
        ]);
        $steps = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $validated['steps'] ?? ''))));

        TextileRouteRecipe::create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'steps' => $steps,
            'is_active' => true,
            'created_by' => creatorId(),
            'creator_id' => Auth::id(),
        ]);

        return back()->with('success', __('Textile route recipe created successfully.'));
    }

    public function updateRouteRecipe(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'record_id' => 'required|integer|min:1',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'steps' => 'nullable|string|max:5000',
        ]);

        $record = TextileRouteRecipe::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['record_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $steps = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $validated['steps'] ?? ''))));

        $record->update([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'steps' => $steps,
        ]);

        return back()->with('success', __('Textile route recipe updated successfully.'));
    }

    public function archiveRouteRecipe(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'record_id' => 'required|integer|min:1',
        ]);

        $record = TextileRouteRecipe::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['record_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->is_active = false;
        $record->save();

        return back()->with('success', __('Textile route recipe deactivated successfully.'));
    }

    public function unitConversions()
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/Masters/Index', [
            'master' => 'unit-conversions',
            'records' => TextileUnitConversion::where('created_by', creatorId())->where('is_active', true)->latest()->get(),
        ]);
    }

    public function sourceTypes()
    {
        return $this->renderReferenceMasterIndex('source-types', self::SOURCE_TYPE_MASTER, self::DEFAULT_MASTER_DOMAIN);
    }

    public function sourceTypesByDomain(string $domain)
    {
        return $this->renderReferenceMasterIndex('source-types', self::SOURCE_TYPE_MASTER, $this->resolveDomain($domain));
    }

    public function storeSourceType(Request $request)
    {
        return $this->storeReferenceMaster($request, self::SOURCE_TYPE_MASTER, 'Textile source type created successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function storeSourceTypeByDomain(Request $request, string $domain)
    {
        return $this->storeReferenceMaster($request, self::SOURCE_TYPE_MASTER, 'Textile source type created successfully.', $this->resolveDomain($domain));
    }

    public function updateSourceType(Request $request)
    {
        return $this->updateReferenceMaster($request, self::SOURCE_TYPE_MASTER, 'Textile source type updated successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function updateSourceTypeByDomain(Request $request, string $domain)
    {
        return $this->updateReferenceMaster($request, self::SOURCE_TYPE_MASTER, 'Textile source type updated successfully.', $this->resolveDomain($domain));
    }

    public function archiveSourceType(Request $request)
    {
        return $this->archiveReferenceMaster($request, self::SOURCE_TYPE_MASTER, 'Textile source type deactivated successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function archiveSourceTypeByDomain(Request $request, string $domain)
    {
        return $this->archiveReferenceMaster($request, self::SOURCE_TYPE_MASTER, 'Textile source type deactivated successfully.', $this->resolveDomain($domain));
    }

    public function machineTypes()
    {
        return $this->renderReferenceMasterIndex('machine-types', self::MACHINE_TYPE_MASTER, self::DEFAULT_MASTER_DOMAIN);
    }

    public function sourceActions()
    {
        return $this->renderReferenceMasterIndex('source-actions', self::SOURCE_ACTION_MASTER, self::DEFAULT_MASTER_DOMAIN);
    }

    public function sourceActionsByDomain(string $domain)
    {
        return $this->renderReferenceMasterIndex('source-actions', self::SOURCE_ACTION_MASTER, $this->resolveDomain($domain));
    }

    public function storeSourceAction(Request $request)
    {
        return $this->storeReferenceMaster($request, self::SOURCE_ACTION_MASTER, 'Textile source action created successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function storeSourceActionByDomain(Request $request, string $domain)
    {
        return $this->storeReferenceMaster($request, self::SOURCE_ACTION_MASTER, 'Textile source action created successfully.', $this->resolveDomain($domain));
    }

    public function updateSourceAction(Request $request)
    {
        return $this->updateReferenceMaster($request, self::SOURCE_ACTION_MASTER, 'Textile source action updated successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function updateSourceActionByDomain(Request $request, string $domain)
    {
        return $this->updateReferenceMaster($request, self::SOURCE_ACTION_MASTER, 'Textile source action updated successfully.', $this->resolveDomain($domain));
    }

    public function archiveSourceAction(Request $request)
    {
        return $this->archiveReferenceMaster($request, self::SOURCE_ACTION_MASTER, 'Textile source action deactivated successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function archiveSourceActionByDomain(Request $request, string $domain)
    {
        return $this->archiveReferenceMaster($request, self::SOURCE_ACTION_MASTER, 'Textile source action deactivated successfully.', $this->resolveDomain($domain));
    }

    public function machineTypesByDomain(string $domain)
    {
        return $this->renderReferenceMasterIndex('machine-types', self::MACHINE_TYPE_MASTER, $this->resolveDomain($domain));
    }

    public function shedTypes()
    {
        return $this->renderReferenceMasterIndex('shed-types', self::SHED_TYPE_MASTER, self::DEFAULT_MASTER_DOMAIN);
    }

    public function shedTypesByDomain(string $domain)
    {
        return $this->renderReferenceMasterIndex('shed-types', self::SHED_TYPE_MASTER, $this->resolveDomain($domain));
    }

    public function storeShedType(Request $request)
    {
        return $this->storeReferenceMaster($request, self::SHED_TYPE_MASTER, 'Textile shed type created successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function storeShedTypeByDomain(Request $request, string $domain)
    {
        return $this->storeReferenceMaster($request, self::SHED_TYPE_MASTER, 'Textile shed type created successfully.', $this->resolveDomain($domain));
    }

    public function updateShedType(Request $request)
    {
        return $this->updateReferenceMaster($request, self::SHED_TYPE_MASTER, 'Textile shed type updated successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function updateShedTypeByDomain(Request $request, string $domain)
    {
        return $this->updateReferenceMaster($request, self::SHED_TYPE_MASTER, 'Textile shed type updated successfully.', $this->resolveDomain($domain));
    }

    public function archiveShedType(Request $request)
    {
        return $this->archiveReferenceMaster($request, self::SHED_TYPE_MASTER, 'Textile shed type deactivated successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function archiveShedTypeByDomain(Request $request, string $domain)
    {
        return $this->archiveReferenceMaster($request, self::SHED_TYPE_MASTER, 'Textile shed type deactivated successfully.', $this->resolveDomain($domain));
    }

    public function loomStatuses()
    {
        return $this->renderReferenceMasterIndex('loom-statuses', self::LOOM_STATUS_MASTER, self::DEFAULT_MASTER_DOMAIN);
    }

    public function loomStatusesByDomain(string $domain)
    {
        return $this->renderReferenceMasterIndex('loom-statuses', self::LOOM_STATUS_MASTER, $this->resolveDomain($domain));
    }

    public function storeLoomStatus(Request $request)
    {
        return $this->storeReferenceMaster($request, self::LOOM_STATUS_MASTER, 'Textile loom status created successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function storeLoomStatusByDomain(Request $request, string $domain)
    {
        return $this->storeReferenceMaster($request, self::LOOM_STATUS_MASTER, 'Textile loom status created successfully.', $this->resolveDomain($domain));
    }

    public function updateLoomStatus(Request $request)
    {
        return $this->updateReferenceMaster($request, self::LOOM_STATUS_MASTER, 'Textile loom status updated successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function updateLoomStatusByDomain(Request $request, string $domain)
    {
        return $this->updateReferenceMaster($request, self::LOOM_STATUS_MASTER, 'Textile loom status updated successfully.', $this->resolveDomain($domain));
    }

    public function archiveLoomStatus(Request $request)
    {
        return $this->archiveReferenceMaster($request, self::LOOM_STATUS_MASTER, 'Textile loom status deactivated successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function archiveLoomStatusByDomain(Request $request, string $domain)
    {
        return $this->archiveReferenceMaster($request, self::LOOM_STATUS_MASTER, 'Textile loom status deactivated successfully.', $this->resolveDomain($domain));
    }

    public function breakdownReasons()
    {
        return $this->renderReferenceMasterIndex('breakdown-reasons', self::BREAKDOWN_REASON_MASTER, self::DEFAULT_MASTER_DOMAIN);
    }

    public function breakdownReasonsByDomain(string $domain)
    {
        return $this->renderReferenceMasterIndex('breakdown-reasons', self::BREAKDOWN_REASON_MASTER, $this->resolveDomain($domain));
    }

    public function storeBreakdownReason(Request $request)
    {
        return $this->storeReferenceMaster($request, self::BREAKDOWN_REASON_MASTER, 'Textile breakdown reason created successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function storeBreakdownReasonByDomain(Request $request, string $domain)
    {
        return $this->storeReferenceMaster($request, self::BREAKDOWN_REASON_MASTER, 'Textile breakdown reason created successfully.', $this->resolveDomain($domain));
    }

    public function updateBreakdownReason(Request $request)
    {
        return $this->updateReferenceMaster($request, self::BREAKDOWN_REASON_MASTER, 'Textile breakdown reason updated successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function updateBreakdownReasonByDomain(Request $request, string $domain)
    {
        return $this->updateReferenceMaster($request, self::BREAKDOWN_REASON_MASTER, 'Textile breakdown reason updated successfully.', $this->resolveDomain($domain));
    }

    public function archiveBreakdownReason(Request $request)
    {
        return $this->archiveReferenceMaster($request, self::BREAKDOWN_REASON_MASTER, 'Textile breakdown reason deactivated successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function archiveBreakdownReasonByDomain(Request $request, string $domain)
    {
        return $this->archiveReferenceMaster($request, self::BREAKDOWN_REASON_MASTER, 'Textile breakdown reason deactivated successfully.', $this->resolveDomain($domain));
    }

    public function maintenanceTypes()
    {
        return $this->renderReferenceMasterIndex('maintenance-types', self::MAINTENANCE_TYPE_MASTER, self::DEFAULT_MASTER_DOMAIN);
    }

    public function maintenanceTypesByDomain(string $domain)
    {
        return $this->renderReferenceMasterIndex('maintenance-types', self::MAINTENANCE_TYPE_MASTER, $this->resolveDomain($domain));
    }

    public function storeMaintenanceType(Request $request)
    {
        return $this->storeReferenceMaster($request, self::MAINTENANCE_TYPE_MASTER, 'Textile maintenance type created successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function storeMaintenanceTypeByDomain(Request $request, string $domain)
    {
        return $this->storeReferenceMaster($request, self::MAINTENANCE_TYPE_MASTER, 'Textile maintenance type created successfully.', $this->resolveDomain($domain));
    }

    public function updateMaintenanceType(Request $request)
    {
        return $this->updateReferenceMaster($request, self::MAINTENANCE_TYPE_MASTER, 'Textile maintenance type updated successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function updateMaintenanceTypeByDomain(Request $request, string $domain)
    {
        return $this->updateReferenceMaster($request, self::MAINTENANCE_TYPE_MASTER, 'Textile maintenance type updated successfully.', $this->resolveDomain($domain));
    }

    public function archiveMaintenanceType(Request $request)
    {
        return $this->archiveReferenceMaster($request, self::MAINTENANCE_TYPE_MASTER, 'Textile maintenance type deactivated successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function archiveMaintenanceTypeByDomain(Request $request, string $domain)
    {
        return $this->archiveReferenceMaster($request, self::MAINTENANCE_TYPE_MASTER, 'Textile maintenance type deactivated successfully.', $this->resolveDomain($domain));
    }

    public function costTypes()
    {
        return $this->renderReferenceMasterIndex('cost-types', self::COST_TYPE_MASTER, self::DEFAULT_MASTER_DOMAIN);
    }

    public function costTypesByDomain(string $domain)
    {
        return $this->renderReferenceMasterIndex('cost-types', self::COST_TYPE_MASTER, $this->resolveDomain($domain));
    }

    public function storeCostType(Request $request)
    {
        return $this->storeReferenceMaster($request, self::COST_TYPE_MASTER, 'Textile cost type created successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function storeCostTypeByDomain(Request $request, string $domain)
    {
        return $this->storeReferenceMaster($request, self::COST_TYPE_MASTER, 'Textile cost type created successfully.', $this->resolveDomain($domain));
    }

    public function updateCostType(Request $request)
    {
        return $this->updateReferenceMaster($request, self::COST_TYPE_MASTER, 'Textile cost type updated successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function updateCostTypeByDomain(Request $request, string $domain)
    {
        return $this->updateReferenceMaster($request, self::COST_TYPE_MASTER, 'Textile cost type updated successfully.', $this->resolveDomain($domain));
    }

    public function archiveCostType(Request $request)
    {
        return $this->archiveReferenceMaster($request, self::COST_TYPE_MASTER, 'Textile cost type deactivated successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function archiveCostTypeByDomain(Request $request, string $domain)
    {
        return $this->archiveReferenceMaster($request, self::COST_TYPE_MASTER, 'Textile cost type deactivated successfully.', $this->resolveDomain($domain));
    }

    public function inspectionResults()
    {
        return $this->renderReferenceMasterIndex('inspection-results', self::INSPECTION_RESULT_MASTER, self::DEFAULT_MASTER_DOMAIN);
    }

    public function inspectionResultsByDomain(string $domain)
    {
        return $this->renderReferenceMasterIndex('inspection-results', self::INSPECTION_RESULT_MASTER, $this->resolveDomain($domain));
    }

    public function storeInspectionResult(Request $request)
    {
        return $this->storeReferenceMaster($request, self::INSPECTION_RESULT_MASTER, 'Textile inspection result created successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function storeInspectionResultByDomain(Request $request, string $domain)
    {
        return $this->storeReferenceMaster($request, self::INSPECTION_RESULT_MASTER, 'Textile inspection result created successfully.', $this->resolveDomain($domain));
    }

    public function updateInspectionResult(Request $request)
    {
        return $this->updateReferenceMaster($request, self::INSPECTION_RESULT_MASTER, 'Textile inspection result updated successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function updateInspectionResultByDomain(Request $request, string $domain)
    {
        return $this->updateReferenceMaster($request, self::INSPECTION_RESULT_MASTER, 'Textile inspection result updated successfully.', $this->resolveDomain($domain));
    }

    public function archiveInspectionResult(Request $request)
    {
        return $this->archiveReferenceMaster($request, self::INSPECTION_RESULT_MASTER, 'Textile inspection result deactivated successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function archiveInspectionResultByDomain(Request $request, string $domain)
    {
        return $this->archiveReferenceMaster($request, self::INSPECTION_RESULT_MASTER, 'Textile inspection result deactivated successfully.', $this->resolveDomain($domain));
    }

    public function storeMachineType(Request $request)
    {
        return $this->storeReferenceMaster($request, self::MACHINE_TYPE_MASTER, 'Textile machine type created successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function storeMachineTypeByDomain(Request $request, string $domain)
    {
        return $this->storeReferenceMaster($request, self::MACHINE_TYPE_MASTER, 'Textile machine type created successfully.', $this->resolveDomain($domain));
    }

    public function updateMachineType(Request $request)
    {
        return $this->updateReferenceMaster($request, self::MACHINE_TYPE_MASTER, 'Textile machine type updated successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function updateMachineTypeByDomain(Request $request, string $domain)
    {
        return $this->updateReferenceMaster($request, self::MACHINE_TYPE_MASTER, 'Textile machine type updated successfully.', $this->resolveDomain($domain));
    }

    public function archiveMachineType(Request $request)
    {
        return $this->archiveReferenceMaster($request, self::MACHINE_TYPE_MASTER, 'Textile machine type deactivated successfully.', self::DEFAULT_MASTER_DOMAIN);
    }

    public function archiveMachineTypeByDomain(Request $request, string $domain)
    {
        return $this->archiveReferenceMaster($request, self::MACHINE_TYPE_MASTER, 'Textile machine type deactivated successfully.', $this->resolveDomain($domain));
    }

    public function storeUnitConversion(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'from_unit' => 'required|string|max:50',
            'to_unit' => 'required|string|max:50',
            'factor' => 'required|numeric|gt:0',
        ]);

        TextileUnitConversion::create([...$validated, 'is_active' => true, 'created_by' => creatorId(), 'creator_id' => Auth::id()]);

        return back()->with('success', __('Textile unit conversion created successfully.'));
    }

    public function updateUnitConversion(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'record_id' => 'required|integer|min:1',
            'from_unit' => 'required|string|max:50',
            'to_unit' => 'required|string|max:50',
            'factor' => 'required|numeric|gt:0',
        ]);

        $record = TextileUnitConversion::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['record_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->update([
            'from_unit' => $validated['from_unit'],
            'to_unit' => $validated['to_unit'],
            'factor' => $validated['factor'],
        ]);

        return back()->with('success', __('Textile unit conversion updated successfully.'));
    }

    public function archiveUnitConversion(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'record_id' => 'required|integer|min:1',
        ]);

        $record = TextileUnitConversion::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['record_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->is_active = false;
        $record->save();

        return back()->with('success', __('Textile unit conversion deactivated successfully.'));
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin'], true), 403);
    }

    private function referenceMasterRecords(string $masterType, string $domain = self::DEFAULT_MASTER_DOMAIN)
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return collect();
        }

        $query = TextileReferenceMaster::query()
            ->type($masterType)
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if ($this->hasDomainColumn()) {
            $query->domain($domain);
        }

        return $query->latest()->get();
    }

    private function storeReferenceMaster(Request $request, string $masterType, string $successMessage, string $domain = self::DEFAULT_MASTER_DOMAIN)
    {
        $this->authorizeTextileAccess();

        if (!Schema::hasTable('textile_reference_masters')) {
            return back()->withErrors(['name' => __('Reference master table is missing. Please run migrations.')]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $payload = [
            'master_type' => $masterType,
            'name' => trim($validated['name']),
            'code' => isset($validated['code']) ? trim((string) $validated['code']) : null,
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
            'is_active' => true,
            'created_by' => creatorId(),
            'creator_id' => Auth::id(),
        ];

        if ($this->hasDomainColumn()) {
            $payload['master_domain'] = $domain;
        }

        TextileReferenceMaster::create($payload);

        return back()->with('success', __($successMessage));
    }

    private function updateReferenceMaster(Request $request, string $masterType, string $successMessage, string $domain = self::DEFAULT_MASTER_DOMAIN)
    {
        $this->authorizeTextileAccess();

        if (!Schema::hasTable('textile_reference_masters')) {
            return back()->withErrors(['record_id' => __('Reference master table is missing. Please run migrations.')]);
        }

        $validated = $request->validate([
            'record_id' => 'required|integer|min:1',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $query = TextileReferenceMaster::query()
            ->type($masterType)
            ->where('created_by', creatorId())
            ->where('id', $validated['record_id'])
            ->where('is_active', true);

        if ($this->hasDomainColumn()) {
            $query->domain($domain);
        }

        $record = $query->firstOrFail();

        $record->update([
            'name' => trim($validated['name']),
            'code' => isset($validated['code']) ? trim((string) $validated['code']) : null,
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
        ]);

        return back()->with('success', __($successMessage));
    }

    private function archiveReferenceMaster(Request $request, string $masterType, string $successMessage, string $domain = self::DEFAULT_MASTER_DOMAIN)
    {
        $this->authorizeTextileAccess();

        if (!Schema::hasTable('textile_reference_masters')) {
            return back()->withErrors(['record_id' => __('Reference master table is missing. Please run migrations.')]);
        }

        $validated = $request->validate([
            'record_id' => 'required|integer|min:1',
        ]);

        $query = TextileReferenceMaster::query()
            ->type($masterType)
            ->where('created_by', creatorId())
            ->where('id', $validated['record_id'])
            ->where('is_active', true);

        if ($this->hasDomainColumn()) {
            $query->domain($domain);
        }

        $record = $query->firstOrFail();

        $record->is_active = false;
        $record->save();

        return back()->with('success', __($successMessage));
    }

    private function renderReferenceMasterIndex(string $master, string $masterType, string $domain)
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/Masters/Index', [
            'master' => $master,
            'records' => $this->referenceMasterRecords($masterType, $domain),
            'masterDomain' => $domain,
            'masterDomainLabel' => $this->domainLabel($domain),
        ]);
    }

    private function resolveDomain(string $domain): string
    {
        abort_unless(in_array($domain, self::REFERENCE_DOMAINS, true), 404);

        return $domain;
    }

    private function domainLabel(string $domain): ?string
    {
        if ($domain === self::DEFAULT_MASTER_DOMAIN) {
            return null;
        }

        return ucfirst($domain);
    }

    private function hasDomainColumn(): bool
    {
        return Schema::hasColumn('textile_reference_masters', 'master_domain');
    }
}