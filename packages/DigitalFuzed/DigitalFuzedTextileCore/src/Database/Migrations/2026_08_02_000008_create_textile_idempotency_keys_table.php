<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('idempotency_key');
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id');
            $table->timestamps();

            $table->unique(['created_by', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_idempotency_keys');
    }
};
