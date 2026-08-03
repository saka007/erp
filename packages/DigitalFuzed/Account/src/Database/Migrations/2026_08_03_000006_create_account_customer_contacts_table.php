<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('account_customer_contacts')) {
            Schema::create('account_customer_contacts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->index();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('mobile')->nullable();
                $table->string('designation')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->boolean('is_active')->default(true);
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();
                $table->timestamps();

                $table->index(['created_by', 'customer_id', 'is_active'], 'acc_customer_contacts_scope_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_customer_contacts');
    }
};
