<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_dispatch_routes', function (Blueprint $table): void {
            $table->id();
            $table->string('route_name');
            $table->string('route_code', 50)->nullable();
            $table->string('origin_location')->nullable();
            $table->string('destination_location')->nullable();
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->decimal('transit_hours', 10, 2)->nullable();
            $table->string('transporter_name')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_dispatch_routes');
    }
};
