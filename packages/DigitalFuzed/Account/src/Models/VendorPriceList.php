<?php

namespace Workdo\Account\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Workdo\ProductService\Models\ProductServiceItem;

class VendorPriceList extends Model
{
    use HasFactory;

    protected $table = 'account_vendor_price_lists';

    protected $fillable = [
        'vendor_id',
        'product_service_item_id',
        'unit_price',
        'currency_code',
        'min_quantity',
        'is_active',
        'notes',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'min_quantity' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function product()
    {
        return $this->belongsTo(ProductServiceItem::class, 'product_service_item_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
