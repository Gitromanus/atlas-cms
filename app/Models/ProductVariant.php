<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Вариант товара из 1С: реальная комбинация характеристик с остатком и ценой.
 */
class ProductVariant extends Model
{
    protected $fillable = [
        'tenant_id',
        'product_id',
        'ext_id',
        'name',
        'options',
        'quantity',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'quantity' => 'float',
            'price' => 'float',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}