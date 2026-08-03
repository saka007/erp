<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_maintenance_spare_part_usages', function (Blueprint $table) {
            $table->id();
            $table->string('usage_code')->nullable();
            $table->date('usage_date');
            $table->string('maintenance_ref_type')->nullable();
            $table->unsignedBigInteger('maintenance_ref_id')->nullable();
            $table->string('machine_name')->nullable();
            $table->string('part_name');
            $table->string('part_code')->nullable();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'is_active']);
            $table->index('usage_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_maintenance_spare_part_usages');
    }
};
