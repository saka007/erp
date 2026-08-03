<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('account_customer_categories')) {
            Schema::create('account_customer_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('code', 100)->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();
                $table->timestamps();

                $table->index(['created_by', 'is_active'], 'acc_customer_categories_scope_idx');
                $table->unique(['created_by', 'name'], 'acc_customer_categories_name_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_customer_categories');
    }
};
