<?php

namespace DigitalFuzed\TextileInventory\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TextileLot extends Model
{
    protected $guarded = [];

    protected $casts = [
        'received_quantity' => 'decimal:2',
        'available_quantity' => 'decimal:2',
        'reserved_quantity' => 'decimal:2',
        'is_frozen' => 'boolean',
        'is_active' => 'boolean',
        'material_type' => 'string',
        'production_stage' => 'string',
        'source_document_type' => 'string',
        'source_document_id' => 'integer',
        'parent_lot_reference' => 'string',
        'parent_lot_type' => 'string',
    ];

    // ── Material type constants ──

    public const TYPE_YARN = 'yarn';
    public const TYPE_BEAM = 'beam';
    public const TYPE_GREY_FABRIC = 'grey_fabric';
    public const TYPE_FINISHED_FABRIC = 'finished_fabric';
    public const TYPE_CHEMICAL = 'chemical';
    public const TYPE_PACKING_MATERIAL = 'packing_material';
    public const TYPE_OTHER = 'other';

    public const MATERIAL_TYPES = [
        self::TYPE_YARN,
        self::TYPE_BEAM,
        self::TYPE_GREY_FABRIC,
        self::TYPE_FINISHED_FABRIC,
        self::TYPE_CHEMICAL,
        self::TYPE_PACKING_MATERIAL,
        self::TYPE_OTHER,
    ];

    // ── Production stage constants ──

    public const STAGE_PROCUREMENT = 'procurement';
    public const STAGE_SIZING = 'sizing';
    public const STAGE_WEAVING = 'weaving';
    public const STAGE_PROCESSING = 'processing';
    public const STAGE_PACKING = 'packing';
    public const STAGE_QUALITY_APPROVED = 'quality_approved';
    public const STAGE_DISPATCH = 'dispatch';

    // ── Scopes ──

    public function scopeByMaterialType(Builder $query, string $type): Builder
    {
        return $query->where('material_type', $type);
    }

    public function scopeByStage(Builder $query, string $stage): Builder
    {
        return $query->where('production_stage', $stage);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->where('is_active', true);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('created_by', $tenantId);
    }

    // ── Helpers ──

    public static function materialTypeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_YARN => 'Yarn',
            self::TYPE_BEAM => 'Beam',
            self::TYPE_GREY_FABRIC => 'Grey Fabric',
            self::TYPE_FINISHED_FABRIC => 'Finished Fabric',
            self::TYPE_CHEMICAL => 'Chemical',
            self::TYPE_PACKING_MATERIAL => 'Packing Material',
            self::TYPE_OTHER => 'Other',
            default => $type,
        };
    }

    public static function materialTypeIcon(string $type): string
    {
        return match ($type) {
            self::TYPE_YARN => '🧶',
            self::TYPE_BEAM => '📦',
            self::TYPE_GREY_FABRIC => '👕',
            self::TYPE_FINISHED_FABRIC => '✨',
            self::TYPE_CHEMICAL => '🧪',
            self::TYPE_PACKING_MATERIAL => '📦',
            default => '📋',
        };
    }
}
