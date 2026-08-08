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
                if (!Schema::hasColumn($table->getTable(), 'credit_days')) {
                    $table->unsignedInteger('credit_days')->nullable()->after('payment_terms');
                }
                if (!Schema::hasColumn($table->getTable(), 'reminder_enabled')) {
                    $table->boolean('reminder_enabled')->default(true)->after('credit_days');
                }
                if (!Schema::hasColumn($table->getTable(), 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->index()->after('reminder_enabled');
                }
            });
        }

        if (Schema::hasTable('vendors') && !Schema::hasColumn('vendors', 'credit_limit')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->decimal('credit_limit', 15, 2)->nullable()->after('credit_days');
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
                foreach (['credit_days', 'reminder_enabled', 'branch_id'] as $column) {
                    if (Schema::hasColumn($table->getTable(), $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('vendors') && Schema::hasColumn('vendors', 'credit_limit')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->dropColumn('credit_limit');
            });
        }
    }
};
