<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LoginHistory;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Events\CreateUser;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Session;
use App\Models\Plan;
use App\Models\UserActiveModule;
use App\Models\AddOn;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-users')){
            $users = User::query()
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-users')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-users')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                // The Users page is for internal organisation members (HRM side).
                // Vendor/client/doctor/student/parent users are external parties
                // managed via their own masters (Vendors, Customers, etc.).
                ->whereNotIn('type', ['client', 'vendor', 'doctor', 'student', 'parent'])
                ->when(request('name'), fn($q) => $q->where('name', 'like', '%' . request('name') . '%'))
                ->when(request('email'), fn($q) => $q->where('email', 'like', '%' . request('email') . '%'))
                ->when(request('role'), fn($q) => $q->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.role_id', request('role'))
                    ->where('model_has_roles.model_type', User::class))
                ->when(request('is_enable_login') !== null, fn($q) => $q->where('is_enable_login', request('is_enable_login')))
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), function($q) {
                    if (config('app.is_demo', false) && Auth::user()->type === 'superadmin') {
                        return $q->orderBy('id', 'asc');
                    }
                    return $q->latest();
                })
                ->select('users.*')
                ->paginate(request('per_page', 10))
                ->withQueryString();

            $companyUsers = $users->getCollection()->where('type', 'company');
            $plans = Plan::whereIn('id', $companyUsers->pluck('active_plan')->filter())
                ->get(['id', 'name', 'modules'])
                ->keyBy('id');
            $manualModules = UserActiveModule::whereIn('user_id', $companyUsers->pluck('id'))
                ->get()
                ->groupBy('user_id');
            $textileModules = ['TextileCore', 'TextileInventory'];

            $users->getCollection()->transform(function (User $user) use ($plans, $manualModules, $textileModules) {
                if ($user->type !== 'company') {
                    $user->setAttribute('branch_ids', $this->userBranchIds($user));

                    return $user;
                }

                $plan = $plans->get($user->active_plan);
                $planModules = is_array($plan?->modules) ? $plan->modules : [];
                $userModules = ($manualModules->get($user->id) ?? collect())->pluck('module')->all();
                $effectiveModules = array_unique(array_merge($planModules, $userModules));

                $user->setAttribute('active_plan_name', $plan?->name);
                $user->setAttribute('industry_type', count(array_intersect($textileModules, $effectiveModules)) > 0 ? 'textile' : 'standard');
                $user->setAttribute('branch_ids', $this->userBranchIds($user));

                return $user;
            });

            $roles = Role::where('created_by', creatorId())
                ->whereNotIn('name', ['vendor', 'client'])
                ->pluck('label', 'id');
            $branches = $this->tenantBranchOptions();

            return Inertia::render('users/index', [
                'users' => $users,
                'roles' => $roles,
                'branches' => $branches,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StoreUserRequest $request)
    {
        if(Auth::user()->can('create-users')){
            $checkUser = canCreateUser();
            if (!$checkUser['can_create']) {
                return redirect()->route('users.index')->with('error', $checkUser['message']);
            }

            $validated = $request->validated();
            $validated['is_enable_login'] = $request->boolean('is_enable_login', true);

            $role = Role::find($validated['type']);
            $enableEmailVerification = admin_setting('enableEmailVerification');

            $user = new User();
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->mobile_no = $validated['mobile_no'];
            $user->password = Hash::make($validated['password']);
            $user->type = Auth::user()->type == 'superadmin' ? 'company' : ($role->name ?? 'staff');
            $user->is_enable_login = $validated['is_enable_login'];
            $user->lang = company_setting('defaultLanguage') ?? 'en';
            $user->email_verified_at = $enableEmailVerification === 'on' ? null : now();
            $user->creator_id = Auth::id();
            $user->created_by = creatorId();
            $user->save();

            if(Auth::user()->type == 'superadmin')
            {
                User::CompanySetting($user->id);
                User::MakeRole($user->id);
                $role = Role::findByName('company');
            }

            $user->assignRole($role);

            // Persist branch assignments (only when the tenant actually has branches)
            if (! empty($this->tenantBranchOptions()) && $request->has('branch_ids')) {
                \DigitalFuzed\TextileCore\Services\TextileUserBranchService::syncBranches(
                    $user->id,
                    (array) $request->input('branch_ids', []),
                    creatorId(),
                    Auth::id()
                );
            }

            // Dispatch event for packages to handle their fields
            CreateUser::dispatch($request, $user);

             // Send welcome email
            if(company_setting('New User') == 'on') {
                $emailData = [
                    'name' => $user->name,
                    'email' => $user->email,
                    'password' => $validated['password'],
                ];

                EmailTemplate::sendEmailTemplate('New User', [$user->email], $emailData);
            }

            if ($enableEmailVerification === 'on') {
                // Apply dynamic mail configuration
                SetConfigEmail(creatorId());
                $user->sendEmailVerificationNotification();
            }

            return redirect()->route('users.index')->with('success', __('The user has been created successfully.'));
        }
        else{
            return redirect()->route('users.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        if(Auth::user()->can('edit-users')){
            $validated = $request->validated();
            $validated['is_enable_login'] = $request->boolean('is_enable_login', true);

            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->mobile_no = $validated['mobile_no'];
            $user->is_enable_login = $validated['is_enable_login'];
            $user->save();

            // Persist branch assignments (only when the tenant actually has branches)
            if (! empty($this->tenantBranchOptions()) && $request->has('branch_ids')) {
                \DigitalFuzed\TextileCore\Services\TextileUserBranchService::syncBranches(
                    $user->id,
                    (array) $request->input('branch_ids', []),
                    creatorId(),
                    Auth::id()
                );
            }

            return back()->with('success', __('The user details are updated successfully.'));
        }
        else{
            return redirect()->route('users.index')->with('error', __('Permission denied'));
        }
    }

    public function updateIndustry(Request $request, User $user)
    {
        $actor = Auth::user();

        if (! $actor || ($actor->type !== 'superadmin' && ! $actor->hasRole('superadmin'))) {
            abort(403);
        }

        if ($user->type !== 'company' || (int) $user->created_by !== (int) $actor->id) {
            abort(403);
        }

        $validated = $request->validate([
            'industry_type' => 'required|in:standard,textile',
        ]);

        $textileModules = ['TextileCore', 'TextileInventory'];

        if ($validated['industry_type'] === 'textile') {
            $this->ensureTextileAddOnsEnabled($textileModules);

            foreach ($textileModules as $module) {
                UserActiveModule::firstOrCreate([
                    'user_id' => $user->id,
                    'module' => $module,
                ]);
            }
        } else {
            UserActiveModule::where('user_id', $user->id)
                ->whereIn('module', $textileModules)
                ->delete();
        }

        return back()->with('success', __('Company industry access updated successfully.'));
    }

    private function ensureTextileAddOnsEnabled(array $textileModules): void
    {
        foreach ($textileModules as $module) {
            $packageName = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $module));

            AddOn::updateOrCreate(
                ['module' => $module],
                [
                    'name' => str_replace('Textile', 'Textile ', $module),
                    'package_name' => $packageName,
                    'monthly_price' => 0,
                    'yearly_price' => 0,
                    'is_enable' => true,
                    'for_admin' => false,
                ]
            );
        }
    }

    public function changePassword(ChangePasswordRequest $request, User $user)
    {
        if(Auth::user()->can('change-password-users') && $user->created_by == creatorId() ){
            $validated = $request->validated();
            $user->password = Hash::make($validated['password']);
            $user->save();

            return redirect()->route('users.index')->with('success', __('The password changed successfully.'));
        }
        else{
            return redirect()->route('users.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(User $user)
    {
        if(Auth::user()->can('delete-users')){
            $user->delete();

            return back()->with('success', __('The user has been deleted.'));
        }
        else{
            return redirect()->route('users.index')->with('error', __('Permission denied'));
        }
    }

    public function impersonate(User $user)
    {
        if (Auth::user()->can('impersonate-users'))
        {
            if ($user->id === Auth::id()) {
                return redirect()->route('users.index')->with('error', __('You cannot login as user yourself'));
            }

            if ($user->created_by !== creatorId()) {
                return redirect()->route('users.index')->with('error', __('Permission denied'));
            }

            // Store the original user ID in session
            Session::put('impersonator_id', Auth::id());

            // Login as the target user
            Auth::login($user);
        }
        else
        {
            return redirect()->route('users.index')->with('error', __('Permission denied'));
        }

        return redirect()->route('dashboard')->with('success', __('You are now login as user :name', ['name' => $user->name]));
    }

    public function leaveImpersonation()
    {
        if (!Session::has('impersonator_id')) {
            return redirect()->route('dashboard')->with('error', __('You are not login as user anyone'));
        }

        $originalUserId = Session::get('impersonator_id');
        $originalUser = User::find($originalUserId);

        if (!$originalUser) {
            Session::forget('impersonator_id');
            return redirect()->route('login')->with('error', __('Original user not found'));
        }

        Session::forget('impersonator_id');
        Auth::login($originalUser);

        return redirect()->route('users.index')->with('success', __('You have stopped login as user'));
    }

    public function loginHistory()
    {
        if(Auth::user()->can('view-login-history')){
            $loginHistories = LoginHistory::with('user')
                ->when(Auth::user()->type !== 'superadmin', fn($q) => $q->where('created_by', creatorId()))
                ->when(request('user_name'), fn($q) => $q->whereHas('user', fn($q) => $q->where('name', 'like', '%' . request('user_name') . '%')))
                ->when(request('ip'), fn($q) => $q->where('ip', 'like', '%' . request('ip') . '%'))
                ->when(request('role'), fn($q) => $q->whereHas('user', fn($q) => $q->where('type', request('role'))))
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 10))
                ->withQueryString();

            $roles = Role::where('created_by', creatorId())->pluck('label', 'name');

            return Inertia::render('users/login-history', [
                'loginHistories' => $loginHistories,
                'roles' => $roles,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    /**
     * Tenant branch options as [{id, name}].
     * Returns [] when the tenant has no branches (field hidden in the form).
     */
    private function tenantBranchOptions(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('branches')) {
            return [];
        }

        return \Illuminate\Support\Facades\DB::table('branches')
            ->where('created_by', creatorId())
            ->orderBy('branch_name')
            ->get(['id', 'branch_name'])
            ->map(fn ($branch) => [
                'id' => (int) $branch->id,
                'name' => $branch->branch_name,
            ])
            ->values()
            ->all();
    }

    private function userBranchIds(User $user): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('textile_user_branch_assignments')) {
            return [];
        }

        return \DigitalFuzed\TextileCore\Services\TextileUserBranchService::branchIdsForUser($user->id, creatorId());
    }
}
