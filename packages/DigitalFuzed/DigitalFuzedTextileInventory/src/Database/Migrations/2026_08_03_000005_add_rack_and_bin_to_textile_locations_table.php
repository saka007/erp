<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('textile_locations', function (Blueprint $table) {
            $table->string('rack')->nullable()->after('code');
            $table->string('bin')->nullable()->after('rack');
        });
    }

    public function down(): void
    {
        Schema::table('textile_locations', function (Blueprint $table) {
            $table->dropColumn(['rack', 'bin']);
        });
    }
};