<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('textile_movements', function (Blueprint $table) {
            $table->string('adjustment_direction')->nullable()->after('movement_type');
        });
    }

    public function down(): void
    {
        Schema::table('textile_movements', function (Blueprint $table) {
            $table->dropColumn('adjustment_direction');
        });
    }
};