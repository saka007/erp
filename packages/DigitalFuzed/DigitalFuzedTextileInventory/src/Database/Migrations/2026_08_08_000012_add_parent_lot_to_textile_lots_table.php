<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds parent-lot traceability columns to textile_lots.
 *
 * Enables the lot traceability chain:
 *   yarn lot → beam lot → grey fabric lot → takha lot → finished fabric lot
 *
 * - parent_lot_reference: lot_reference of the upstream lot this lot was
 *   produced/derived from (e.g. a beam lot's parent is the source yarn lot).
 * - parent_lot_type: material_type of the parent lot (yarn/beam/grey_fabric/...)
 *   so consumers can render the chain without a join.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('textile_lots', function (Blueprint $table) {
            if (!Schema::hasColumn('textile_lots', 'parent_lot_reference')) {
                $table->string('parent_lot_reference', 100)->nullable()->after('lot_reference');
            }
            if (!Schema::hasColumn('textile_lots', 'parent_lot_type')) {
                $table->string('parent_lot_type', 40)->nullable()->after('parent_lot_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('textile_lots', function (Blueprint $table) {
            if (Schema::hasColumn('textile_lots', 'parent_lot_reference')) {
                $table->dropColumn('parent_lot_reference');
            }
            if (Schema::hasColumn('textile_lots', 'parent_lot_type')) {
                $table->dropColumn('parent_lot_type');
            }
        });
    }
};
