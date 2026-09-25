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

        $product->load([
            'images',
            'category',
            'features',
            'variants',
            'prices',
            'stocks',
        ]);

        $related = Product::query()
            ->active()
            ->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->with(['mainImage', 'features', 'variants', 'prices', 'stocks'])
            ->limit(4)
            ->get();

        return view('shop.product', compact('product', 'related'));
    }
}
