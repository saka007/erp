<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_fuel_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_code', 50)->nullable();
            $table->date('fuel_date');
            $table->foreignId('vehicle_id')->nullable()->constrained('textile_dispatch_vehicles')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('textile_dispatch_drivers')->nullOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('textile_dispatch_routes')->nullOnDelete();
            $table->string('vehicle_name', 255)->nullable();
            $table->string('driver_name', 255)->nullable();
            $table->string('route_name', 255)->nullable();
            $table->decimal('fuel_quantity_liters', 10, 2)->default(0);
            $table->decimal('fuel_rate_per_liter', 10, 2)->default(0);
            $table->decimal('fuel_total_cost', 10, 2)->default(0);
            $table->decimal('odometer_km', 10, 2)->nullable();
            $table->string('fuel_type', 50)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'is_active']);
            $table->index(['fuel_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_fuel_entries');
    }
};
