<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_service_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('schedule_code')->nullable();
            $table->date('scheduled_date');
            $table->unsignedBigInteger('pm_schedule_id')->nullable();
            $table->unsignedBigInteger('machine_id')->nullable();
            $table->string('machine_name')->nullable();
            $table->string('machine_type')->nullable();
            $table->string('technician_name')->nullable();
            $table->string('status')->default('scheduled');
            $table->text('completion_notes')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'is_active']);
            $table->index('scheduled_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_service_schedules');
    }
};
