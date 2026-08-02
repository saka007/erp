<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_lots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->string('lot_reference');
            $table->decimal('received_quantity', 12, 2)->default(0);
            $table->decimal('available_quantity', 12, 2)->default(0);
            $table->string('status')->default('active');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['created_by', 'lot_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_lots');
    }
};
