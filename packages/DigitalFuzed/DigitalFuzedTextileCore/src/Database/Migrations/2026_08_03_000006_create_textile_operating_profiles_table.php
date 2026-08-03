<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('textile_operating_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->string('profile_key', 80);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'is_active'], 'textile_operating_profile_active_idx');
            $table->index(['created_by', 'profile_key'], 'textile_operating_profile_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('textile_operating_profiles');
    }
};
