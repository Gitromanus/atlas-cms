<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Throwable;

class ProductController extends Controller
{
    public function show(Request $request, string $shop, string $productSlug): View|Response
    {
        try {
            $product = Product::query()
                ->where('slug', $productSlug)
                ->firstOrFail();

            abort_unless($product->is_active && ! $product->is_deleted_from_1c, 404);

            $product->load(['images', 'category', 'features', 'prices']);

            $related = Product::query()
                ->active()
                ->whereKeyNot($product->id)
                ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
                ->with(['images', 'prices'])
                ->limit(4)
                ->get();

            return view('shop.product', [
                'product' => $product,
                'related' => $related,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response(
                $e::class."\n".$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n\n".$e->getTraceAsString(),
                500,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }
    }
}
