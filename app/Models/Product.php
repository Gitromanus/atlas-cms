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
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Уникальный slug в пределах магазина (кириллица → латиница).
     */
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
        return $this->hasMany(ProductFeature::class)->orderBy('sort_order');
    }

    /**
     * Вариантные свойства товара (цвет, размер и т.п.) — участвуют в выборе на витрине.
     *
     * @return \Illuminate\Support\Collection<int, ProductFeature>
     */
    public function variantFeatures(): \Illuminate\Support\Collection
    {
        return $this->features->where('is_variant', true)->values();
    }

    /**
     * Реальные варианты из 1С: комбинации характеристик с остатками.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Есть ли у товара варианты с характеристиками.
     */
    public function hasVariants(): bool
    {
        return $this->variants()->whereNotNull('options')->exists();
    }

    /**
     * Остаток конкретного варианта (комбинации характеристик) или null, если такого варианта нет.
     *
     * @param  array<string, string>  $options
     */
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

    /**
     * Нормализация карты «имя свойства → значение» для сравнения без учёта порядка ключей.
     */
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

    /**
     * Товар доступен к заказу: нет записей остатков (значит остаток не ведётся)
     * или суммарный остаток положительный.
     */
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
}