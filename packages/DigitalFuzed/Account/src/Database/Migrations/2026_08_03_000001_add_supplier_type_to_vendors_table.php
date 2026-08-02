<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vendors')) {
            return;
        }

        Schema::table('vendors', function (Blueprint $table): void {
            if (!Schema::hasColumn('vendors', 'supplier_type')) {
                $table->string('supplier_type', 30)->nullable()->after('vendor_code');
                $table->index(['created_by', 'supplier_type'], 'vendors_supplier_type_idx');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('vendors')) {
            return;
        }

        Schema::table('vendors', function (Blueprint $table): void {
            if (Schema::hasColumn('vendors', 'supplier_type')) {
                $table->dropIndex('vendors_supplier_type_idx');
                $table->dropColumn('supplier_type');
            }
        });
    }
};
