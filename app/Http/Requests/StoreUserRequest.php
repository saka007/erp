<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $typeRule = auth()->user()->type === 'superadmin' ? 'nullable' : 'required|exists:roles,id';

        // Staff users must always be branch scoped. When the tenant has
        // branches, branch assignment is mandatory for users created by a
        // company admin (superadmin creates company accounts which are
        // tenant roots - they are not branch scoped).
        $isSuperAdmin = auth()->user()->type === 'superadmin';
        $branchRules = ['nullable', 'array'];
        if (! $isSuperAdmin && $this->tenantHasBranches()) {
            $branchRules = ['required', 'array', 'min:1'];
        }

        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'mobile_no' => 'nullable|string|regex:/^\+\d{1,3}\d{9,13}$/',
            'password' => 'required|confirmed|min:6',
            'type' => $typeRule,
            'is_enable_login' => 'boolean',
            'branch_ids' => $branchRules,
            'branch_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => __('Role is required.'),
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
