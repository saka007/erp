<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('textile_specifications', function (Blueprint $table) {
            $table->string('yarn_type')->nullable()->after('family');
            $table->string('yarn_count')->nullable()->after('yarn_type');
            $table->string('denier')->nullable()->after('yarn_count');
            $table->string('blend')->nullable()->after('denier');
            $table->string('mill')->nullable()->after('blend');
            $table->string('brand')->nullable()->after('mill');
            $table->string('net_weight')->nullable()->after('brand');
            $table->string('gross_weight')->nullable()->after('net_weight');
            $table->string('moisture')->nullable()->after('gross_weight');
            $table->string('quality_grade')->nullable()->after('moisture');
            $table->decimal('yarn_cost', 12, 2)->nullable()->after('quality_grade');
        });
    }

    public function down(): void
    {
        Schema::table('textile_specifications', function (Blueprint $table) {
            $table->dropColumn([
                'yarn_type',
                'yarn_count',
                'denier',
                'blend',
                'mill',
                'brand',
                'net_weight',
                'gross_weight',
                'moisture',
                'quality_grade',
                'yarn_cost',
            ]);
        });
    }
};