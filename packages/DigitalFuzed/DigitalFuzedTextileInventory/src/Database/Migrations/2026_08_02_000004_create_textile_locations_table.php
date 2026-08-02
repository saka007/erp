<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('location_type')->default('warehouse');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['created_by', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_locations');
    }
};
