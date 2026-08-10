<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use DigitalFuzed\TextileCore\Services\TextilePaymentReminderService;
use DigitalFuzed\TextileCore\Support\TextileBranchScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use RuntimeException;

/**
 * Unified party management — yarn vendors, sizing vendors, powerloom vendors
 * and customers (buyers) in one page with category filters and credit settings.
 */
class TextilePartyController extends Controller
{
    public const CATEGORY_ALL = 'all';
    public const CATEGORY_YARN = 'yarn';
    public const CATEGORY_SIZING = 'sizing';
    public const CATEGORY_POWERLOOM = 'powerloom';
    public const CATEGORY_CUSTOMER = 'customer';
    public const CATEGORY_OTHER = 'other';

    public function __construct(protected TextileOperatingPolicyService $policyService)
    {
    }

    public function index(Request $request, TextilePaymentReminderService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapabilityOrAbort('payments');

        $category = $request->input('category', 'all');
        $search = trim((string) $request->input('search', ''));
        // Branch comes automatically from the user's session scope (assigned branch,
        // active branch, or employee branch) — never user-selectable.
        $branchId = TextileBranchScope::branchIdForCreate();

        $parties = collect($service->partyMasters())
            ->filter(function (array $party) use ($category) {
                if ($category === self::CATEGORY_ALL) {
                    return true;
                }

                if ($party['party_type'] === TextilePaymentReminderService::PARTY_BUYER) {
                    return $category === self::CATEGORY_CUSTOMER;
                }

                $supplierType = $party['supplier_type'] ?? null;

                return match ($category) {
                    self::CATEGORY_YARN => $supplierType === 'yarn',
                    self::CATEGORY_SIZING => $supplierType === 'sizing',
                    self::CATEGORY_POWERLOOM => $supplierType === 'powerloom',
                    self::CATEGORY_OTHER => ! in_array($supplierType, ['yarn', 'sizing', 'powerloom'], true),
                    default => false,
                };
            })
            ->filter(function (array $party) use ($branchId) {
                if ($branchId === null) {
                    return true;
                }

                $partyBranchId = $party['branch_id'] ?? null;

                // Parties without a branch are global — visible in all branches.
                if ($partyBranchId === null || $partyBranchId === '') {
                    return true;
                }

                // Otherwise show only parties whose record branch matches the
                // user's session-active branch (selected in the header dropdown).
                return (int) $partyBranchId === $branchId;
            })
            ->filter(function (array $party) use ($search) {
                if ($search === '') {
                    return true;
                }

                return mb_stristr((string) ($party['party_name'] ?? ''), $search) !== false
                    || mb_stristr((string) ($party['party_code'] ?? ''), $search) !== false;
            })
            ->values()
            ->all();

        return Inertia::render('DigitalFuzedTextileCore/Parties/Index', [
            'parties' => $parties,
            'categoryOptions' => [
                ['value' => 'all', 'label' => __('All Parties')],
                ['value' => self::CATEGORY_YARN, 'label' => __('Yarn Suppliers')],
                ['value' => self::CATEGORY_SIZING, 'label' => __('Sizing Vendors')],
                ['value' => self::CATEGORY_POWERLOOM, 'label' => __('Powerloom Vendors')],
                ['value' => self::CATEGORY_CUSTOMER, 'label' => __('Customers (Buyers)')],
                ['value' => self::CATEGORY_OTHER, 'label' => __('Other Vendors')],
            ],
            'selectedCategory' => $category,
            'search' => $search,
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
