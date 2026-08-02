<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Services\TextileManufacturingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use RuntimeException;

class TextileManufacturingController extends Controller
{
    public function index()
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/Manufacturing/Index', [
            'beams' => $this->documents('beam'),
            'productionBatches' => $this->documents('production_batch'),
            'weavingOutputs' => $this->documents('weaving_output'),
            'wastes' => $this->documents('waste'),
            'reworks' => $this->documents('rework'),
        ]);
    }

    public function storeBeam(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'source_reference_type' => ['required', 'string', 'max:100'],
            'source_reference_id' => ['required', 'integer', 'min:1'],
            'source_action' => ['nullable', 'string', 'max:100'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $service->createBeam($validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['source_reference_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Beam created successfully.'));
    }

    public function approveBeam(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'beam_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->approveBeam((int) $validated['beam_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['beam_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Beam approved successfully.'));
    }

    public function storeProductionBatch(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'beam_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createProductionBatch((int) $validated['beam_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['beam_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Production batch created successfully.'));
    }

    public function releaseProductionBatch(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'batch_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->releaseProductionBatch((int) $validated['batch_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['batch_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Production batch released successfully.'));
    }

    public function storeWeavingOutput(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'batch_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $service->createWeavingOutput((int) $validated['batch_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['batch_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Weaving output recorded successfully.'));
    }

    public function storeWaste(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'batch_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $service->createWaste((int) $validated['batch_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['batch_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Waste recorded successfully.'));
    }

    public function storeRework(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'weaving_output_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $service->createRework((int) $validated['weaving_output_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['weaving_output_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Rework recorded successfully.'));
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
