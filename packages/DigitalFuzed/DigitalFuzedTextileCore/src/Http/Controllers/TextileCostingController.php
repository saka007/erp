<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Services\TextileCostingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use RuntimeException;

class TextileCostingController extends Controller
{
    public function index(TextileCostingService $costingService)
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/Costing/Index', [
            'costingEntries' => $this->documents('costing_entry'),
            'marginSnapshots' => $this->documents('margin_snapshot'),
            'costingSummary' => $costingService->summary(),
            'eligibleSources' => TextileWorkflowDocument::query()
                ->where('created_by', creatorId())
                ->whereIn('status', ['approved', 'released', 'closed'])
                ->whereNotIn('document_type', ['costing_entry', 'margin_snapshot'])
                ->latest()
                ->limit(100)
                ->get(['id', 'document_type', 'document_number', 'lot_reference', 'quantity', 'unit', 'status']),
        ]);
    }

    public function storeCostingEntry(Request $request, TextileCostingService $costingService)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'source_document_id' => ['required', 'integer', 'min:1'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'material_cost' => ['required', 'numeric', 'gte:0'],
            'conversion_cost' => ['required', 'numeric', 'gte:0'],
            'overhead_cost' => ['required', 'numeric', 'gte:0'],
            'variance_value' => ['nullable', 'numeric'],
            'revenue_value' => ['required', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $costingService->createCostingEntry($validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['source_document_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Costing entry created successfully.'));
    }

    public function finalizeCostingEntry(Request $request, TextileCostingService $costingService)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'costing_entry_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $costingService->finalizeCostingEntry((int) $validated['costing_entry_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['costing_entry_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Costing entry finalized and margin snapshot posted successfully.'));
    }

    private function documents(string $type)
    {
        return TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->where('document_type', $type)
            ->latest()
            ->get();
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin'], true), 403);
    }
}
