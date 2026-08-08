<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('textile_lots', function (Blueprint $table) {
            if (!Schema::hasColumn('textile_lots', 'material_type')) {
                $table->string('material_type', 40)->nullable()->after('status');
            }
            if (!Schema::hasColumn('textile_lots', 'production_stage')) {
                $table->string('production_stage', 40)->nullable()->after('material_type');
            }
            if (!Schema::hasColumn('textile_lots', 'source_document_type')) {
                $table->string('source_document_type', 100)->nullable()->after('production_stage');
            }
            if (!Schema::hasColumn('textile_lots', 'source_document_id')) {
                $table->unsignedBigInteger('source_document_id')->nullable()->after('source_document_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('textile_lots', function (Blueprint $table) {
            $table->dropColumn([
                'material_type',
                'production_stage',
                'source_document_type',
                'source_document_id',
            ]);
        });
    }
};
