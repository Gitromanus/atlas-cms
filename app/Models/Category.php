<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Tenant\TenantContext;
use App\Support\Slugger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'name',
        'slug',
        'ext_id',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Уникальный slug в пределах магазина (кириллица → латиница).
     */
    public function uniqueSlug(): string
    {
        $base = Slugger::slug($this->name) ?: 'kategoriya';
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

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Дерево активных категорий магазина для меню витрины.
     *
     * Каждый узел получает products_count_total — количество товаров
     * в категории вместе со всеми подкатегориями.
     *
     * @return array<int, Category>
     */
    public static function menuTree(): array
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId === null) {
            return [];
        }

        $categories = static::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->withCount('products')
            ->orderBy('sort_order')
            ->get()
            ->keyBy('id');

        $roots = [];

        foreach ($categories as $category) {
            if ($category->parent_id !== null && $categories->has($category->parent_id)) {
                $children = $categories[$category->parent_id]->children ?? collect();
                $categories[$category->parent_id]->setRelation('children', $children->push($category));
            } else {
                $roots[] = $category;
            }
        }

        $countRecursive = function (self $node) use (&$countRecursive): int {
            $total = (int) $node->products_count;

            foreach ($node->children ?? [] as $child) {
                $total += $countRecursive($child);
            }

            $node->products_count_total = $total;

            return $total;
        };

        foreach ($roots as $root) {
            $countRecursive($root);
        }

        return $roots;
    }

    /**
     * Идентификаторы категории и всех её подкатегорий (для фильтра товаров).
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];

        $stack = $this->children()->where('is_active', true)->get();

        while ($stack->isNotEmpty()) {
            $levelIds = $stack->pluck('id')->all();
            $ids = array_merge($ids, $levelIds);

            $stack = Category::query()
                ->whereIn('parent_id', $levelIds)
                ->where('is_active', true)
                ->get();
        }

        return $ids;
    }
}