<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_specifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('family')->nullable();
            $table->string('composition')->nullable();
            $table->string('construction')->nullable();
            $table->string('width')->nullable();
            $table->string('gsm')->nullable();
            $table->string('shade')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_specifications');
    }
};
