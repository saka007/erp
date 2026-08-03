<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('textile_dispatch_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_number');
            $table->string('code')->nullable();
            $table->string('vehicle_type', 50)->nullable();
            $table->decimal('capacity', 14, 2)->nullable();
            $table->string('capacity_unit', 50)->nullable();
            $table->string('ownership_type', 50)->nullable();
            $table->string('container_number', 100)->nullable();
            $table->string('transporter_name')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('creator_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['created_by', 'vehicle_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_dispatch_vehicles');
    }
};
