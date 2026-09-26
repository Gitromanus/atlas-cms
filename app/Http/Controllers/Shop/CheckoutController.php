<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Services\Cart\CartService;
use App\Services\Delivery\YandexDeliveryService;
use App\Services\Orders\OrderService;
use App\Services\Payments\YooKassaService;
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
        protected YooKassaService $yookassa,
        protected YandexDeliveryService $yandex,
    ) {}

    public function index(): View|RedirectResponse
    {
        if ($this->cart->count() === 0) {
            return redirect()->route('cart.index');
        }

        $tenant = app(TenantContext::class)->current();
        $methods = DeliveryMethod::query()->active()->get();

        return view('shop.checkout', [
            'items' => $this->cart->items(),
            'total' => $this->cart->total(),
            'deliveryMethods' => $methods,
            'yookassaEnabled' => $this->yookassa->isConfigured($tenant),
            'yandexDeliveryEnabled' => $this->yandex->isConfigured($tenant),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->cart->count() === 0) {
            return redirect()->route('cart.index');
        }

        $minOrder = app(TenantContext::class)->current()?->setting('min_order_sum');
        if ($minOrder !== null && (float) $minOrder > 0 && $this->cart->total() < (float) $minOrder) {
            return redirect()->route('cart.index')->withErrors([
                'cart' => 'Минимальная сумма заказа — '.number_format((float) $minOrder, 0, ',', ' ').' ₽.',
            ]);
        }

        $activeIds = DeliveryMethod::query()->active()->pluck('id')->all();
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'delivery_address' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', Rule::in(['cash', 'card_online', 'card_courier'])],
            'comment' => ['nullable', 'string', 'max:2000'],
            'delivery_cost' => ['nullable', 'numeric', 'min:0'],
        ];

        if ($activeIds !== []) {
            $rules['delivery_method_id'] = ['required', Rule::in($activeIds)];
        } else {
            $rules['delivery_method'] = ['required', Rule::in(['pickup', 'courier', 'post', 'yandex'])];
        }

        $validated = $request->validate($rules);

        if (! empty($validated['delivery_method_id'])) {
            $method = DeliveryMethod::query()->find($validated['delivery_method_id']);
            if ($method?->require_address && blank($validated['delivery_address'] ?? null)) {
                return back()->withErrors(['delivery_address' => 'Укажите адрес доставки'])->withInput();
            }
        }

        $order = $this->orders->create($validated);
        $shop = app(TenantContext::class)->current()?->slug;

        if (($validated['payment_method'] ?? '') === 'card_online') {
            return redirect()->route('payment.pay', ['shop' => $shop, 'order' => $order->id]);
        }

        return redirect()->route('checkout.success', ['shop' => $shop, 'order' => $order->id]);
    }

    public function success(Request $request, string $shop, int|string $order): View
    {
        $model = Order::query()->with(['items', 'status', 'deliveryMethod'])->whereKey($order)->firstOrFail();

        return view('shop.order-success', ['order' => $model]);
    }
}
