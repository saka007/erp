<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('textile_dispatch_drivers')) {
            Schema::table('textile_dispatch_drivers', function (Blueprint $table): void {
                if (!Schema::hasColumn('textile_dispatch_drivers', 'transport_vendor_id')) {
                    $table->unsignedBigInteger('transport_vendor_id')->nullable()->after('license_expiry_date');
                    $table->index(['created_by', 'transport_vendor_id'], 'textile_dispatch_drivers_vendor_idx');
                }
            });
        }

        if (Schema::hasTable('textile_dispatch_vehicles')) {
            Schema::table('textile_dispatch_vehicles', function (Blueprint $table): void {
                if (!Schema::hasColumn('textile_dispatch_vehicles', 'transport_vendor_id')) {
                    $table->unsignedBigInteger('transport_vendor_id')->nullable()->after('ownership_type');
                    $table->index(['created_by', 'transport_vendor_id'], 'textile_dispatch_vehicles_vendor_idx');
                }
            });
        }

        if (Schema::hasTable('textile_dispatch_routes')) {
            Schema::table('textile_dispatch_routes', function (Blueprint $table): void {
                if (!Schema::hasColumn('textile_dispatch_routes', 'transport_vendor_id')) {
                    $table->unsignedBigInteger('transport_vendor_id')->nullable()->after('transit_hours');
                    $table->index(['created_by', 'transport_vendor_id'], 'textile_dispatch_routes_vendor_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('textile_dispatch_drivers')) {
            Schema::table('textile_dispatch_drivers', function (Blueprint $table): void {
                if (Schema::hasColumn('textile_dispatch_drivers', 'transport_vendor_id')) {
                    $table->dropIndex('textile_dispatch_drivers_vendor_idx');
                    $table->dropColumn('transport_vendor_id');
                }
            });
        }

        if (Schema::hasTable('textile_dispatch_vehicles')) {
            Schema::table('textile_dispatch_vehicles', function (Blueprint $table): void {
                if (Schema::hasColumn('textile_dispatch_vehicles', 'transport_vendor_id')) {
                    $table->dropIndex('textile_dispatch_vehicles_vendor_idx');
                    $table->dropColumn('transport_vendor_id');
                }
            });
        }

        if (Schema::hasTable('textile_dispatch_routes')) {
            Schema::table('textile_dispatch_routes', function (Blueprint $table): void {
                if (Schema::hasColumn('textile_dispatch_routes', 'transport_vendor_id')) {
                    $table->dropIndex('textile_dispatch_routes_vendor_idx');
                    $table->dropColumn('transport_vendor_id');
                }
            });
        }
    }
};
