<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customers')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table): void {
            if (!Schema::hasColumn('customers', 'operating_model')) {
                $table->string('operating_model', 80)->nullable()->after('payment_terms');
            }

            if (!Schema::hasColumn('customers', 'material_ownership')) {
                $table->string('material_ownership', 30)->nullable()->after('operating_model');
            }

            if (!Schema::hasColumn('customers', 'billing_mode')) {
                $table->string('billing_mode', 30)->nullable()->after('material_ownership');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('customers')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table): void {
            $dropColumns = [];

            if (Schema::hasColumn('customers', 'operating_model')) {
                $dropColumns[] = 'operating_model';
            }

            if (Schema::hasColumn('customers', 'material_ownership')) {
                $dropColumns[] = 'material_ownership';
            }

            if (Schema::hasColumn('customers', 'billing_mode')) {
                $dropColumns[] = 'billing_mode';
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
