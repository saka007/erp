<?php

namespace Workdo\Account\Models;

use Illuminate\Database\Eloquent\Model;

class VendorRating extends Model
{
    protected $guarded = [];
    protected $table = 'account_vendor_ratings';

    protected function casts(): array
    {
        return [
            'rating_date' => 'date',
            'quality_score' => 'integer',
            'delivery_score' => 'integer',
            'service_score' => 'integer',
            'price_score' => 'integer',
            'overall_score' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
