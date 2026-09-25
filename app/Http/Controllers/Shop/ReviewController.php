<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductReview;
use App\Services\Tenant\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, string $shop, string $productSlug): RedirectResponse
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless((bool) $tenant?->setting('enable_reviews', true), 404);

        $product = Product::query()->where('slug', $productSlug)->firstOrFail();
        abort_unless($product->is_active && ! $product->is_deleted_from_1c, 404);

        $data = $request->validate([
            'author_name' => ['required', 'string', 'max:120'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        ProductReview::create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'customer_id' => auth('customers')->id(),
            'author_name' => $data['author_name'],
            'rating' => (int) $data['rating'],
            'body' => $data['body'] ?? null,
            'is_approved' => false,
        ]);

        return redirect()
            ->route('product.show', ['productSlug' => $product->slug])
            ->with('status', 'Спасибо! Отзыв отправлен на модерацию.');
    }
}
