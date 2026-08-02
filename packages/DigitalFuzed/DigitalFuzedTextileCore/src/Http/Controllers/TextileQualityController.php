<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Models\TextileReferenceMaster;
use DigitalFuzed\TextileCore\Models\TextileUnitConversion;
use DigitalFuzed\TextileCore\Services\TextileQualityService;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use RuntimeException;

class TextileQualityController extends Controller
{
    public function index()
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/Quality/Index', [
            'inspections' => $this->documents('inspection'),
            'holds' => $this->documents('hold_release'),
            'lots' => TextileLot::query()->where('created_by', creatorId())->latest()->get(),
            'sourceTypeOptions' => $this->sourceTypeOptions(),
            'unitOptions' => $this->unitOptions(),
        ]);
    }

    public function storeInspection(Request $request, TextileQualityService $service)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'source_reference_type' => ['nullable', 'string', 'max:100'],
            'source_reference_id' => ['nullable', 'integer', 'min:1'],
            'source_action' => ['nullable', 'string', 'max:100'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $service->createInspection($validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['lot_reference' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Inspection created successfully.'));
    }

    public function finalizeInspection(Request $request, TextileQualityService $service)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'inspection_id' => ['required', 'integer', 'min:1'],
            'decision' => ['required', 'in:pass,fail'],
        ]);

        try {
            $service->finalizeInspection((int) $validated['inspection_id'], $validated['decision']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['inspection_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Inspection finalized successfully.'));
    }

    public function holdLot(Request $request, TextileQualityService $service)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'lot_reference' => ['required', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->holdLot($validated['lot_reference'], (string) ($validated['reason'] ?? 'Quality hold'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['lot_reference' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Lot hold applied successfully.'));
    }

    public function releaseLot(Request $request, TextileQualityService $service)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'lot_reference' => ['required', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->releaseLot($validated['lot_reference'], (string) ($validated['reason'] ?? 'Quality release'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['lot_reference' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Lot release applied successfully.'));
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
        return TextileReferenceMaster::query()
            ->type('source_type')
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();
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

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin'], true), 403);
    }
}
