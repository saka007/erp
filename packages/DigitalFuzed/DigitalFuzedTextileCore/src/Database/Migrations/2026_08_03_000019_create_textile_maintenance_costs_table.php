<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_maintenance_costs', function (Blueprint $table) {
            $table->id();
            $table->string('cost_code')->nullable();
            $table->date('cost_date');
            $table->unsignedBigInteger('machine_id')->nullable();
            $table->string('machine_name')->nullable();
            $table->string('machine_type')->nullable();
            $table->decimal('labor_cost', 12, 2)->default(0);
            $table->decimal('parts_cost', 12, 2)->default(0);
            $table->decimal('external_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'is_active']);
            $table->index('cost_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_maintenance_costs');
    }
};
