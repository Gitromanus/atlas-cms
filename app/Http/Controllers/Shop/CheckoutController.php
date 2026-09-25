<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Cart\CartService;
use App\Services\Orders\OrderService;
use App\Services\Tenant\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected OrderService $orders,
    ) {}

    public function index(): View
    {
        if ($this->cart->count() === 0) {
            return redirect()->route('cart.index');
        }

        return view('shop.checkout', [
            'items' => $this->cart->items(),
            'total' => $this->cart->total(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->cart->count() === 0) {
            return redirect()->route('cart.index');
        }

        $minOrder = app(TenantContext::class)->current()?->setting('min_order_sum');
        if ($minOrder !== null && (float) $minOrder > 0 && $this->cart->total() < (float) $minOrder) {
            return redirect()
                ->route('cart.index')
                ->withErrors(['cart' => 'Минимальная сумма заказа — '.number_format((float) $minOrder, 0, ',', ' ').' ₽. Добавьте товары в корзину.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'delivery_method' => ['required', Rule::in(['pickup', 'courier', 'post'])],
            'delivery_address' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', Rule::in(['cash', 'card_online', 'card_courier'])],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = $this->orders->create($validated);

        return redirect()->route('checkout.success', $order);
    }

    public function success(Order $order): View
    {
        $order->load('items');

        return view('shop.order-success', compact('order'));
    }
}
