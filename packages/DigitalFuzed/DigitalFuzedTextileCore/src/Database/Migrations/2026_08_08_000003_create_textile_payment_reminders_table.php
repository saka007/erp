<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_payment_reminders', function (Blueprint $table) {
            $table->id();
            $table->string('party_type', 20); // supplier | buyer
            $table->unsignedBigInteger('party_id')->nullable()->index();
            $table->string('party_name', 255)->nullable();
            $table->string('invoice_type', 20); // purchase | sales
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->decimal('amount_due', 15, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('template_name', 150)->nullable();
            $table->dateTime('reminded_at')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('creator_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->index(['invoice_type', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_payment_reminders');
    }
};
