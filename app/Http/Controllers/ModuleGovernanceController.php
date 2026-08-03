<?php

namespace App\Http\Controllers;

use App\Services\TenantModuleGovernanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class ModuleGovernanceController extends Controller
{
    public function __construct(protected TenantModuleGovernanceService $service)
    {
    }

    public function updateEntitlement(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:users,id'],
            'module_key' => ['required', 'string', 'max:120'],
            'is_entitled' => ['required', 'boolean'],
            'requires_approval' => ['nullable', 'boolean'],
        ]);

        try {
            $this->service->updateEntitlement(
                Auth::user(),
                (int) $validated['tenant_id'],
                (string) $validated['module_key'],
                (bool) $validated['is_entitled'],
                (bool) ($validated['requires_approval'] ?? false)
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['module_key' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Module entitlement updated successfully.'));
    }

    public function activate(Request $request)
    {
        $validated = $request->validate([
            'module_key' => ['required', 'string', 'max:120'],
            'request_note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $this->service->activateModule(Auth::user(), (string) $validated['module_key'], $validated['request_note'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['module_key' => __($exception->getMessage())]);
        }

        if ($result === 'requested') {
            return back()->with('success', __('Activation request submitted for superadmin approval.'));
        }

        if ($result === 'request_pending') {
            return back()->with('error', __('An activation request for this module is already pending.'));
        }

        return back()->with('success', __('Module activated successfully.'));
    }

    public function deactivate(Request $request)
    {
        $validated = $request->validate([
            'module_key' => ['required', 'string', 'max:120'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->service->deactivateModule(Auth::user(), (string) $validated['module_key'], $validated['reason'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['module_key' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Module deactivated successfully.'));
    }

    public function reviewRequest(Request $request, int $id)
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->service->reviewRequest(Auth::user(), $id, (string) $validated['decision'], $validated['review_note'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['decision' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Module request reviewed successfully.'));
    }
}
