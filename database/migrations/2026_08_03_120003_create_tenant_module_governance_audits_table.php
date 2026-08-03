<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_module_governance_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('action', 80);
            $table->string('module_key', 120);
            $table->json('old_payload')->nullable();
            $table->json('new_payload')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('change_reason', 500)->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'action'], 'tenant_module_audit_scope_idx');
            $table->index(['module_key', 'changed_at'], 'tenant_module_audit_module_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_module_governance_audits');
    }
};
