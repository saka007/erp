<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_breakdowns', function (Blueprint $table) {
            $table->id();
            $table->string('breakdown_code')->nullable();
            $table->date('breakdown_date');
            $table->unsignedBigInteger('machine_id')->nullable();
            $table->string('machine_name')->nullable();
            $table->string('machine_type')->nullable();
            $table->text('fault_description')->nullable();
            $table->string('symptom')->nullable();
            $table->unsignedInteger('downtime_minutes')->default(0);
            $table->string('impact')->nullable();
            $table->string('status')->default('reported');
            $table->date('resolved_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'is_active']);
            $table->index('breakdown_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_breakdowns');
    }
};
