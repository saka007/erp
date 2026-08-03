<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('account_customer_follow_ups')) {
            Schema::create('account_customer_follow_ups', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_id')->index();
                $table->foreignId('customer_contact_id')->nullable()->index();
                $table->date('follow_up_date');
                $table->date('next_follow_up_date')->nullable();
                $table->string('channel', 30)->default('call');
                $table->string('status', 30)->default('pending');
                $table->text('notes')->nullable();
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();
                $table->timestamps();

                $table->index(['created_by', 'status', 'follow_up_date'], 'acc_customer_followups_scope_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_customer_follow_ups');
    }
};
