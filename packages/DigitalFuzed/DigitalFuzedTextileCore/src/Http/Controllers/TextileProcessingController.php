<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Services\TextileProcessingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use RuntimeException;

class TextileProcessingController extends Controller
{
    public function index()
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/Processing/Index', [
            'outwards' => $this->documents('job_work_outward'),
            'batches' => $this->documents('processing_batch'),
            'inwards' => $this->documents('job_work_inward'),
            'reconciliations' => $this->documents('job_work_reconciliation'),
        ]);
    }

    public function storeOutward(Request $request, TextileProcessingService $service)
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

        $service->createJobWorkOutward($validated);

        return back()->with('success', __('Job-work outward created successfully.'));
    }

    public function releaseOutward(Request $request, TextileProcessingService $service)
    {
        $this->authorizeTextileAccess();

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

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin'], true), 403);
    }
}
