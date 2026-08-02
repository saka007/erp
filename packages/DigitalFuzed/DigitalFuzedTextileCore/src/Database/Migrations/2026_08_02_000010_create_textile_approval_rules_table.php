<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_approval_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->string('document_type')->nullable();
            $table->string('from_status');
            $table->string('to_status');
            $table->decimal('min_quantity', 12, 2)->nullable();
            $table->decimal('max_quantity', 12, 2)->nullable();
            $table->unsignedInteger('required_approvals')->default(1);
            $table->boolean('is_active')->default(true);
            $table->json('conditions')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'document_type', 'from_status', 'to_status'], 'textile_approval_rules_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_approval_rules');
    }
};