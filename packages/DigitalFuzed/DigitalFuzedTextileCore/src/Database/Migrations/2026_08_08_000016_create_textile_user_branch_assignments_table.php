<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Branch-restricted users.
 *
 * A user with NO assignments keeps legacy behavior (employees.branch_id fallback
 * for staff; company/superadmin manage all branches).
 * A user WITH assignments is scoped to ONLY the assigned branches:
 *   - single assignment  -> auto-scoped, no branch switcher in header
 *   - multiple assignments -> sees the branch switcher in the header
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('textile_user_branch_assignments')) {
            return;
        }

        Schema::create('textile_user_branch_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('creator_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'branch_id'], 'uba_user_branch_unique');
            $table->index(['created_by', 'branch_id'], 'uba_tenant_branch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_user_branch_assignments');
    }
};
