<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductFeature;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        return $this->render($request, null);
    }

    public function category(Request $request, Category $category): View
    {
        return $this->render($request, $category);
    }

    protected function render(Request $request, ?Category $category): View
    {
        // Дерево категорий со счётчиками товаров (включая подкатегории)
        $menuTree = Category::menuTree();
        $categories = collect($menuTree);

        // Узел текущей категории из дерева — с загруженными детьми и счётчиками
        // (для плиток подкатегорий на странице категории)
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
            ->with(['mainImage', 'category', 'features', 'variants'])
            ->when($category !== null, function ($q) use ($category) {
                // Товары категории и всех её подкатегорий
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

        // Доступные фильтры по характеристикам — из товаров текущей выборки
        // (чтобы не показывать фильтры, по которым нет товаров)
        $filterOptions = $this->featureFilters((clone $query)->pluck('id'));

        // Применяем выбранные фильтры: AND между характеристиками, OR внутри значений
        $selectedFilters = $this->normalizeFilters($request->input('f', []));

        foreach ($selectedFilters as $name => $values) {
            $query->where(function ($q) use ($name, $values, $filterOptions) {
                $isVariant = (bool) ($filterOptions[$name]['is_variant'] ?? false);

                foreach ($values as $value) {
                    if ($isVariant) {
                        // Вариантная характеристика (Цвет, Размер): значение из реальных комбинаций вариантов
                        $q->orWhereHas('variants', fn ($vq) => $vq->where('options->'.$name, $value));
                    } else {
                        // Обычная характеристика (Пол, Тип): значение свойства товара
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

    /**
     * Сортировки как в интернет-магазинах:
     * new (новинки), popular (популярность), price_asc/price_desc, name_asc/name_desc.
     */
    protected function applySorting($query, string $sort): void
    {
        switch ($sort) {
            case 'popular':
                // Популярность: суммарное количество в заказах (order_items), затем новинки
                $sales = '(SELECT COALESCE(SUM(order_items.quantity), 0) FROM order_items WHERE order_items.product_id = products.id)';
                $query->orderByRaw($sales.' DESC')->orderByDesc('id');
                break;

            case 'price_asc':
            case 'price_desc':
                // Цена: минимальная из типов цен или из вариантов (у товаров с вариантами цены только в вариантах)
                $price = '(SELECT COALESCE(
                    (SELECT MIN(price) FROM product_prices WHERE product_prices.product_id = products.id),
                    (SELECT MIN(price) FROM product_variants WHERE product_variants.product_id = products.id)
                ))';
                // Товары без цены — в конец списка
                $query->orderByRaw('(CASE WHEN '.$price.' IS NULL THEN 1 ELSE 0 END) ASC')
                    ->orderByRaw($price.' '.($sort === 'price_asc' ? 'ASC' : 'DESC'))
                    ->orderByDesc('id');
                break;

            case 'name_asc':
                $query->orderBy('name')->orderBy('id');
                break;

            case 'name_desc':
                $query->orderByDesc('name')->orderBy('id');
                break;

            case 'new':
            default:
                // Новинки — сначала последние добавленные
                $query->orderByDesc('id');
                break;
        }
    }

    /**
     * Нормализация выбранных фильтров: f[Характеристика][] = значение.
     *
     * @return array<string, array<int, string>>
     */
    protected function normalizeFilters(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $result = [];

        foreach ($input as $name => $values) {
            $values = array_values(array_filter((array) $values, fn ($value): bool => filled($value)));

            if ($values !== []) {
                $result[(string) $name] = array_map('strval', $values);
            }
        }

        return $result;
    }

    /**
     * Доступные для фильтрации характеристики и их значения по id товаров выборки.
     *
     * @param  Collection<int, int>  $productIds
     * @return array<string, array{is_variant: bool, values: array<int, string>}>
     */
    protected function featureFilters(Collection $productIds): array
    {
        if ($productIds->isEmpty()) {
            return [];
        }

        $filters = [];

        // Обычные характеристики: значение берётся из product_features.value
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

        // Вариантные характеристики: значения из реальных комбинаций вариантов
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