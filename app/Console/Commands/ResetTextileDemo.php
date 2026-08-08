<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetTextileDemo extends Command
{
    protected $signature = 'textile:reset {--force : Skip confirmation prompt}';

    protected $description = 'Delete ALL textile demo/transactional data (all textile_* tables except config tables) so a fresh textile:demo can be run. Workdo data is untouched.';

    /**
     * Config tables that hold app/tenant configuration rather than demo data —
     * these are never truncated.
     */
    private const KEEP_TABLES = [
        'textile_role_capabilities',
        'textile_operating_policies',
        'textile_operating_profiles',
    ];

    public function handle(): int
    {
        $tables = $this->textileTablesToReset();

        if (empty($tables)) {
            $this->info('No textile tables found to reset.');
            return self::SUCCESS;
        }

        $counts = $this->countRows($tables);

        $this->info('The following textile tables will be TRUNCATED:');
        foreach ($counts as $table => $count) {
            $this->line(sprintf('  - %-60s %d rows', $table, $count));
        }

        if (! $this->option('force') && ! $this->confirm('This permanently deletes all textile demo/transactional data. Continue?', false)) {
            $this->warn('Aborted.');
            return self::FAILURE;
        }

        $this->withDisabledForeignKeyChecks(function () use ($tables) {
            foreach ($tables as $table) {
                DB::table($table)->truncate();
                $this->line("  truncated {$table}");
            }
        });

        $this->info('Textile data reset complete. Run `php artisan textile:demo` to seed fresh demo data.');

        return self::SUCCESS;
    }

    private function textileTablesToReset(): array
    {
        // Works on MySQL/MariaDB without Doctrine DBAL (removed in Laravel 11+).
        $rows = DB::select('SHOW TABLES');
        $key = array_key_first((array) $rows[0] ?? []);

        $tables = [];
        foreach ($rows as $row) {
            $table = (string) ($row->{$key} ?? '');
            if (str_starts_with($table, 'textile_') && ! in_array($table, self::KEEP_TABLES, true)) {
                $tables[] = $table;
            }
        }

        sort($tables);

        return $tables;
    }

    private function countRows(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table) {
            try {
                $counts[$table] = DB::table($table)->count();
            } catch (\Throwable $e) {
                $counts[$table] = -1;
            }
        }

        return $counts;
    }

    private function withDisabledForeignKeyChecks(callable $callback): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $callback();
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
