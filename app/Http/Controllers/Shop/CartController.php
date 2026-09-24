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
        ]);

        $product = Product::query()->active()->findOrFail($validated['product_id']);
        $this->cart->add($product, $validated['quantity'] ?? 1);

        return back()->with('status', 'Товар добавлен в корзину');
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