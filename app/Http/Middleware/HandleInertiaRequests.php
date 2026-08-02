<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cookie;
use App\Classes\Module;

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

        if ($request->user()) {
            $industryType = $this->detectIndustryType($request->user());
            $activatedPackages = $this->filterActivatedPackagesForIndustry($request->user(), $activatedPackages, $industryType);
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
                        ]
                    )
                    : ['activatedPackages' => $activatedPackages, 'industry_type' => $industryType],
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
}