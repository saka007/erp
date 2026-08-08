<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Events\CreateWarehouse;
use App\Events\DestroyWarehouse;
use App\Events\UpdateWarehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class WarehouseController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-warehouses')){
            $user = Auth::user();
            $canManageAllBranches = $this->canManageAllBranches($user);
            $currentBranchId = $this->currentUserBranchId($user);

            $warehouses = Warehouse::query()
                ->when(
                    Schema::hasTable('branches'),
                    fn($q) => $q
                        ->leftJoin('branches', 'branches.id', '=', 'warehouses.branch_id')
                        ->select('warehouses.*', 'branches.branch_name as branch_name'),
                    fn($q) => $q->select('warehouses.*')
                )
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-warehouses')) {
                        $q->where('warehouses.created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-warehouses')) {
                        $q->where('warehouses.creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(!$canManageAllBranches, function ($q) use ($currentBranchId) {
                    if ($currentBranchId === null) {
                        return $q->whereRaw('1 = 0');
                    }

                    return $q->where('warehouses.branch_id', $currentBranchId);
                })
                ->when(
                    $canManageAllBranches && Schema::hasTable('branches') && is_numeric(request('branch_id')),
                    fn($q) => $q->where('warehouses.branch_id', (int) request('branch_id')),
                    fn($q) => $q->when(
                        $canManageAllBranches && $currentBranchId !== null,
                        fn($nested) => $nested->where('warehouses.branch_id', $currentBranchId)
                    )
                )
                ->when(request('name'), fn($q) => $q->where('warehouses.name', 'like', '%' . request('name') . '%'))
                ->when(request('city'), fn($q) => $q->where('warehouses.city', 'like', '%' . request('city') . '%'))
                ->when(request('is_active') !== null && request('is_active') !== '', fn($q) => $q->where('warehouses.is_active', request('is_active')))
                ->when(request('sort'), fn($q) => $q->orderBy('warehouses.' . request('sort'), request('direction', 'asc')), fn($q) => $q->latest('warehouses.created_at'))
                ->paginate(request('per_page', 10))
                ->withQueryString();

            return Inertia::render('warehouses/index', [
                'warehouses' => $warehouses,
                'branches' => $this->branchOptions(creatorId()),
                'canManageAllBranches' => $canManageAllBranches,
                'currentBranchId' => $currentBranchId,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StoreWarehouseRequest $request)
    {
        if(Auth::user()->can('create-warehouses')){
            $user = Auth::user();
            $validated = $request->validated();
            $validated['is_active'] = $request->boolean('is_active', true);

            $canManageAllBranches = $this->canManageAllBranches($user);
            $currentBranchId = $this->currentUserBranchId($user);

            if (!$canManageAllBranches) {
                if ($currentBranchId === null) {
                    return redirect()->route('warehouses.index')->with('error', __('Please assign a branch to this user before creating warehouse records.'));
                }

                $validated['branch_id'] = $currentBranchId;
            }

            $warehouse = new Warehouse();
            $warehouse->name = $validated['name'];
            $warehouse->address = $validated['address'];
            $warehouse->city = $validated['city'];
            $warehouse->zip_code = $validated['zip_code'];
            $warehouse->phone = $validated['phone'];
            $warehouse->email = $validated['email'];
            $warehouse->branch_id = $validated['branch_id'] ?? null;
            $warehouse->is_active = $validated['is_active'];
            $warehouse->creator_id = Auth::id();
            $warehouse->created_by = creatorId();
            $warehouse->save();

            // Dispatch event for packages to handle their fields
            CreateWarehouse::dispatch($request, $warehouse);

            return redirect()->route('warehouses.index')->with('success', __('The warehouse has been created successfully.'));
        }
        else{
            return redirect()->route('warehouses.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse)
    {
        if(Auth::user()->can('edit-warehouses')){
            $user = Auth::user();
            $validated = $request->validated();
            $validated['is_active'] = $request->boolean('is_active', true);

            $canManageAllBranches = $this->canManageAllBranches($user);
            $currentBranchId = $this->currentUserBranchId($user);

            if (!$canManageAllBranches) {
                if ($currentBranchId === null) {
                    return redirect()->route('warehouses.index')->with('error', __('Please assign a branch to this user before editing warehouse records.'));
                }

                $validated['branch_id'] = $currentBranchId;
            }

            $warehouse->name = $validated['name'];
            $warehouse->address = $validated['address'];
            $warehouse->city = $validated['city'];
            $warehouse->zip_code = $validated['zip_code'];
            $warehouse->phone = $validated['phone'];
            $warehouse->email = $validated['email'];
            $warehouse->branch_id = $validated['branch_id'] ?? null;
            $warehouse->is_active = $validated['is_active'];
            $warehouse->save();

            // Dispatch event for packages to handle their fields
            UpdateWarehouse::dispatch($request, $warehouse);

            return back()->with('success', __('The warehouse details are updated successfully.'));
        }
        else{
            return redirect()->route('warehouses.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(Warehouse $warehouse)
    {
        if(Auth::user()->can('delete-warehouses')){
            DestroyWarehouse::dispatch($warehouse);

            $warehouse->delete();

            return back()->with('success', __('The warehouse has been deleted.'));
        }
        else{
            return redirect()->route('warehouses.index')->with('error', __('Permission denied'));
        }
    }

    private function canManageAllBranches($user): bool
    {
        return in_array($user->type, ['company', 'superadmin'], true)
            || $user->can('manage-any-branches');
    }

    private function currentUserBranchId($user): ?int
    {
        if ($this->canManageAllBranches($user) && Schema::hasTable('branches')) {
            $activeBranchId = session('active_branch_id');
            if (is_numeric($activeBranchId)) {
                $exists = DB::table('branches')
                    ->where('created_by', creatorId())
                    ->where('id', (int) $activeBranchId)
                    ->exists();

                return $exists ? (int) $activeBranchId : null;
            }
        }

        if (!Schema::hasTable('employees')) {
            return null;
        }

        $branchId = DB::table('employees')
            ->where('user_id', $user->id)
            ->value('branch_id');

        return $branchId ? (int) $branchId : null;
    }

    private function branchOptions(int $tenantId): array
    {
        if (!Schema::hasTable('branches')) {
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
}
