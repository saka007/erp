<?php

namespace Workdo\Account\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Workdo\ProductService\Models\ProductServiceItem;

class CustomerPriceList extends Model
{
    use HasFactory;

    protected $table = 'account_customer_price_lists';

    protected $fillable = [
        'customer_id',
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

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function item()
    {
        return $this->belongsTo(ProductServiceItem::class, 'product_service_item_id');
    }
}
