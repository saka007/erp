<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_vehicle_maintenances', function (Blueprint $table) {
            $table->id();
            $table->string('maintenance_code', 50)->nullable();
            $table->date('maintenance_date');
            $table->date('next_due_date')->nullable();
            $table->foreignId('vehicle_id')->nullable()->constrained('textile_dispatch_vehicles')->nullOnDelete();
            $table->string('vehicle_name', 255)->nullable();
            $table->string('maintenance_type', 100)->nullable();
            $table->text('description')->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->string('service_provider', 255)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'is_active']);
            $table->index(['maintenance_date', 'next_due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_vehicle_maintenances');
    }
};
