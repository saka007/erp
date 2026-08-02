<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use App\Models\User;
use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use RuntimeException;

class TextileOperatingPolicyController extends Controller
{
    public function __construct(protected TextileOperatingPolicyService $policyService)
    {
    }

    public function index()
    {
        $this->authorizeTextileAccess();

        $user = Auth::user();
        $selectedCompanyId = $this->targetTenantFromRequest(request(), $user?->type === 'superadmin');
        $targetTenantId = $selectedCompanyId ?? creatorId();

        $policy = $this->policyService->resolveForTenant($targetTenantId);

        $companies = [];
        if ($user?->type === 'superadmin') {
            $companies = User::query()
                ->where('type', 'company')
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        }

        return Inertia::render('DigitalFuzedTextileCore/OperatingPolicy/Index', [
            'policy' => $policy,
            'capabilities' => $this->policyService->capabilities($policy),
            'isSuperadmin' => $user?->type === 'superadmin',
            'selectedCompanyId' => $selectedCompanyId,
            'companies' => $companies,
            'options' => [
                'operatingModels' => $this->policyService->options(),
                'materialOwnership' => $this->policyService->materialOwnershipOptions(),
                'billingModes' => $this->policyService->billingModeOptions(),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:users,id'],
            'operating_model' => ['required', 'string', 'max:80'],
            'material_ownership' => ['required', 'string', 'max:30'],
            'billing_mode' => ['required', 'string', 'max:30'],
        ]);

        $user = Auth::user();
        $isSuperadmin = $user?->type === 'superadmin';
        $targetTenantId = $isSuperadmin
            ? ($validated['company_id'] ?? null)
            : creatorId();

        if ($isSuperadmin && $targetTenantId === null) {
            return back()->withErrors(['company_id' => __('Please select a company first.')]);
        }

        if (!$isSuperadmin && isset($validated['company_id'])) {
            return back()->withErrors(['company_id' => __('Only superadmin can update another company policy.')]);
        }

        try {
            $this->policyService->updateForTenant($targetTenantId, $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['operating_model' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Operating model policy updated successfully.'));
    }

    private function targetTenantFromRequest(Request $request, bool $isSuperadmin): ?int
    {
        if (!$isSuperadmin) {
            return null;
        }

        $companyId = $request->query('company_id');
        if ($companyId === null || $companyId === '') {
            return User::query()->where('type', 'company')->orderBy('name')->value('id');
        }

        return (int) $companyId;
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin'], true), 403);
    }
}
