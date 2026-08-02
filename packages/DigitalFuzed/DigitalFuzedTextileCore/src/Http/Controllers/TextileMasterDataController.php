<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileQualityProfile;
use DigitalFuzed\TextileCore\Models\TextileReferenceMaster;
use DigitalFuzed\TextileCore\Models\TextileRouteRecipe;
use DigitalFuzed\TextileCore\Models\TextileUnitConversion;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TextileMasterDataController extends Controller
{
    private const SOURCE_TYPE_MASTER = 'source_type';
    private const MACHINE_TYPE_MASTER = 'machine_type';

    public function qualityProfiles()
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/Masters/Index', [
            'master' => 'quality-profiles',
            'records' => TextileQualityProfile::where('created_by', creatorId())->where('is_active', true)->latest()->get(),
        ]);
    }

    public function storeQualityProfile(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'grade' => 'nullable|string|max:100',
            'parameters' => 'nullable|string|max:5000',
        ]);

        TextileQualityProfile::create([...$validated, 'is_active' => true, 'created_by' => creatorId(), 'creator_id' => Auth::id()]);

        return back()->with('success', __('Textile quality profile created successfully.'));
    }

    public function updateQualityProfile(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'record_id' => 'required|integer|min:1',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'grade' => 'nullable|string|max:100',
            'parameters' => 'nullable|string|max:5000',
        ]);

        $record = TextileQualityProfile::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['record_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->update([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'grade' => $validated['grade'] ?? null,
            'parameters' => $validated['parameters'] ?? null,
        ]);

        return back()->with('success', __('Textile quality profile updated successfully.'));
    }

    public function archiveQualityProfile(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'record_id' => 'required|integer|min:1',
        ]);

        $record = TextileQualityProfile::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['record_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->is_active = false;
        $record->save();

        return back()->with('success', __('Textile quality profile deactivated successfully.'));
    }

    public function routeRecipes()
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/Masters/Index', [
            'master' => 'route-recipes',
            'records' => TextileRouteRecipe::where('created_by', creatorId())->where('is_active', true)->latest()->get(),
        ]);
    }

    public function storeRouteRecipe(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'steps' => 'nullable|string|max:5000',
        ]);
        $steps = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $validated['steps'] ?? ''))));

        TextileRouteRecipe::create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'steps' => $steps,
            'is_active' => true,
            'created_by' => creatorId(),
            'creator_id' => Auth::id(),
        ]);

        return back()->with('success', __('Textile route recipe created successfully.'));
    }

    public function updateRouteRecipe(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'record_id' => 'required|integer|min:1',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'steps' => 'nullable|string|max:5000',
        ]);

        $record = TextileRouteRecipe::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['record_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $steps = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $validated['steps'] ?? ''))));

        $record->update([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'steps' => $steps,
        ]);

        return back()->with('success', __('Textile route recipe updated successfully.'));
    }

    public function archiveRouteRecipe(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'record_id' => 'required|integer|min:1',
        ]);

        $record = TextileRouteRecipe::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['record_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->is_active = false;
        $record->save();

        return back()->with('success', __('Textile route recipe deactivated successfully.'));
    }

    public function unitConversions()
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/Masters/Index', [
            'master' => 'unit-conversions',
            'records' => TextileUnitConversion::where('created_by', creatorId())->where('is_active', true)->latest()->get(),
        ]);
    }

    public function sourceTypes()
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/Masters/Index', [
            'master' => 'source-types',
            'records' => $this->referenceMasterRecords(self::SOURCE_TYPE_MASTER),
        ]);
    }

    public function storeSourceType(Request $request)
    {
        return $this->storeReferenceMaster($request, self::SOURCE_TYPE_MASTER, 'Textile source type created successfully.');
    }

    public function updateSourceType(Request $request)
    {
        return $this->updateReferenceMaster($request, self::SOURCE_TYPE_MASTER, 'Textile source type updated successfully.');
    }

    public function archiveSourceType(Request $request)
    {
        return $this->archiveReferenceMaster($request, self::SOURCE_TYPE_MASTER, 'Textile source type deactivated successfully.');
    }

    public function machineTypes()
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/Masters/Index', [
            'master' => 'machine-types',
            'records' => $this->referenceMasterRecords(self::MACHINE_TYPE_MASTER),
        ]);
    }

    public function storeMachineType(Request $request)
    {
        return $this->storeReferenceMaster($request, self::MACHINE_TYPE_MASTER, 'Textile machine type created successfully.');
    }

    public function updateMachineType(Request $request)
    {
        return $this->updateReferenceMaster($request, self::MACHINE_TYPE_MASTER, 'Textile machine type updated successfully.');
    }

    public function archiveMachineType(Request $request)
    {
        return $this->archiveReferenceMaster($request, self::MACHINE_TYPE_MASTER, 'Textile machine type deactivated successfully.');
    }

    public function storeUnitConversion(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'from_unit' => 'required|string|max:50',
            'to_unit' => 'required|string|max:50',
            'factor' => 'required|numeric|gt:0',
        ]);

        TextileUnitConversion::create([...$validated, 'is_active' => true, 'created_by' => creatorId(), 'creator_id' => Auth::id()]);

        return back()->with('success', __('Textile unit conversion created successfully.'));
    }

    public function updateUnitConversion(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'record_id' => 'required|integer|min:1',
            'from_unit' => 'required|string|max:50',
            'to_unit' => 'required|string|max:50',
            'factor' => 'required|numeric|gt:0',
        ]);

        $record = TextileUnitConversion::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['record_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->update([
            'from_unit' => $validated['from_unit'],
            'to_unit' => $validated['to_unit'],
            'factor' => $validated['factor'],
        ]);

        return back()->with('success', __('Textile unit conversion updated successfully.'));
    }

    public function archiveUnitConversion(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'record_id' => 'required|integer|min:1',
        ]);

        $record = TextileUnitConversion::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['record_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->is_active = false;
        $record->save();

        return back()->with('success', __('Textile unit conversion deactivated successfully.'));
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin'], true), 403);
    }

    private function referenceMasterRecords(string $masterType)
    {
        return TextileReferenceMaster::query()
            ->type($masterType)
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->latest()
            ->get();
    }

    private function storeReferenceMaster(Request $request, string $masterType, string $successMessage)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        TextileReferenceMaster::create([
            'master_type' => $masterType,
            'name' => trim($validated['name']),
            'code' => isset($validated['code']) ? trim((string) $validated['code']) : null,
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
            'is_active' => true,
            'created_by' => creatorId(),
            'creator_id' => Auth::id(),
        ]);

        return back()->with('success', __($successMessage));
    }

    private function updateReferenceMaster(Request $request, string $masterType, string $successMessage)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'record_id' => 'required|integer|min:1',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $record = TextileReferenceMaster::query()
            ->type($masterType)
            ->where('created_by', creatorId())
            ->where('id', $validated['record_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->update([
            'name' => trim($validated['name']),
            'code' => isset($validated['code']) ? trim((string) $validated['code']) : null,
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
        ]);

        return back()->with('success', __($successMessage));
    }

    private function archiveReferenceMaster(Request $request, string $masterType, string $successMessage)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'record_id' => 'required|integer|min:1',
        ]);

        $record = TextileReferenceMaster::query()
            ->type($masterType)
            ->where('created_by', creatorId())
            ->where('id', $validated['record_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->is_active = false;
        $record->save();

        return back()->with('success', __($successMessage));
    }
}