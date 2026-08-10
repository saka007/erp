<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quotation type drives the item source for the quotation:
        // 'takha'   -> grey fabric takha lots (textile_lots, source_document_type=takha_entry)
        // 'yarn'    -> yarn lots (textile_lots, material_type=yarn)
        // 'general' -> regular ProductServiceItem catalog products (default)
        if (! Schema::hasColumn('sales_quotations', 'quotation_type')) {
            Schema::table('sales_quotations', function (Blueprint $table) {
                $table->string('quotation_type')->default('general')->index()->after('payment_terms');
            });
        }

        // Items can reference either a catalog product or a textile lot.
        // product_id keeps the lot id when product_type = 'lot'.
        if (! Schema::hasColumn('sales_quotation_items', 'product_type')) {
            Schema::table('sales_quotation_items', function (Blueprint $table) {
                $table->string('product_type')->default('product')->after('product_id');
            });
        }

        // Human-readable reference snapshot for lot-based items (display in
        // list/view/print without needing a join back to textile_lots).
        if (! Schema::hasColumn('sales_quotation_items', 'lot_reference')) {
            Schema::table('sales_quotation_items', function (Blueprint $table) {
                $table->string('lot_reference')->nullable()->after('product_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales_quotations', 'quotation_type')) {
            Schema::table('sales_quotations', function (Blueprint $table) {
                $table->dropColumn('quotation_type');
            });
        }

        if (Schema::hasColumn('sales_quotation_items', 'lot_reference')) {
            Schema::table('sales_quotation_items', function (Blueprint $table) {
                $table->dropColumn('lot_reference');
            });
        }

        if (Schema::hasColumn('sales_quotation_items', 'product_type')) {
            Schema::table('sales_quotation_items', function (Blueprint $table) {
                $table->dropColumn('product_type');
            });
        }
    }
};
