<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'sku',
        'barcode',
        'name',
        'description',
        'slug',
        'unit',
        'ext_id',
        'is_active',
        'is_deleted_from_1c',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_deleted_from_1c' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(ProductFeature::class)->orderBy('sort_order');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function mainImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->orderBy('sort_order');
    }

    public function defaultPrice(): HasOne
    {
        return $this->hasOne(ProductPrice::class)->ofMany([
            'id' => 'max',
        ], function (Builder $query) {
            $query->whereHas('priceType', function (Builder $q) {
                $q->where('name', 'like', '%розн%');
            });
        });
    }

    /**
     * Суммарный остаток по всем складам.
     */
    public function stockTotal(): float
    {
        return (float) $this->stocks()->sum('quantity');
    }

    /**
     * Актуальная цена товара (наименьшая из доступных типов цен).
     */
    public function getPriceAttribute(): ?float
    {
        $price = $this->prices()->orderBy('price')->first();

        return $price !== null ? (float) $price->price : null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('is_deleted_from_1c', false);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->whereHas('stocks', fn (Builder $q) => $q->where('quantity', '>', 0));
    }
}