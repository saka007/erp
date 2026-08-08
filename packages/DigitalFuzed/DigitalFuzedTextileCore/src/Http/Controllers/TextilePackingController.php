<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileReferenceMaster;
use DigitalFuzed\TextileCore\Models\TextileUnitConversion;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use DigitalFuzed\TextileCore\Services\TextilePackingService;
use DigitalFuzed\TextileCore\Traits\ProvidesRecentActivity;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use RuntimeException;

class TextilePackingController extends Controller
{
    use ProvidesRecentActivity;

    public function __construct(protected TextileOperatingPolicyService $policyService)
    {
    }

    public function index()
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapabilityOrAbort('sales_challan_pod');

        return Inertia::render('DigitalFuzedTextileCore/Packing/Index', [
            'rollPackings' => $this->documents('roll_packing'),
            'bundlePackings' => $this->documents('bundle_packing'),
            'balePackings' => $this->documents('bale_packing'),
            'labels' => $this->documents('packing_label'),
            'challans' => $this->documents('challan')->filter(fn (TextileWorkflowDocument $row) => in_array($row->status, ['released', 'closed'], true))->values(),
            'sourceTypeOptions' => $this->sourceTypeOptions(),
            'packingMaterialOptions' => $this->packingMaterialOptions(),
            'labelTypeOptions' => $this->labelTypeOptions(),
            'unitOptions' => $this->unitOptions(),
            'lotReferenceOptions' => $this->lotReferenceOptions(),
            'recentActivity' => $this->recentActivity(),
        ]);
    }

    public function storeRollPacking(Request $request, TextilePackingService $service)
    {
        $this->storePacking($request, $service, 'roll');

        return back()->with('success', __('Roll packing created successfully.'));
    }

    public function storeBundlePacking(Request $request, TextilePackingService $service)
    {
        $this->storePacking($request, $service, 'bundle');

        return back()->with('success', __('Bundle packing created successfully.'));
    }

    public function storeBalePacking(Request $request, TextilePackingService $service)
    {
        $this->storePacking($request, $service, 'bale');

        return back()->with('success', __('Bale packing created successfully.'));
    }

    public function storeLabel(Request $request, TextilePackingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('sales_challan_pod', 'lot_reference');

        $validated = $request->validate([
            'source_reference_type' => ['required', 'string', Rule::in($this->sourceTypeOptions())],
            'challan_id' => ['nullable', 'integer', 'min:1'],
            'lot_reference' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', Rule::in($this->unitOptions())],
            'packing_material' => ['required', 'string', Rule::in($this->packingMaterialOptions())],
            'label_type' => ['required', 'string', Rule::in($this->labelTypeOptions())],
            'label_code' => ['nullable', 'string', 'max:100'],
            'weight' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->createLabel($validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['challan_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Packing label created successfully.'));
    }

    public function issueLabel(Request $request, TextilePackingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('sales_challan_pod', 'label_id');

        $validated = $request->validate([
            'label_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->issueLabel((int) $validated['label_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['label_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Packing label issued successfully.'));
    }

    protected function storePacking(Request $request, TextilePackingService $service, string $type): void
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('sales_challan_pod', 'lot_reference');

        $validated = $request->validate([
            'source_reference_type' => ['required', 'string', Rule::in($this->sourceTypeOptions())],
            'challan_id' => ['nullable', 'integer', 'min:1'],
            'lot_reference' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', Rule::in($this->unitOptions())],
            'packing_material' => ['required', 'string', Rule::in($this->packingMaterialOptions())],
            'weight' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            if ($type === 'roll') {
                $service->createRollPacking($validated);
            } elseif ($type === 'bundle') {
                $service->createBundlePacking($validated);
            } else {
                $service->createBalePacking($validated);
            }
        } catch (RuntimeException $exception) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'challan_id' => __($exception->getMessage()),
            ]);
        }
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
            return ['challan', 'dispatch', 'lot'];
        }

        $query = TextileReferenceMaster::query()
            ->type('source_type')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('packing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : ['challan', 'dispatch', 'lot'];
    }

    private function packingMaterialOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return ['poly_wrap', 'carton_box', 'jute_bale', 'fabric_strap'];
        }

        $query = TextileReferenceMaster::query()
            ->type('source_action')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('packing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : ['poly_wrap', 'carton_box', 'jute_bale', 'fabric_strap'];
    }

    private function labelTypeOptions(): array
    {
        return ['barcode', 'qr'];
    }

    private function unitOptions(): array
    {
        $units = TextileUnitConversion::query()
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->get(['from_unit', 'to_unit'])
            ->flatMap(fn ($row) => [$row->from_unit, $row->to_unit])
            ->filter(fn ($unit) => is_string($unit) && trim($unit) !== '')
            ->map(fn ($unit) => trim((string) $unit))
            ->unique()
            ->values()
            ->all();

        if (count($units) === 0) {
            return ['kg', 'mtr', 'pcs'];
        }

        return $units;
    }

    private function lotReferenceOptions(): array
    {
        $lots = TextileLot::query()
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->pluck('lot_reference');

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

        abort_unless($user && in_array($user->type, ['company', 'superadmin', 'staff'], true), 403);
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
