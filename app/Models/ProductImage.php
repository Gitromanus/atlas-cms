<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'path',
        'url',
        'source',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $image) {
            if (filled($image->path)) {
                Storage::disk('public')->delete($image->path);
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getUrlAttribute(): ?string
    {
        $external = $this->attributes['url'] ?? null;
        if (filled($external)) {
            return $external;
        }

        $path = $this->attributes['path'] ?? null;
        if (! filled($path)) {
            return null;
        }

        $relative = str_starts_with($path, 'products/') ? $path : 'products/'.$path;

        return asset('storage/'.$relative);
    }
}
