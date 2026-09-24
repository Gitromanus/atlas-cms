<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'path',
        'url',
        'sort_order',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Публичный URL изображения.
     */
    public function getUrlAttribute(): ?string
    {
        if ($this->attributes['url'] ?? null) {
            return $this->attributes['url'];
        }

        if ($this->path) {
            return asset('storage/products/'.$this->path);
        }

        return null;
    }
}