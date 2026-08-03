<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_pm_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('pm_code')->nullable();
            $table->date('scheduled_date');
            $table->date('next_due_date')->nullable();
            $table->unsignedBigInteger('machine_id')->nullable();
            $table->string('machine_name')->nullable();
            $table->string('machine_type')->nullable();
            $table->string('maintenance_type')->nullable();
            $table->string('frequency_type')->default('days');
            $table->decimal('frequency_value', 10, 2)->default(0);
            $table->text('task_description')->nullable();
            $table->date('last_completed_date')->nullable();
            $table->string('status')->default('planned');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'is_active']);
            $table->index('next_due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_pm_schedules');
    }
};
