<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_approval_decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('textile_workflow_document_id');
            $table->unsignedBigInteger('textile_approval_rule_id')->nullable();
            $table->string('to_status');
            $table->string('decision')->default('approved');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'textile_workflow_document_id', 'to_status'], 'textile_approval_decisions_scope_idx');
            $table->unique(
                ['created_by', 'textile_workflow_document_id', 'to_status', 'creator_id'],
                'textile_approval_actor_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_approval_decisions');
    }
};