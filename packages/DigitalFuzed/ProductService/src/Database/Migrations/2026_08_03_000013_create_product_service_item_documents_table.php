<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_service_item_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('document_type', 80);
            $table->string('document_number', 100)->nullable();
            $table->string('document_path', 255);
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->index(['created_by', 'document_type']);
            $table->foreign('product_id')->references('id')->on('product_service_items')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_service_item_documents');
    }
};
