<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileSpecification;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TextileSpecificationController extends Controller
{
    public function index()
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/Specifications/Index', [
            'specifications' => TextileSpecification::query()
                ->where('created_by', creatorId())
                ->where('is_active', true)
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'family' => 'nullable|string|max:100',
            'composition' => 'nullable|string|max:255',
            'construction' => 'nullable|string|max:255',
            'width' => 'nullable|string|max:100',
            'gsm' => 'nullable|string|max:100',
            'shade' => 'nullable|string|max:100',
        ]);

        TextileSpecification::create([
            ...$validated,
            'is_active' => true,
            'created_by' => creatorId(),
            'creator_id' => Auth::id(),
        ]);

        return back()->with('success', __('Textile specification created successfully.'));
    }

    public function update(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'specification_id' => 'required|integer|min:1',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'family' => 'nullable|string|max:100',
            'composition' => 'nullable|string|max:255',
            'construction' => 'nullable|string|max:255',
            'width' => 'nullable|string|max:100',
            'gsm' => 'nullable|string|max:100',
            'shade' => 'nullable|string|max:100',
        ]);

        $specification = TextileSpecification::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['specification_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $specification->update([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'family' => $validated['family'] ?? null,
            'composition' => $validated['composition'] ?? null,
            'construction' => $validated['construction'] ?? null,
            'width' => $validated['width'] ?? null,
            'gsm' => $validated['gsm'] ?? null,
            'shade' => $validated['shade'] ?? null,
        ]);

        return back()->with('success', __('Textile specification updated successfully.'));
    }

    public function archive(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'specification_id' => 'required|integer|min:1',
        ]);

        $specification = TextileSpecification::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['specification_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $specification->is_active = false;
        $specification->save();

        return back()->with('success', __('Textile specification deactivated successfully.'));
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin'], true), 403);
    }
}