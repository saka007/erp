<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use DigitalFuzed\TextileCore\Services\TextilePaymentReminderService;
use DigitalFuzed\TextileCore\Support\TextileBranchScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use RuntimeException;

class TextilePaymentController extends Controller
{
    public function __construct(protected TextileOperatingPolicyService $policyService)
    {
    }

    public function index(Request $request, TextilePaymentReminderService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapabilityOrAbort('payments');

        $branchId = $request->has('branch_id') && $request->input('branch_id') !== ''
            ? (int) $request->input('branch_id')
            : null;

        return Inertia::render('DigitalFuzedTextileCore/Payments/Index', [
            'summary' => $service->summary(null, $branchId),
            'partyMasters' => $service->partyMasters(),
            'branchOptions' => $service->branchOptions(),
            'selectedBranchId' => $branchId,
            'templateNames' => [
                'supplier' => TextilePaymentReminderService::TEMPLATE_SUPPLIER,
                'buyer' => TextilePaymentReminderService::TEMPLATE_BUYER,
            ],
        ]);
    }

    public function updateCredit(Request $request, TextilePaymentReminderService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapabilityOrAbort('payments');

        $validated = $request->validate([
            'party_type' => ['required', Rule::in([TextilePaymentReminderService::PARTY_SUPPLIER, TextilePaymentReminderService::PARTY_BUYER])],
            'party_id' => ['required', 'integer'],
            'credit_enabled' => ['required', 'boolean'],
            'credit_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'reminder_enabled' => ['required', 'boolean'],
        ]);

        // Branch comes automatically from the user's session scope — never user-selectable.
        $validated['branch_id'] = TextileBranchScope::branchIdForCreate();

        try {
            $party = $service->updateCredit($validated['party_type'], (int) $validated['party_id'], $validated);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'party_id' => __($exception->getMessage()),
            ]);
        }

        return back()->with('flash', [
            'success' => __('Credit settings updated for :name.', ['name' => $party['party_name']]),
        ]);
    }

    public function sendReminders(Request $request, TextilePaymentReminderService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapabilityOrAbort('payments');

        $validated = $request->validate([
            'party_type' => ['nullable', Rule::in([TextilePaymentReminderService::PARTY_SUPPLIER, TextilePaymentReminderService::PARTY_BUYER])],
            'party_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'force' => ['nullable', 'boolean'],
        ]);

        $result = $service->sendDueReminders(
            (bool) ($validated['force'] ?? false),
            null,
            $validated['party_type'] ?? null,
            isset($validated['party_id']) ? (int) $validated['party_id'] : null,
            isset($validated['branch_id']) ? (int) $validated['branch_id'] : null
        );

        return back()->with('flash', [
            'success' => __('Reminders sent: :sent, skipped: :skipped, failed: :failed.', [
                'sent' => $result['sent'],
                'skipped' => $result['skipped'],
                'failed' => $result['failed'],
            ]),
        ]);
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin', 'staff'], true), 403);
    }

    private function authorizeCapabilityOrAbort(string $capability): void
    {
        try {
            $this->policyService->assertCapability($capability);
        } catch (RuntimeException $exception) {
            abort(403, $exception->getMessage());
        }
    }
}
