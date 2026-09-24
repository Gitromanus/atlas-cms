<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(Product $product): View
    {
        abort_unless($product->is_active && ! $product->is_deleted_from_1c, 404);

        $related = Product::query()
            ->active()
            ->where('id', '!=', $product->id)
            ->where('category_id', $product->category_id)
            ->with('mainImage')
            ->limit(4)
            ->get();

        return view('shop.product', compact('product', 'related'));
    }
}