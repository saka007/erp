<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileCostCenter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TextileCostCenterController extends Controller
{
    public function index()
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/CostCenters/Index', [
            'costCenters' => TextileCostCenter::query()
                ->where('created_by', creatorId())
                ->where('is_active', true)
                ->latest('id')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        TextileCostCenter::create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => true,
            'created_by' => creatorId(),
            'creator_id' => Auth::id(),
        ]);

        return back()->with('success', __('Cost center created successfully.'));
    }

    public function update(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'cost_center_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $record = TextileCostCenter::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['cost_center_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->update([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', __('Cost center updated successfully.'));
    }

    public function archive(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'cost_center_id' => ['required', 'integer', 'min:1'],
        ]);

        $record = TextileCostCenter::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['cost_center_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->is_active = false;
        $record->save();

        return back()->with('success', __('Cost center deactivated successfully.'));
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin'], true), 403);
    }
}
