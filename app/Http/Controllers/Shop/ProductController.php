<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(Request $request, string $shop, string $productSlug): View
    {
        $product = Product::query()
            ->where('slug', $productSlug)
            ->firstOrFail();

        abort_unless($product->is_active && ! $product->is_deleted_from_1c, 404);

        $product->load(['images', 'category', 'features', 'prices', 'stocks', 'variants', 'approvedReviews']);

        $related = Product::query()
            ->active()
            ->whereKeyNot($product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->with(['images', 'prices', 'stocks', 'features', 'variants'])
            ->limit(4)
            ->get();

        return view('shop.product', [
            'product' => $product,
            'related' => $related,
        ]);
    }
}
