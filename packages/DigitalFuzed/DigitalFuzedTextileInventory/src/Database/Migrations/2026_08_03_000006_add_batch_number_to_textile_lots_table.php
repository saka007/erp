<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('textile_lots', function (Blueprint $table) {
            $table->string('batch_number')->nullable()->after('lot_reference');
        });
    }

    public function down(): void
    {
        Schema::table('textile_lots', function (Blueprint $table) {
            $table->dropColumn('batch_number');
        });
    }
};