<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $products = Product::query()
            ->active()
            ->with(['images', 'category', 'features', 'variants', 'prices', 'stocks'])
            ->inStock()
            ->latest()
            ->limit(8)
            ->get();

        return view('shop.home', [
            'products' => $products,
            'categories' => collect(\App\Models\Category::menuTree()),
        ]);
    }
}
