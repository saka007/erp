<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['vendors', 'customers'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                if (!Schema::hasColumn($table->getTable(), 'credit_enabled')) {
                    $table->boolean('credit_enabled')->default(false)->after('credit_days');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['vendors', 'customers'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                if (Schema::hasColumn($table->getTable(), 'credit_enabled')) {
                    $table->dropColumn('credit_enabled');
                }
            });
        }
    }
};
