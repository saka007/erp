<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds branch scoping to the textile movement ledger.
 *
 * The movement ledger is the single source of truth for the manufacturing
 * pipeline (Yarn PO → Sizing → Beam Received → Beam Issued → Takha Produced
 * → Takha Sold). Every posted movement carries the branch of the operation
 * that produced it, so employees only see their branch's ledger and managers
 * can filter per branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('textile_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('textile_movements', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('creator_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('textile_movements', function (Blueprint $table) {
            if (Schema::hasColumn('textile_movements', 'branch_id')) {
                $table->dropColumn('branch_id');
            }
        });
    }
};
