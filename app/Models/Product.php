<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Tenant\TenantContext;
use App\Support\Slugger;
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

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if (blank($model->slug) && $model->name) {
                $model->slug = $model->uniqueSlug();
            }

            $model->search_name = mb_strtolower(trim(($model->name ?? '').' '.($model->sku ?? '')));
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function uniqueSlug(): string
    {
        $base = Slugger::slug($this->name) ?: 'tovar';
        $tenantId = $this->tenant_id ?? app(TenantContext::class)->id();

        $slug = $base;
        $i = 2;

        while (static::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->whereKeyNot($this->getKey())
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function features(): HasMany
    {
        return $this->hasMany(ProductFeature::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function variantFeatures(): \Illuminate\Support\Collection
    {
        return $this->features->where('is_variant', true)->values();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function hasVariants(): bool
    {
        return $this->variants()->whereNotNull('options')->exists();
    }

    public function variantQuantity(array $options): ?float
    {
        if ($options === []) {
            return null;
        }

        $normalized = $this->normalizeOptionsMap($options);

        foreach ($this->variants as $variant) {
            if ($this->normalizeOptionsMap($variant->options ?? []) == $normalized) {
                return (float) $variant->quantity;
            }
        }

        return null;
    }

    public function variantPriceRange(): ?array
    {
        $prices = $this->variants
            ->pluck('price')
            ->filter(fn ($price): bool => $price !== null)
            ->map(fn ($price): float => (float) $price)
            ->values();

        if ($prices->isEmpty()) {
            return null;
        }

        return [
            'min' => $prices->min(),
            'max' => $prices->max(),
        ];
    }

    public function variantStockTotal(): float
    {
        return (float) $this->variants->sum('quantity');
    }

    protected function normalizeOptionsMap(array $map): array
    {
        $result = [];

        foreach ($map as $key => $value) {
            if ($value !== null && $value !== '') {
                $result[(string) $key] = (string) $value;
            }
        }

        ksort($result);

        return $result;
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
        return $this->hasOne(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function stockTotal(): float
    {
        return (float) $this->stocks()->sum('quantity');
    }

    public function getPriceAttribute(): ?float
    {
        if ($this->relationLoaded('prices')) {
            $price = $this->prices->sortBy('price')->first();
        } else {
            $price = $this->prices()->orderBy('price')->first();
        }

        return $price !== null ? (float) $price->price : null;
    }

    public function isAvailable(): bool
    {
        return $this->stocks()->count() === 0 || $this->stockTotal() > 0;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('is_deleted_from_1c', false);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereDoesntHave('stocks')
                ->orWhereHas('stocks', fn (Builder $sq) => $sq->where('quantity', '>', 0));
        });
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class)->where('is_approved', true)->latest();
    }

    public function averageRating(): ?float
    {
        $avg = $this->approvedReviews()->avg('rating');

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    public function reviewsCount(): int
    {
        return (int) $this->approvedReviews()->count();
    }
}
