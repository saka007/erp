<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('textile_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('module_key', 100);
            $table->string('sub_module_key', 100)->default('');
            $table->string('field_key', 100);
            $table->string('label', 255);
            $table->string('field_type', 50);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('help_text', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'is_active'], 'textile_custom_fields_scope_idx');
            $table->unique(['created_by', 'module_key', 'sub_module_key', 'field_key'], 'textile_custom_fields_unique_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_custom_fields');
    }
};
