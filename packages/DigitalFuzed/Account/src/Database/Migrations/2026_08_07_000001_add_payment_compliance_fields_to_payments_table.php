<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['vendor_payments', 'customer_payments'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('payment_mode', 20)->nullable()->after('reference_number');
                $table->string('cheque_number', 50)->nullable()->after('payment_mode');
                $table->date('cheque_date')->nullable()->after('cheque_number');
                $table->string('bank_name', 100)->nullable()->after('cheque_date');
                $table->decimal('tds_rate', 5, 2)->nullable()->after('bank_name');
                $table->decimal('tds_amount', 15, 2)->nullable()->after('tds_rate');
                $table->string('tds_section', 20)->nullable()->after('tds_amount');
            });
        }
    }

    public function down(): void
    {
        foreach (['vendor_payments', 'customer_payments'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn([
                    'payment_mode',
                    'cheque_number',
                    'cheque_date',
                    'bank_name',
                    'tds_rate',
                    'tds_amount',
                    'tds_section',
                ]);
            });
        }
    }
};
