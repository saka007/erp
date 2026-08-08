<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class TextileSalesCustomerSeeder extends Seeder
{
    public function run(): void
    {
        $company = User::query()
            ->where('type', 'company')
            ->orderBy('id')
            ->first();

        if (! $company) {
            $this->command?->warn('No company user found for textile sales customer seeding.');
            return;
        }

        (new DemoTextileFlowSeeder())->seedCustomers((int) $company->id);

        $this->command?->info("Textile sales customers seeded for company user {$company->id}.");
    }
}
