<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_vendor_performance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->string('period_month', 7);
            $table->unsignedInteger('rating_count')->default(0);
            $table->decimal('avg_quality_score', 4, 2)->default(0);
            $table->decimal('avg_delivery_score', 4, 2)->default(0);
            $table->decimal('avg_service_score', 4, 2)->default(0);
            $table->decimal('avg_price_score', 4, 2)->default(0);
            $table->decimal('avg_overall_score', 4, 2)->default(0);
            $table->string('remarks', 1000)->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'period_month', 'created_by'], 'account_vendor_perf_period_unique');
            $table->index(['created_by', 'period_month'], 'account_vendor_perf_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_vendor_performance_snapshots');
    }
};
