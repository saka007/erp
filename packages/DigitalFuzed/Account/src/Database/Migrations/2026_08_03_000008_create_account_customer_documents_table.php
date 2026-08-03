<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('account_customer_documents')) {
            Schema::create('account_customer_documents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->index();
                $table->string('document_name');
                $table->string('document_type', 100);
                $table->string('document_reference')->nullable();
                $table->string('status', 30)->default('active');
                $table->date('issue_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();
                $table->timestamps();

                $table->index(['created_by', 'customer_id', 'status'], 'acc_customer_docs_scope_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_customer_documents');
    }
};
