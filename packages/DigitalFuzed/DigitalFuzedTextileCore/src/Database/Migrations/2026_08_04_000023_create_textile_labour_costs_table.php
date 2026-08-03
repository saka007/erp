<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_labour_costs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->date('labour_date')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->string('cost_center_name')->nullable();
            $table->string('shift_name', 50)->nullable();
            $table->unsignedInteger('worker_count')->default(1);
            $table->decimal('hours_worked', 8, 2)->default(0);
            $table->decimal('rate_per_hour', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['created_by', 'is_active']);
            $table->index(['created_by', 'labour_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_labour_costs');
    }
};
