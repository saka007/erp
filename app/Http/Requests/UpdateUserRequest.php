<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;
        $user = $this->route('user');

        // Staff users must always be branch scoped - branch assignment is
        // mandatory when editing them and the tenant has branches. Company /
        // superadmin accounts are tenant roots and are not branch scoped.
        $isTenantRoot = in_array($user->type, ['company', 'superadmin'], true);
        $branchRules = ['nullable', 'array'];
        if (! $isTenantRoot && $this->tenantHasBranches()) {
            $branchRules = ['required', 'array', 'min:1'];
        }

        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $userId,
            'mobile_no' => 'nullable|string|regex:/^\+\d{1,3}\d{9,13}$/',
            'is_enable_login' => 'boolean',
            'branch_ids' => $branchRules,
            'branch_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'branch_ids.required' => __('At least one branch must be assigned.'),
            'branch_ids.min' => __('At least one branch must be assigned.'),
        ];
    }

    private function tenantHasBranches(): bool
    {
        if (! Schema::hasTable('branches')) {
            return false;
        }

        return DB::table('branches')
            ->where('created_by', creatorId())
            ->exists();
    }
}