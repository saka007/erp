<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->string('lot_reference');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('reserved_quantity', 12, 2)->default(0);
            $table->string('status')->default('reserved');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['lot_reference', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_reservations');
    }
};
