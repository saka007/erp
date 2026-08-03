<?php

namespace Workdo\ProductService\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductServiceItemDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'document_type',
        'document_number',
        'document_path',
        'issued_on',
        'expires_on',
        'is_active',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'issued_on' => 'date',
            'expires_on' => 'date',
        ];
    }

    public function product()
    {
        return $this->belongsTo(ProductServiceItem::class, 'product_id');
    }
}
