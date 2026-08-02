<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_commercial_source_maps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('canonical_type');
            $table->unsignedBigInteger('canonical_id');
            $table->timestamps();

            $table->unique(['created_by', 'source_type', 'source_id'], 'textile_commercial_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_commercial_source_maps');
    }
};
