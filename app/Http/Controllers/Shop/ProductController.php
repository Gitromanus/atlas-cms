<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\View\View;
use Throwable;

class ProductController extends Controller
{
    public function show(Product $product): View
    {
        try {
            abort_unless($product->is_active && ! $product->is_deleted_from_1c, 404);

            $product->loadMissing(['images', 'category', 'features', 'prices']);

            $related = Product::query()
                ->active()
                ->whereKeyNot($product->id)
                ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
                ->with(['images', 'prices'])
                ->limit(4)
                ->get();

            return view('shop.product', compact('product', 'related'));
        } catch (Throwable $e) {
            report($e);
            abort(500, $e->getMessage().' | '.$e->getFile().':'.$e->getLine());
        }
    }
}
