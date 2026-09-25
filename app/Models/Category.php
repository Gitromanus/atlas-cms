<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Tenant\TenantContext;
use App\Support\Slugger;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        return static::query()->where($field, $value)->first() ?? abort(404);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

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

    public static function menuTree(): array
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId === null) {
            return [];
        }

        $categories = static::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->withCount(['products' => fn ($query) => $query->active()])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $byId = $categories->keyBy('id');

        foreach ($categories as $category) {
            $category->setRelation('children', new EloquentCollection());
        }

        $roots = [];
        $attached = [];

        foreach ($categories as $category) {
            if (isset($attached[$category->id])) {
                continue;
            }

            if ($category->parent_id !== null && $byId->has($category->parent_id)) {
                $attached[$category->id] = true;
                $byId[$category->parent_id]->children->push($category);
            } else {
                $roots[] = $category;
            }
        }

        $countRecursive = function (self $node) use (&$countRecursive, &$visited): int {
            if (isset($visited[$node->id])) {
                return 0;
            }
            $visited[$node->id] = true;

            $total = (int) $node->products_count;

            foreach ($node->children as $child) {
                $total += $countRecursive($child);
            }

            $node->products_count_total = $total;

            return $total;
        };

        $visited = [];

        foreach ($roots as $root) {
            $countRecursive($root);
        }

        return $roots;
    }

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

    private static ?array $productCountsMap = null;

    private static ?int $productCountsTenantId = null;

    public function productCountWithChildren(): int
    {
        $tenantId = app(TenantContext::class)->id() ?? $this->tenant_id;

        if ($tenantId === null) {
            return (int) ($this->products_count ?? 0);
        }

        if (self::$productCountsMap === null || self::$productCountsTenantId !== $tenantId) {
            self::$productCountsTenantId = $tenantId;
            self::$productCountsMap = static::buildProductCountsMap((int) $tenantId);
        }

        return self::$productCountsMap[$this->id] ?? (int) ($this->products_count ?? 0);
    }

    protected static function buildProductCountsMap(int $tenantId): array
    {
        $categories = static::query()
            ->where('tenant_id', $tenantId)
            ->withCount('products')
            ->get();

        if ($categories->isEmpty()) {
            return [];
        }

        $byId = $categories->keyBy('id');

        foreach ($categories as $category) {
            $category->setRelation('children', new EloquentCollection());
        }

        foreach ($categories as $category) {
            if ($category->parent_id !== null && $byId->has($category->parent_id)) {
                $byId[$category->parent_id]->children->push($category);
            }
        }

        $visited = [];
        $map = [];

        $compute = function (Category $node) use (&$compute, &$visited, &$map): int {
            if (isset($visited[$node->id])) {
                return $map[$node->id] ?? 0;
            }

            $visited[$node->id] = true;

            $total = (int) $node->products_count;

            foreach ($node->children as $child) {
                $total += $compute($child);
            }

            $map[$node->id] = $total;

            return $total;
        };

        foreach ($categories as $category) {
            $compute($category);
        }

        return $map;
    }

    private static ?array $parentsMap = null;

    private static ?int $parentsMapTenantId = null;

    public function getDepthAttribute(): int
    {
        $tenantId = app(TenantContext::class)->id() ?? $this->tenant_id;

        if ($tenantId === null) {
            return 0;
        }

        if (self::$parentsMap === null || self::$parentsMapTenantId !== $tenantId) {
            self::$parentsMapTenantId = $tenantId;
            self::$parentsMap = static::query()
                ->where('tenant_id', $tenantId)
                ->pluck('parent_id', 'id')
                ->map(fn ($parentId): ?int => $parentId !== null ? (int) $parentId : null)
                ->all();
        }

        $depth = 0;
        $current = (int) $this->id;
        $seen = [];

        while (isset(self::$parentsMap[$current]) && self::$parentsMap[$current] !== null && ! isset($seen[$current])) {
            $seen[$current] = true;
            $depth++;
            $current = self::$parentsMap[$current];
        }

        return $depth;
    }
}
