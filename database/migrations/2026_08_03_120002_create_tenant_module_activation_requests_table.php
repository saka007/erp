<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_module_activation_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('module_key', 120);
            $table->string('status', 20)->default('pending');
            $table->string('request_note', 500)->nullable();
            $table->string('review_note', 500)->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'tenant_module_request_scope_idx');
            $table->index(['module_key', 'status'], 'tenant_module_request_module_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_module_activation_requests');
    }
};
