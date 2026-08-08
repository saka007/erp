<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Branch-restricted parties (vendors + customers).
 *
 * A party with NO assignments is visible in ALL branches (default/global).
 * A party WITH assignments is visible ONLY in the assigned branches.
 * Admins bulk-assign / bulk-remove parties to/from branches.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('textile_party_branch_assignments')) {
            return;
        }

        Schema::create('textile_party_branch_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('party_type', 20)->default('vendor'); // vendor | customer
            $table->unsignedBigInteger('party_id');
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('creator_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->index();
            $table->timestamps();

            $table->unique(['party_type', 'party_id', 'branch_id'], 'tba_party_branch_unique');
            $table->index(['created_by', 'branch_id'], 'tba_tenant_branch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_party_branch_assignments');
    }
};
