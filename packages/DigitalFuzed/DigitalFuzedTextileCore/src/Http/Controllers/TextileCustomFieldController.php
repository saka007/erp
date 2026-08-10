<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileCustomField;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TextileCustomFieldController extends Controller
{
    private const FIELD_TYPES = ['text', 'number', 'date', 'textarea', 'select', 'checkbox'];

    public function index()
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/CustomFields/Index', [
            'customFields' => TextileCustomField::query()
                ->where('created_by', creatorId())
                ->where('is_active', true)
                ->orderBy('module_key')
                ->orderBy('sub_module_key')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'fieldTypes' => self::FIELD_TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'module_key' => ['required', 'string', 'max:100'],
            'sub_module_key' => ['nullable', 'string', 'max:100'],
            'field_key' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'label' => ['required', 'string', 'max:255'],
            'field_type' => ['required', 'string', 'in:'.implode(',', self::FIELD_TYPES)],
            'options_csv' => ['nullable', 'string', 'max:2000'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'help_text' => ['nullable', 'string', 'max:500'],
        ]);

        TextileCustomField::create([
            'module_key' => trim((string) $validated['module_key']),
            'sub_module_key' => trim((string) ($validated['sub_module_key'] ?? '')),
            'field_key' => trim((string) $validated['field_key']),
            'label' => trim((string) $validated['label']),
            'field_type' => $validated['field_type'],
            'options' => $this->parseOptions($validated['options_csv'] ?? null),
            'is_required' => (bool) ($validated['is_required'] ?? false),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'help_text' => isset($validated['help_text']) ? trim((string) $validated['help_text']) : null,
            'is_active' => true,
            'created_by' => creatorId(),
            'creator_id' => Auth::id(),
        ]);

        return back()->with('success', __('Custom field created successfully.'));
    }

    public function update(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'custom_field_id' => ['required', 'integer', 'min:1'],
            'module_key' => ['required', 'string', 'max:100'],
            'sub_module_key' => ['nullable', 'string', 'max:100'],
            'field_key' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'label' => ['required', 'string', 'max:255'],
            'field_type' => ['required', 'string', 'in:'.implode(',', self::FIELD_TYPES)],
            'options_csv' => ['nullable', 'string', 'max:2000'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'help_text' => ['nullable', 'string', 'max:500'],
        ]);

        $record = TextileCustomField::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['custom_field_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->update([
            'module_key' => trim((string) $validated['module_key']),
            'sub_module_key' => trim((string) ($validated['sub_module_key'] ?? '')),
            'field_key' => trim((string) $validated['field_key']),
            'label' => trim((string) $validated['label']),
            'field_type' => $validated['field_type'],
            'options' => $this->parseOptions($validated['options_csv'] ?? null),
            'is_required' => (bool) ($validated['is_required'] ?? false),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'help_text' => isset($validated['help_text']) ? trim((string) $validated['help_text']) : null,
        ]);

        return back()->with('success', __('Custom field updated successfully.'));
    }

    public function archive(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'custom_field_id' => ['required', 'integer', 'min:1'],
        ]);

        $record = TextileCustomField::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['custom_field_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->is_active = false;
        $record->save();

        return back()->with('success', __('Custom field deactivated successfully.'));
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        // Master setup is admin-only (company/superadmin) so staff cannot manage custom fields.
        abort_unless($user && in_array($user->type, ['company', 'superadmin'], true), 403);
    }

    private function parseOptions(?string $optionsCsv): ?array
    {
        if ($optionsCsv === null || trim($optionsCsv) === '') {
            return null;
        }

        $options = collect(explode(',', $optionsCsv))
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();

        return count($options) > 0 ? $options : null;
    }
}
