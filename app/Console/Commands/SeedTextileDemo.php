<?php

namespace App\Console\Commands;

use Database\Seeders\DemoTextileFlowSeeder;
use Illuminate\Console\Command;

class SeedTextileDemo extends Command
{
    protected $signature = 'textile:demo {user? : Company user id to scope the demo data to (defaults to the first company user)}';

    protected $description = 'Seed demo data across every textile module (masters, procurement, manufacturing, quality, packing, dispatch, transport, maintenance, finance, HR, approvals).';

    public function handle(DemoTextileFlowSeeder $seeder): int
    {
        $seeder->run($this->argument('user'));

        return self::SUCCESS;
    }
}
