<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers') && !Schema::hasColumn('customers', 'default_rate')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->decimal('default_rate', 15, 2)->nullable()->after('credit_limit');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'default_rate')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('default_rate');
            });
        }
    }
};
