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
        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();

        $products = Product::query()
            ->active()
            ->with(['mainImage', 'category'])
            ->when($category !== null, fn ($q) => $q->where('category_id', $category->id))
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