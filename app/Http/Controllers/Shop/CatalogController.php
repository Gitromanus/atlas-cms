<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductFeature;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Services\Demo\DemoCatalogSeeder;
use App\Services\Tenant\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        return $this->render($request, null);
    }

    public function category(Request $request, string $categorySlug): View
    {
        $category = Category::query()->where('slug', $categorySlug)->firstOrFail();

        return $this->render($request, $category);
    }

    protected function render(Request $request, ?Category $category): View
    {
        @ini_set('memory_limit', '256M');

        $tenant = app(TenantContext::class)->current();
        if ($tenant) {
            try {
                app(DemoCatalogSeeder::class)->ensure($tenant);
            } catch (\Throwable $e) {
                Log::warning('DemoCatalogSeeder: '.$e->getMessage());
            }
        }

        $menuTree = Category::menuTree();
        $categories = collect($menuTree);

        $categoryNode = null;
        if ($category !== null) {
            $stack = collect($menuTree);
            while ($stack->isNotEmpty()) {
                $node = $stack->shift();

                if ($node->id === $category->id) {
                    $categoryNode = $node;
                    break;
                }

                $stack = $stack->concat($node->children ?? collect());
            }
        }

        $query = Product::query()
            ->active()
            ->with(['images', 'category', 'features', 'variants', 'prices', 'stocks'])
            ->when($category !== null, function ($q) use ($category) {
                $q->whereIn('category_id', $category->descendantIds());
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $search = trim((string) $request->string('q'));
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });

        try {
            $filterOptions = $this->featureFilters(clone $query);
        } catch (\Throwable $e) {
            Log::warning('catalog filters failed: '.$e->getMessage());
            $filterOptions = [];
        }

        $selectedFilters = $this->normalizeFilters($request->input('f', []));

        foreach ($selectedFilters as $name => $values) {
            $query->where(function ($q) use ($name, $values, $filterOptions) {
                $isVariant = (bool) ($filterOptions[$name]['is_variant'] ?? false);

                foreach ($values as $value) {
                    if ($isVariant) {
                        $q->orWhereHas('variants', fn ($vq) => $vq->where('options->'.$name, $value))
                            ->orWhereHas('features', fn ($fq) => $fq->where('name', $name)->where('value', $value));
                    } else {
                        $q->orWhereHas('features', fn ($fq) => $fq->where('name', $name)->where('value', $value));
                    }
                }
            });
        }

        $this->applySorting($query, (string) $request->string('sort')->toString());

        $products = $query->paginate(12)->withQueryString();

        return view('shop.catalog', [
            'categories' => $categories,
            'products' => $products,
            'category' => $category,
            'categoryNode' => $categoryNode,
            'filterOptions' => $filterOptions,
            'selectedFilters' => $selectedFilters,
            'sort' => (string) $request->string('sort')->toString(),
        ]);
    }

    protected function applySorting($query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy(
                ProductPrice::query()
                    ->select('price')
                    ->whereColumn('product_prices.product_id', 'products.id')
                    ->orderBy('price')
                    ->limit(1)
            ),
            'price_desc' => $query->orderByDesc(
                ProductPrice::query()
                    ->select('price')
                    ->whereColumn('product_prices.product_id', 'products.id')
                    ->orderBy('price')
                    ->limit(1)
            ),
            'name', 'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            'popular' => $query->orderByDesc('id'),
            'new', '' => $query->orderByDesc('id'),
            default => $query->orderByDesc('id'),
        };
    }

    /** @param mixed $raw @return array<string, list<string>> */
    protected function normalizeFilters(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];

        foreach ($raw as $name => $values) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            $list = is_array($values) ? $values : [$values];
            $list = array_values(array_filter(array_map('strval', $list), fn ($v) => $v !== ''));

            if ($list !== []) {
                $out[$name] = $list;
            }
        }

        return $out;
    }

    /**
     * @return array<string, array{is_variant: bool, type: string, values: list<array{value: string, count: int}>}>
     */
    protected function featureFilters(Builder $productQuery): array
    {
        $sub = (clone $productQuery)->select('products.id')->reorder();

        $rows = ProductFeature::query()
            ->whereIn('product_id', $sub)
            ->selectRaw('name, value, MAX(CAST(is_variant AS UNSIGNED)) as is_variant, COUNT(DISTINCT product_id) as cnt')
            ->groupBy('name', 'value')
            ->orderBy('name')
            ->orderBy('value')
            ->limit(1500)
            ->get();

        $filters = [];

        foreach ($rows as $row) {
            $name = (string) $row->name;
            $value = (string) $row->value;
            if ($name === '' || $value === '') {
                continue;
            }

            $isVariant = (bool) $row->is_variant;

            if (! isset($filters[$name])) {
                $filters[$name] = [
                    'is_variant' => $isVariant,
                    'type' => $this->detectFilterType($name),
                    'values' => [],
                ];
            } else {
                $filters[$name]['is_variant'] = $filters[$name]['is_variant'] || $isVariant;
            }

            $filters[$name]['values'][] = [
                'value' => $value,
                'count' => (int) $row->cnt,
            ];
        }

        $variantRows = ProductVariant::query()
            ->whereIn('product_id', $sub)
            ->whereNotNull('options')
            ->limit(2000)
            ->get(['product_id', 'options']);

        $variantCounts = [];
        foreach ($variantRows as $variant) {
            foreach ((array) $variant->options as $name => $value) {
                if (! filled($value)) {
                    continue;
                }
                $variantCounts[(string) $name][(string) $value][$variant->product_id] = true;
            }
        }

        foreach ($variantCounts as $name => $values) {
            if (! isset($filters[$name])) {
                $filters[$name] = [
                    'is_variant' => true,
                    'type' => $this->detectFilterType($name),
                    'values' => [],
                ];
            } else {
                $filters[$name]['is_variant'] = true;
            }

            $existing = [];
            foreach ($filters[$name]['values'] as $i => $v) {
                $existing[$v['value']] = $i;
            }

            foreach ($values as $value => $productIds) {
                $cnt = count($productIds);
                if (isset($existing[$value])) {
                    $filters[$name]['values'][$existing[$value]]['count'] = max(
                        $filters[$name]['values'][$existing[$value]]['count'],
                        $cnt
                    );
                } else {
                    $filters[$name]['values'][] = ['value' => $value, 'count' => $cnt];
                }
            }
        }

        return $filters;
    }

    protected function detectFilterType(string $name): string
    {
        $n = mb_strtolower(trim($name));

        if (str_contains($n, 'цвет') || str_contains($n, 'color') || str_contains($n, 'colour')) {
            return 'color';
        }

        if (str_contains($n, 'размер') || str_contains($n, 'size') || $n === 'р-р') {
            return 'size';
        }

        if (str_contains($n, 'бренд') || str_contains($n, 'brand') || str_contains($n, 'производител') || str_contains($n, 'марка')) {
            return 'brand';
        }

        return 'default';
    }
}
