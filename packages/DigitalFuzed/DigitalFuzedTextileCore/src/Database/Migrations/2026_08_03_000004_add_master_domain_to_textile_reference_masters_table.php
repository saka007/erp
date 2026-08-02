<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return;
        }

        Schema::table('textile_reference_masters', function (Blueprint $table) {
            if (!Schema::hasColumn('textile_reference_masters', 'master_domain')) {
                $table->string('master_domain', 100)->default('global')->after('master_type');
            }
        });

        Schema::table('textile_reference_masters', function (Blueprint $table) {
            $table->dropUnique('textile_reference_masters_name_unique');
            $table->dropIndex('textile_reference_masters_scope_idx');

            $table->index(['master_type', 'master_domain', 'created_by', 'is_active'], 'textile_reference_masters_scope_idx');
            $table->unique(['master_type', 'master_domain', 'created_by', 'name'], 'textile_reference_masters_domain_name_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return;
        }

        Schema::table('textile_reference_masters', function (Blueprint $table) {
            $table->dropUnique('textile_reference_masters_domain_name_unique');
            $table->dropIndex('textile_reference_masters_scope_idx');

            $table->index(['master_type', 'created_by', 'is_active'], 'textile_reference_masters_scope_idx');
            $table->unique(['master_type', 'created_by', 'name'], 'textile_reference_masters_name_unique');

            if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
                $table->dropColumn('master_domain');
            }
        });
    }
};
