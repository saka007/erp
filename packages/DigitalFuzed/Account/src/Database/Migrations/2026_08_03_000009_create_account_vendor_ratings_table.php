<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_vendor_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->date('rating_date');
            $table->unsignedTinyInteger('quality_score')->default(3);
            $table->unsignedTinyInteger('delivery_score')->default(3);
            $table->unsignedTinyInteger('service_score')->default(3);
            $table->unsignedTinyInteger('price_score')->default(3);
            $table->decimal('overall_score', 4, 2)->default(3.00);
            $table->string('remarks', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'vendor_id'], 'account_vendor_ratings_scope_idx');
            $table->index(['created_by', 'is_active'], 'account_vendor_ratings_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_vendor_ratings');
    }
};
