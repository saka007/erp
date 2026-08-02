<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('textile_lots', function (Blueprint $table) {
            $table->boolean('is_frozen')->default(false)->after('status');
            $table->text('freeze_note')->nullable()->after('is_frozen');
        });
    }

    public function down(): void
    {
        Schema::table('textile_lots', function (Blueprint $table) {
            $table->dropColumn(['is_frozen', 'freeze_note']);
        });
    }
};