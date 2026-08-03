<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_power_costs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('meter_reading_start', 14, 2)->default(0);
            $table->decimal('meter_reading_end', 14, 2)->default(0);
            $table->decimal('units_consumed', 14, 2)->default(0);
            $table->decimal('rate_per_unit', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->text('allocation_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['created_by', 'is_active']);
            $table->index(['created_by', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_power_costs');
    }
};
