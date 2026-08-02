<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->string('movement_type');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('lot_reference')->nullable();
            $table->string('location_from')->nullable();
            $table->string('location_to')->nullable();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->string('unit')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_movements');
    }
};
