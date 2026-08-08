<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('textile_workflow_documents')) {
            return;
        }

        Schema::table('textile_workflow_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('textile_workflow_documents', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('creator_id');
                $table->index(['created_by', 'branch_id'], 'textile_workflow_created_branch_idx');
                $table->index(['branch_id', 'document_type'], 'textile_workflow_branch_type_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('textile_workflow_documents') || ! Schema::hasColumn('textile_workflow_documents', 'branch_id')) {
            return;
        }

        Schema::table('textile_workflow_documents', function (Blueprint $table) {
            $table->dropIndex('textile_workflow_created_branch_idx');
            $table->dropIndex('textile_workflow_branch_type_idx');
            $table->dropColumn('branch_id');
        });
    }
};
