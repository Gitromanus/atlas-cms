<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\Cart\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(protected CartService $cart) {}

    public function index(): View
    {
        return view('shop.cart', [
            'items' => $this->cart->items(),
            'total' => $this->cart->total(),
        ]);
    }

    public function add(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'product_id' => ['required', 'integer'],
                'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
                'options' => ['nullable'],
            ]);

            $product = Product::query()->find($validated['product_id']);
            if ($product === null) {
                return back()->withErrors(['product_id' => 'Товар не найден']);
            }

            if (! $product->is_active || $product->is_deleted_from_1c) {
                return back()->withErrors(['product_id' => 'Товар недоступен']);
            }

            $options = $this->decodeOptions($validated['options'] ?? null);

            try {
                if ($options !== [] && $product->variants()->exists()) {
                    $qty = $product->variantQuantity($options);
                    if ($qty === null) {
                        Log::info('Cart: variant not exact match', [
                            'product_id' => $product->id,
                            'options' => $options,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Cart variant check: '.$e->getMessage());
            }

            $this->cart->add($product, (int) ($validated['quantity'] ?? 1), $options);

            return back()->with('status', 'Товар добавлен в корзину');
        } catch (\Throwable $e) {
            Log::error('Cart add 500: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return back()->withErrors(['cart' => 'Не удалось добавить товар: '.$e->getMessage()]);
        }
    }

    protected function decodeOptions(mixed $options): array
    {
        if (is_array($options)) {
            return array_filter($options, fn ($v) => $v !== null && $v !== '');
        }

        if (is_string($options) && $options !== '') {
            $decoded = json_decode($options, true);
            if (is_array($decoded)) {
                return array_filter($decoded, fn ($v) => $v !== null && $v !== '');
            }
        }

        return [];
    }

    public function update(Request $request, CartItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $this->cart->setQuantity($item, $validated['quantity']);

        return back();
    }

    public function remove(CartItem $item): RedirectResponse
    {
        $this->cart->remove($item);

        return back()->with('status', 'Товар удалён из корзины');
    }
}
