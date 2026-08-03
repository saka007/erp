<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_freight_costs', function (Blueprint $table) {
            $table->id();
            $table->string('cost_code', 50)->nullable();
            $table->date('freight_date');
            $table->foreignId('vehicle_id')->nullable()->constrained('textile_dispatch_vehicles')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('textile_dispatch_drivers')->nullOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('textile_dispatch_routes')->nullOnDelete();
            $table->string('vehicle_name', 255)->nullable();
            $table->string('driver_name', 255)->nullable();
            $table->string('route_name', 255)->nullable();
            $table->unsignedBigInteger('transport_vendor_id')->nullable();
            $table->string('transport_vendor_name', 255)->nullable();
            $table->string('freight_type', 50)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'is_active']);
            $table->index(['freight_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_freight_costs');
    }
};
