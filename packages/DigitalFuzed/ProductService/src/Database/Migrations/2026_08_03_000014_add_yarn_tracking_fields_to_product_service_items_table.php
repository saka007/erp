<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_service_items', function (Blueprint $table): void {
            $table->string('cone_number', 100)->nullable()->after('images');
            $table->decimal('cone_weight', 12, 3)->nullable()->after('cone_number');
            $table->string('yarn_barcode', 140)->nullable()->after('cone_weight');
            $table->string('yarn_qr_code', 255)->nullable()->after('yarn_barcode');
        });
    }

    public function down(): void
    {
        Schema::table('product_service_items', function (Blueprint $table): void {
            $table->dropColumn(['cone_number', 'cone_weight', 'yarn_barcode', 'yarn_qr_code']);
        });
    }
};
