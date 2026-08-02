<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_workflow_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->string('document_type');
            $table->string('document_number');
            $table->string('source_reference_type')->nullable();
            $table->unsignedBigInteger('source_reference_id')->nullable();
            $table->string('source_action')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->string('party_name')->nullable();
            $table->string('lot_reference')->nullable();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->string('unit')->nullable();
            $table->string('status')->default('draft');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['created_by', 'document_number']);
            $table->unique(['created_by', 'source_reference_type', 'source_reference_id', 'source_action'], 'textile_workflow_source_unique');
            $table->unique(['created_by', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_workflow_documents');
    }
};
