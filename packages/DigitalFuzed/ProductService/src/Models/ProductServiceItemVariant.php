<?php

namespace Workdo\ProductService\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductServiceItemVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'variant_type',
        'variant_label',
        'variant_value',
        'unit',
        'sku_suffix',
        'is_active',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function product()
    {
        return $this->belongsTo(ProductServiceItem::class, 'product_id');
    }
}
