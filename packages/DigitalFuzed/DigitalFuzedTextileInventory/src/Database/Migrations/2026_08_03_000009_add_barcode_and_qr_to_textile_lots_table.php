<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('textile_lots', function (Blueprint $table) {
            $table->string('barcode')->nullable()->after('batch_number');
            $table->text('qr_code')->nullable()->after('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('textile_lots', function (Blueprint $table) {
            $table->dropColumn(['barcode', 'qr_code']);
        });
    }
};