<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
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
        $categories = collect(Category::menuTree());

        $products = Product::query()
            ->active()
            ->with(['mainImage', 'category', 'features', 'variants'])
            ->when($category !== null, function ($q) use ($category) {
                // Товары категории и всех её подкатегорий
                $q->whereIn('category_id', $category->descendantIds());
            })
            ->when($request->has('q'), function ($q) use ($request) {
                $q->where(function ($query) use ($request) {
                    $search = $request->string('q')->trim()->toString();
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('shop.catalog', [
            'categories' => $categories,
            'products' => $products,
            'category' => $category,
        ]);
    }
}