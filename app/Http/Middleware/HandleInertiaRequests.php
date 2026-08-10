<?php

namespace App\Http\Middleware;

use App\Models\User;
use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Classes\Module;
use Throwable;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        if (!$this->isInstalled()) {
            return [];
        }
        $locale = $request->user()->lang ?? $this->getSuperAdminLang();

        if (config('app.is_demo') && Cookie::get('language')) {
            $locale = Cookie::get('language');
        }

        app()->setLocale($locale);

        $languageFile = resource_path('lang/language.json');
        $defaultLanguages = [];
        if (file_exists($languageFile)) {
            $languages = json_decode(file_get_contents($languageFile), true) ?? [];
            $defaultLanguages = array_values($languages);
        }

        $activatedPackages = ActivatedModule();
        $industryType = 'standard';
        $textileCapabilities = [];

        if ($request->user()) {
            $industryType = $this->detectIndustryType($request->user());
            $activatedPackages = $this->filterActivatedPackagesForIndustry($request->user(), $activatedPackages, $industryType);
            $textileCapabilities = $this->resolveTextileCapabilitiesForUser($request->user(), $industryType);

            if ($textileCapabilities === [] && $industryType === 'textile') {
                $companyContextUser = $this->resolveCompanyContextUser($request->user());

                if ($companyContextUser) {
                    try {
                        /** @var TextileOperatingPolicyService $policyService */
                        $policyService = app(TextileOperatingPolicyService::class);
                        $textileCapabilities = $policyService->capabilities($policyService->resolveForTenant($companyContextUser->id));
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                }
            }
        }

        $branchContext = [
            'active_branch_id' => null,
            'can_manage_all_branches' => false,
            'can_switch_branch' => false,
            'branches' => [],
        ];

        if ($request->user()) {
            $user = $request->user();
            $tenantId = (int) creatorId();
            $isTenantRoot = in_array($user->type, ['company', 'superadmin'], true);
            $assignedBranchIds = $isTenantRoot
                ? []
                : \DigitalFuzed\TextileCore\Services\TextileUserBranchService::branchIdsForUser($user->id, $tenantId);

            $branchContext['can_manage_all_branches'] = $this->canManageAllBranches($user);

if ($isTenantRoot) {
            // Tenant root (company/superadmin): can view all tenant branches.
            $branchContext['can_switch_branch'] = $this->canManageAllBranches($user);
            $branchContext['branches'] = $this->branchOptions($tenantId);
        } else {
            // Staff are always branch scoped: the top switcher only appears when
            // the user has multiple assigned branches, and it is limited to those
            // branches. The manage-any-branches permission does not grant an
            // all-branches view to staff - branch flexibility is driven solely
            // by explicit branch assignments.
                $branchContext['can_switch_branch'] = count($assignedBranchIds) > 1;
                $branchContext['branches'] = $this->assignedBranchOptions($tenantId, $assignedBranchIds);
            }

            $branchContext['active_branch_id'] = $this->resolveActiveBranchIdForUser($request);
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()
                    ? array_merge(
                        $request->user()->toArray(),
                        [
                            'permissions' => $this->getUserPermissions($request->user()),
                            'roles' => $this->getUserRoles($request->user()),
                            'activatedPackages' => $activatedPackages,
                            'industry_type' => $industryType,
                            'textile_capabilities' => $textileCapabilities,
                        ]
                    )
                    : ['activatedPackages' => $activatedPackages, 'industry_type' => $industryType, 'textile_capabilities' => $textileCapabilities],
                'impersonating' => $request->session()->has('impersonator_id'),
                'lang' => $locale,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'packages' => (new Module())->allModules(),
            'adminAllSetting' =>   $request->user() ?  getAdminAllSetting() : getAdminAllSetting(true),
            'companyAllSetting' => $request->user() ? getCompanyAllSetting($request->user()->id) : [],
            'imageUrlPrefix' =>  getImageUrlPrefix(),
            'baseUrl' => url('/'),
            'currencies' => config('default_currency.currencies', []),
            'defaultLanguages' => $defaultLanguages,
            'is_demo' => config('app.is_demo', false),
            'branchContext' => $branchContext,
        ];
    }

    public function onException($request, $exception)
    {
        if ($exception instanceof AuthorizationException) {
            return redirect()->route('users.index')->with('error', 'Permission denied');
        }

        return parent::onException($request, $exception);
    }

    /**
     * Get user permissions (placeholder - implement based on your permission system)
     */
    private function getUserPermissions($user): array
    {
        if (method_exists($user, 'getAllPermissions')) {
            return $user->getAllPermissions()->pluck('name')->toArray();
        }
        return [];
    }

    private function getUserRoles($user): array
    {
        if (method_exists($user, 'getRoleNames')) {
            return $user->getRoleNames()->toArray();
        }
        return [];
    }

    /**
     * Get superadmin language if user lang is not set
     */
    private function getSuperAdminLang(): string
    {
        return admin_setting('defaultLanguage') ? admin_setting('defaultLanguage') : 'en';
    }

    private function isInstalled(): bool
    {
        return File::exists(storage_path('installed'));
    }

    private function detectIndustryType($user): string
    {
        if (! $user || $this->isSuperAdminUser($user)) {
            return 'standard';
        }

        $companyContextUser = $this->resolveCompanyContextUser($user);
        if (! $companyContextUser) {
            return 'standard';
        }

        if ($this->hasTenantTextileModule($companyContextUser->id)) {
            return 'textile';
        }

        return 'standard';
    }

    private function filterActivatedPackagesForIndustry($user, array $activatedPackages, string $industryType): array
    {
        if (! $user || $this->isSuperAdminUser($user) || $industryType !== 'textile') {
            return $activatedPackages;
        }

        $textilePackages = array_values(array_filter($activatedPackages, function ($moduleName) {
            return stripos((string) $moduleName, 'textile') !== false;
        }));

        $companyContextUser = $this->resolveCompanyContextUser($user);
        if ($companyContextUser) {
            $tenantAssignedTextileModules = $this->tenantAssignedTextileModules($companyContextUser->id);
            $textilePackages = array_values(array_unique(array_merge($textilePackages, $tenantAssignedTextileModules)));
        }

        return $textilePackages;
    }

    private function resolveCompanyContextUser($user): ?User
    {
        if (! $user) {
            return null;
        }

        if ($user->type === 'company') {
            return $user;
        }

        if (empty($user->created_by)) {
            return null;
        }

        return User::find($user->created_by);
    }

    private function isSuperAdminUser($user): bool
    {
        return $user->type === 'superadmin' || (method_exists($user, 'hasRole') && $user->hasRole('superadmin'));
    }

    private function canManageAllBranches($user): bool
    {
        return in_array($user->type, ['company', 'superadmin'], true)
            || (method_exists($user, 'can') && $user->can('manage-any-branches'));
    }

    private function resolveActiveBranchIdForUser(Request $request): ?int
    {
        $user = $request->user();
        if (! $user || ! Schema::hasTable('branches')) {
            return null;
        }

        $tenantId = (int) creatorId();
        $isTenantRoot = in_array($user->type, ['company', 'superadmin'], true);
        $assignedBranchIds = $isTenantRoot
            ? []
            : \DigitalFuzed\TextileCore\Services\TextileUserBranchService::branchIdsForUser($user->id, $tenantId);

        // Users with explicit branch assignments are scoped to those branches
        // (takes precedence over the manage-any-branches permission):
        // single assignment -> that branch; multiple -> session choice or first.
        // The resolved branch is persisted to the session so downstream readers
        // (warehouse pickers, warehouse scopes, ...) stay scoped consistently.
        if (count($assignedBranchIds) > 0) {
            if (count($assignedBranchIds) === 1) {
                $request->session()->put('active_branch_id', $assignedBranchIds[0]);
                return $assignedBranchIds[0];
            }

            $activeBranchId = $request->session()->get('active_branch_id');
            if (is_numeric($activeBranchId) && in_array((int) $activeBranchId, $assignedBranchIds, true)) {
                return (int) $activeBranchId;
            }

            $request->session()->put('active_branch_id', $assignedBranchIds[0]);
            return $assignedBranchIds[0];
        }

        if ($this->canManageAllBranches($user)) {
            $activeBranchId = $request->session()->get('active_branch_id');
            if (! is_numeric($activeBranchId)) {
                $activeBranchId = null;
            }

            if ($activeBranchId !== null) {
                $exists = DB::table('branches')
                    ->where('created_by', creatorId())
                    ->where('id', (int) $activeBranchId)
                    ->exists();

                if (! $exists) {
                    $activeBranchId = null;
                }
            }

            // Company accounts (and any non-superadmin user who can manage all
            // branches) always operate inside a specific branch: when no branch
            // has been chosen yet, default to the tenant's first branch so
            // document creation works out of the box (no "All Branches" mode).
            // Superadmin keeps the "All Branches" view.
            if ($activeBranchId === null && $user->type !== 'superadmin') {
                $activeBranchId = DB::table('branches')
                    ->where('created_by', creatorId())
                    ->orderBy('branch_name')
                    ->value('id');

                if ($activeBranchId !== null) {
                    $request->session()->put('active_branch_id', (int) $activeBranchId);
                }
            }

            return $activeBranchId !== null ? (int) $activeBranchId : null;
        }

        if (! Schema::hasTable('employees')) {
            return null;
        }

        $branchId = DB::table('employees')
            ->where('user_id', $user->id)
            ->value('branch_id');

        return $branchId ? (int) $branchId : null;
    }

    /**
     * Branch options limited to the user's assigned branches.
     */
    private function assignedBranchOptions(int $tenantId, array $assignedBranchIds): array
    {
        if (empty($assignedBranchIds) || $tenantId <= 0 || ! Schema::hasTable('branches')) {
            return [];
        }

        return DB::table('branches')
            ->where('created_by', $tenantId)
            ->whereIn('id', $assignedBranchIds)
            ->orderBy('branch_name')
            ->get(['id', 'branch_name'])
            ->map(fn($branch) => [
                'id' => (int) $branch->id,
                'name' => $branch->branch_name,
            ])
            ->values()
            ->all();
    }

    private function branchOptions(int $tenantId): array
    {
        if ($tenantId <= 0 || ! Schema::hasTable('branches')) {
            return [];
        }

        return DB::table('branches')
            ->where('created_by', $tenantId)
            ->orderBy('branch_name')
            ->get(['id', 'branch_name'])
            ->map(fn($branch) => [
                'id' => (int) $branch->id,
                'name' => $branch->branch_name,
            ])
            ->values()
            ->all();
    }

    private function hasTenantTextileModule(int $companyUserId): bool
    {
        return \App\Models\UserActiveModule::query()
            ->where('user_id', $companyUserId)
            ->where(function ($query) {
                $query->where('module', 'like', '%Textile%')
                    ->orWhere('module', 'like', '%textile%');
            })
            ->exists();
    }

    private function tenantAssignedTextileModules(int $companyUserId): array
    {
        return \App\Models\UserActiveModule::query()
            ->where('user_id', $companyUserId)
            ->where(function ($query) {
                $query->where('module', 'like', '%Textile%')
                    ->orWhere('module', 'like', '%textile%');
            })
            ->pluck('module')
            ->toArray();
    }

    private function resolveTextileCapabilitiesForUser($user, string $industryType): array
    {
        if (! $user || $industryType !== 'textile') {
            return [];
        }

        $companyContextUser = $this->resolveCompanyContextUser($user);
        if (! $companyContextUser) {
            return [];
        }

        try {
            /** @var TextileOperatingPolicyService $policyService */
            $policyService = app(TextileOperatingPolicyService::class);

                return $policyService->capabilitiesForUser($user);
        } catch (Throwable $exception) {
            // Fail open for menu rendering if policy storage is not migrated yet.
            return [];
        }
    }
}