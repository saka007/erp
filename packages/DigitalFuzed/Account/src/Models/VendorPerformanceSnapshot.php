<?php

namespace Workdo\Account\Models;

use Illuminate\Database\Eloquent\Model;

class VendorPerformanceSnapshot extends Model
{
    protected $guarded = [];
    protected $table = 'account_vendor_performance_snapshots';

    protected function casts(): array
    {
        return [
            'rating_count' => 'integer',
            'avg_quality_score' => 'decimal:2',
            'avg_delivery_score' => 'decimal:2',
            'avg_service_score' => 'decimal:2',
            'avg_price_score' => 'decimal:2',
            'avg_overall_score' => 'decimal:2',
        ];
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
