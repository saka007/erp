<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use App\Models\LoginHistory;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Models\TextileAuditLog;
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

        $loginHistoryCount = LoginHistory::query()->where('created_by', creatorId())->count();
        $auditLogCount = TextileAuditLog::query()->where('created_by', creatorId())->count();
        $recentLoginHistory = LoginHistory::query()
            ->where('created_by', creatorId())
            ->latest('id')
            ->limit(5)
            ->get(['id', 'user_id', 'ip', 'date', 'details', 'type', 'created_at']);
        $recentAuditLogs = TextileAuditLog::query()
            ->where('created_by', creatorId())
            ->latest('id')
            ->limit(5)
            ->get(['id', 'event_type', 'payload', 'creator_id', 'created_at']);

        return Inertia::render('DigitalFuzedTextileCore/Dashboard/Index', [
            'costingSummary' => $costingService->summary(),
            'byType' => $byType,
            'byStatus' => $byStatus,
            'recentDocuments' => $recentDocuments,
            'recentMargins' => $recentMargins,
            'loginHistoryCount' => $loginHistoryCount,
            'auditLogCount' => $auditLogCount,
            'recentLoginHistory' => $recentLoginHistory,
            'recentAuditLogs' => $recentAuditLogs,
        ]);
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin'], true), 403);
    }
}
