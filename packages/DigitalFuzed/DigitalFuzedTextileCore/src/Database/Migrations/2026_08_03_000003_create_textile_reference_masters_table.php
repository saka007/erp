<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('textile_reference_masters', function (Blueprint $table) {
            $table->id();
            $table->string('master_type', 100);
            $table->string('name');
            $table->string('code', 100)->nullable();
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->timestamps();

            $table->index(['master_type', 'created_by', 'is_active'], 'textile_reference_masters_scope_idx');
            $table->unique(['master_type', 'created_by', 'name'], 'textile_reference_masters_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_reference_masters');
    }
};
