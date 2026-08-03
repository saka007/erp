<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_chemical_costs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->date('chemical_date')->nullable();
            $table->string('chemical_name');
            $table->string('process_stage', 80)->nullable();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->string('batch_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['created_by', 'is_active']);
            $table->index(['created_by', 'chemical_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_chemical_costs');
    }
};
