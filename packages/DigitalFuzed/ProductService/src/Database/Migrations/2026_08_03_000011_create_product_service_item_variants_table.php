<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_service_item_variants', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('variant_type', 50);
            $table->string('variant_label', 120);
            $table->string('variant_value', 120);
            $table->string('unit', 30)->nullable();
            $table->string('sku_suffix', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->index(['created_by', 'variant_type']);
            $table->foreign('product_id')->references('id')->on('product_service_items')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_service_item_variants');
    }
};
