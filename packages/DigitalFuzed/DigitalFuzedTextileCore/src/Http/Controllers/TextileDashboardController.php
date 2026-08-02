<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Services\TextileCostingService;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TextileDashboardController extends Controller
{
    public function index(TextileCostingService $costingService)
    {
        $this->authorizeTextileAccess();

        $baseQuery = TextileWorkflowDocument::query()->where('created_by', creatorId());

        $byType = (clone $baseQuery)
            ->selectRaw('document_type, COUNT(*) as total')
            ->groupBy('document_type')
            ->orderBy('document_type')
            ->get();

        $byStatus = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        $recentDocuments = (clone $baseQuery)
            ->latest()
            ->limit(20)
            ->get(['id', 'document_type', 'document_number', 'party_name', 'lot_reference', 'quantity', 'unit', 'status', 'updated_at']);

        $recentMargins = TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->where('document_type', 'margin_snapshot')
            ->latest()
            ->limit(10)
            ->get(['id', 'document_number', 'lot_reference', 'quantity', 'unit', 'metadata', 'updated_at']);

        return Inertia::render('DigitalFuzedTextileCore/Dashboard/Index', [
            'costingSummary' => $costingService->summary(),
            'byType' => $byType,
            'byStatus' => $byStatus,
            'recentDocuments' => $recentDocuments,
            'recentMargins' => $recentMargins,
        ]);
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin'], true), 403);
    }
}
