<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_cycle_counts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->string('lot_reference');
            $table->decimal('expected_quantity', 12, 2)->default(0);
            $table->decimal('counted_quantity', 12, 2)->default(0);
            $table->decimal('variance_quantity', 12, 2)->default(0);
            $table->string('adjustment_direction')->nullable();
            $table->string('location')->nullable();
            $table->string('unit')->nullable();
            $table->string('status')->default('posted');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['created_by', 'lot_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_cycle_counts');
    }
};