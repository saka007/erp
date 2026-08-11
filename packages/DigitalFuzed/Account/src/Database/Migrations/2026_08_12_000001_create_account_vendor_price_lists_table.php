<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('account_vendor_price_lists')) {
            Schema::create('account_vendor_price_lists', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('vendor_id')->index();
                $table->foreignId('product_service_item_id')->index();
                $table->decimal('unit_price', 15, 2);
                $table->string('currency_code', 10)->default('USD');
                $table->decimal('min_quantity', 15, 3)->default(1);
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();
                $table->timestamps();

                $table->index(['created_by', 'is_active'], 'acc_vendor_prices_scope_idx');
                $table->unique(['created_by', 'vendor_id', 'product_service_item_id', 'currency_code'], 'acc_vendor_prices_unique_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_vendor_price_lists');
    }
};
