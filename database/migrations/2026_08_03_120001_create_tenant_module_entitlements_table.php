<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_module_entitlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('module_key', 120);
            $table->boolean('is_entitled')->default(false);
            $table->boolean('requires_approval')->default(false);
            $table->unsignedBigInteger('set_by')->nullable();
            $table->timestamp('set_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'module_key'], 'tenant_module_entitlement_unique');
            $table->index(['tenant_id', 'is_entitled'], 'tenant_module_entitlement_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_module_entitlements');
    }
};
