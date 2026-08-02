<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use App\Models\LoginHistory;
use DigitalFuzed\TextileCore\Models\TextileAuditLog;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TextileLogController extends Controller
{
    public function index()
    {
        $this->authorizeTextileAccess();

        $tenantId = creatorId();

        return Inertia::render('DigitalFuzedTextileCore/Logs/Index', [
            'loginHistory' => LoginHistory::query()
                ->where('created_by', $tenantId)
                ->latest('id')
                ->limit(50)
                ->get(['id', 'user_id', 'ip', 'date', 'details', 'type', 'created_by', 'created_at']),
            'auditLogs' => TextileAuditLog::query()
                ->where('created_by', $tenantId)
                ->latest('id')
                ->limit(50)
                ->get(['id', 'event_type', 'payload', 'creator_id', 'created_by', 'created_at']),
        ]);
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin'], true), 403);
    }
}