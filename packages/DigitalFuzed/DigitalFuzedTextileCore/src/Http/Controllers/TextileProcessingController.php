<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Models\TextileReferenceMaster;
use DigitalFuzed\TextileCore\Models\TextileUnitConversion;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use DigitalFuzed\TextileCore\Services\TextileProcessingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use RuntimeException;
use Workdo\Account\Models\Vendor;

class TextileProcessingController extends Controller
{
    public function __construct(protected TextileOperatingPolicyService $policyService)
    {
    }

    public function index()
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapabilityOrAbort('processing');

        return Inertia::render('DigitalFuzedTextileCore/Processing/Index', [
            'outwards' => $this->documents('job_work_outward'),
            'batches' => $this->documents('processing_batch'),
            'inwards' => $this->documents('job_work_inward'),
            'reconciliations' => $this->documents('job_work_reconciliation'),
            'sourceTypeOptions' => $this->sourceTypeOptions(),
            'sourceActionOptions' => $this->sourceActionOptions(),
            'unitOptions' => $this->unitOptions(),
            'partyOptions' => $this->partyOptions(),
            'lotReferenceOptions' => $this->lotReferenceOptions(),
        ]);
    }

    public function storeOutward(Request $request, TextileProcessingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('processing', 'lot_reference');

        $validated = $request->validate([
            'source_reference_type' => ['nullable', 'string', 'max:100'],
            'source_reference_id' => ['nullable', 'integer', 'min:1'],
            'source_action' => ['nullable', 'string', 'max:100'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        $service->createJobWorkOutward($validated);

        return back()->with('success', __('Job-work outward created successfully.'));
    }

    public function releaseOutward(Request $request, TextileProcessingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('processing', 'outward_id');

        $validated = $request->validate([
            'outward_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->releaseJobWorkOutward((int) $validated['outward_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['outward_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Job-work outward released successfully.'));
    }

    public function storeBatch(Request $request, TextileProcessingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('processing', 'outward_id');

        $validated = $request->validate([
            'outward_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createProcessingBatch((int) $validated['outward_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['outward_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Processing batch created successfully.'));
    }

    public function releaseBatch(Request $request, TextileProcessingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('processing', 'batch_id');

        $validated = $request->validate([
            'batch_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->releaseProcessingBatch((int) $validated['batch_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['batch_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Processing batch released successfully.'));
    }

    public function storeInward(Request $request, TextileProcessingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('processing', 'batch_id');

        $validated = $request->validate([
            'batch_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $service->createJobWorkInward((int) $validated['batch_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['batch_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Job-work inward created successfully.'));
    }

    public function finalizeInward(Request $request, TextileProcessingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('processing', 'inward_id');

        $validated = $request->validate([
            'inward_id' => ['required', 'integer', 'min:1'],
            'decision' => ['required', 'in:pass,fail'],
        ]);

        try {
            $service->finalizeJobWorkInward((int) $validated['inward_id'], $validated['decision']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['inward_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Job-work inward finalized successfully.'));
    }

    public function reconcile(Request $request, TextileProcessingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('processing', 'inward_id');

        $validated = $request->validate([
            'outward_id' => ['required', 'integer', 'min:1'],
            'inward_id' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $service->reconcileJobWork((int) $validated['outward_id'], (int) $validated['inward_id'], $validated['notes'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['inward_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Job-work reconciliation completed successfully.'));
    }

    private function documents(string $type)
    {
        return TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->where('document_type', $type)
            ->latest()
            ->get();
    }

    private function sourceTypeOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultSourceTypeOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('source_type')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('processing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultSourceTypeOptions();
    }

    private function defaultSourceTypeOptions(): array
    {
        return [
            'job_work_order',
            'processing_order',
            'vendor_challan',
        ];
    }

    private function unitOptions(): array
    {
        return TextileUnitConversion::query()
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->get(['from_unit', 'to_unit'])
            ->flatMap(fn ($row) => [$row->from_unit, $row->to_unit])
            ->filter(fn ($unit) => is_string($unit) && trim($unit) !== '')
            ->map(fn ($unit) => trim((string) $unit))
            ->unique()
            ->values()
            ->all();
    }

    private function sourceActionOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultSourceActionOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('source_action')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('processing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultSourceActionOptions();
    }

    private function defaultSourceActionOptions(): array
    {
        return [
            'job_work_issue',
            'processing_start',
            'job_work_receive',
            'job_work_reconcile',
        ];
    }

    private function partyOptions(): array
    {
        $vendors = collect();
        if (Schema::hasTable('vendors')) {
            $vendors = Vendor::query()
                ->where('created_by', creatorId())
                ->whereNotNull('company_name')
                ->pluck('company_name');
        }

        $workflowParties = TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->whereNotNull('party_name')
            ->pluck('party_name');

        return $vendors
            ->merge($workflowParties)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function lotReferenceOptions(): array
    {
        $lots = collect();
        if (Schema::hasTable('textile_lots')) {
            $lots = TextileLot::query()
                ->where('created_by', creatorId())
                ->where('is_active', true)
                ->pluck('lot_reference');
        }

        $workflowLots = TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->whereNotNull('lot_reference')
            ->pluck('lot_reference');

        return $lots
            ->merge($workflowLots)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin'], true), 403);
    }

    private function authorizeCapability(string $capability, string $errorKey): void
    {
        try {
            $this->policyService->assertCapability($capability);
        } catch (RuntimeException $exception) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $errorKey => __($exception->getMessage()),
            ]);
        }
    }

    private function authorizeCapabilityOrAbort(string $capability): void
    {
        try {
            $this->policyService->assertCapability($capability);
        } catch (RuntimeException $exception) {
            abort(403, __($exception->getMessage()));
        }
    }
}
