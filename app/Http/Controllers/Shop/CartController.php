<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\Cart\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            // options приходит JSON-строкой из Alpine-пикера вариантов (см. product.blade.php)
            'options' => ['nullable'],
        ]);

        $product = Product::query()->active()->findOrFail($validated['product_id']);
        $options = $this->decodeOptions($validated['options'] ?? null);

        // Для товара с вариантами комбинация характеристик должна реально существовать в 1С
        if ($product->hasVariants() && $product->variantQuantity($options) === null) {
            return back()
                ->withErrors(['options' => 'Такого варианта товара нет в наличии'])
                ->withInput();
        }

        $this->cart->add($product, $validated['quantity'] ?? 1, $options);

        return back()->with('status', 'Товар добавлен в корзину');
    }

    /**
     * Приводит options (массив или JSON-строка из пикера вариантов) к массиву.
     */
    protected function decodeOptions(mixed $options): array
    {
        if (is_array($options)) {
            return $options;
        }

        if (is_string($options) && $options !== '') {
            $decoded = json_decode($options, true);

            if (is_array($decoded)) {
                return $decoded;
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

    public function clear(): RedirectResponse
    {
        $this->cart->clear();

        return redirect()->route('cart.index');
    }
}