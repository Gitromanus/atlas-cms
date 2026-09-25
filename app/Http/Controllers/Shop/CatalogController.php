<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductFeature;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        return $this->render($request, null);
    }

    public function category(Request $request, string $shop, string $categorySlug): View
    {
        $category = Category::query()->where('slug', $categorySlug)->firstOrFail();

        return $this->render($request, $category);
    }

    protected function render(Request $request, ?Category $category): View
    {
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

        $filterOptions = $this->featureFilters((clone $query)->pluck('id'));

        $selectedFilters = $this->normalizeFilters($request->input('f', []));

        foreach ($selectedFilters as $name => $values) {
            $query->where(function ($q) use ($name, $values, $filterOptions) {
                $isVariant = (bool) ($filterOptions[$name]['is_variant'] ?? false);

                foreach ($values as $value) {
                    if ($isVariant) {
                        $q->orWhereHas('variants', fn ($vq) => $vq->where('options->'.$name, $value));
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
            'name' => $query->orderBy('name'),
            default => $query->latest(),
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

    /** @param mixed $productIds @return array<string, array{is_variant: bool, values: list<string>}> */
    protected function featureFilters($productIds): array
    {
        $productIds = collect($productIds)->filter()->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        $filters = [];

        $features = ProductFeature::query()
            ->whereIn('product_id', $productIds)
            ->get();

        foreach ($features as $feature) {
            if ($feature->is_variant) {
                continue;
            }

            $name = (string) $feature->name;
            $value = (string) $feature->value;

            if ($value === '') {
                continue;
            }

            $filters[$name]['is_variant'] = false;
            $filters[$name]['values'][$value] = true;
        }

        $variants = ProductVariant::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('options')
            ->get();

        foreach ($variants as $variant) {
            foreach ((array) $variant->options as $name => $value) {
                if (! filled($value)) {
                    continue;
                }

                $filters[(string) $name]['is_variant'] = true;
                $filters[(string) $name]['values'][(string) $value] = true;
            }
        }

        $result = [];

        foreach ($filters as $name => $data) {
            $values = array_keys($data['values']);
            usort($values, static fn (string $a, string $b): int => mb_strtolower($a) <=> mb_strtolower($b));

            $result[$name] = [
                'is_variant' => (bool) $data['is_variant'],
                'values' => $values,
            ];
        }

        return $result;
    }
}
