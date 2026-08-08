<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class TextileRoleCapabilitySeeder extends Seeder
{
    public function run(): void
    {
        if (! class_exists(User::class)) {
            return;
        }

        User::query()
            ->where('type', 'company')
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (int $companyId) => User::MakeRole($companyId));
    }
}