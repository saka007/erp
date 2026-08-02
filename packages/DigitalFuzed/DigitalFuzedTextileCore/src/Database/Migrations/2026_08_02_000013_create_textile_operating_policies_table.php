<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_operating_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->string('operating_model', 80)->default('full_package_buyer');
            $table->string('material_ownership', 30)->default('company_owned');
            $table->string('billing_mode', 30)->default('sale_value');
            $table->timestamps();

            $table->unique(['created_by'], 'textile_operating_policy_tenant_unique');
            $table->index(['created_by', 'operating_model'], 'textile_operating_policy_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_operating_policies');
    }
};
