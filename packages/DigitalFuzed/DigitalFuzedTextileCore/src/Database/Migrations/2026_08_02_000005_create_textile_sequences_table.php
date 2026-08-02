<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('sequence_key');
            $table->string('prefix');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['created_by', 'sequence_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_sequences');
    }
};
