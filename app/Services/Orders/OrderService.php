<?php

namespace App\Services\Orders;

use App\Mail\NewOrderToShop;
use App\Mail\OrderConfirmationToCustomer;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use App\Services\Cart\CartService;
use App\Services\Tenant\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Создание заказа из корзины покупателя.
 */
class OrderService
{
    public function __construct(protected CartService $cart) {}

    /**
     * @param  array{name: string, phone?: string, email?: string, delivery_method?: string, delivery_address?: string, payment_method?: string, comment?: string}  $data
     */
    public function create(array $data): Order
    {
        $customer = auth('customers')->user();

        if ($customer === null) {
            $customer = $this->findOrCreateCustomer($data);
        }

        $items = $this->cart->items();

        abort_if($items->isEmpty(), 422, 'Корзина пуста');

        $itemsTotal = $items->sum(fn ($item) => (float) ($item->product->price ?? 0) * $item->quantity);
        $deliveryCost = 0.0;

        $status = OrderStatus::query()->where('code', 'new')->firstOrFail();

        return DB::transaction(function () use ($data, $customer, $items, $itemsTotal, $deliveryCost, $status) {
            $tenantId = app(TenantContext::class)->id();

            $order = Order::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer?->id,
                'number' => $this->nextNumber(),
                'status_id' => $status->id,
                'ext_id' => (string) Str::uuid(),
                'items_total' => $itemsTotal,
                'delivery_cost' => $deliveryCost,
                'total' => $itemsTotal + $deliveryCost,
                'currency' => 'RUB',
                'customer_name' => $data['name'],
                'customer_phone' => $data['phone'] ?? null,
                'customer_email' => $data['email'] ?? null,
                'delivery_method' => $data['delivery_method'] ?? null,
                'delivery_address' => $data['delivery_address'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'comment' => $data['comment'] ?? null,
                'placed_at' => now(),
            ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'sku' => $item->product->sku,
                    'options' => $item->options ?? [],
                    'quantity' => $item->quantity,
                    'price' => $item->product->price ?? 0,
                    'total' => ($item->product->price ?? 0) * $item->quantity,
                    'unit' => $item->product->unit,
                ]);
            }

            $this->cart->clear();

            $order->load(['items', 'status']);
            $this->notifyAboutOrder($order);

            return $order;
        });
    }

    protected function notifyAboutOrder(Order $order): void
    {
        $tenant = app(TenantContext::class)->current();
        if ($tenant === null) {
            $tenant = $order->tenant ?? null;
        }
        if ($tenant === null) {
            return;
        }

        $tenant->refresh();

        try {
            $shopEmail = $tenant->setting('email');
            if (filled($shopEmail)) {
                Mail::to($shopEmail)->send(new NewOrderToShop($order, $tenant));
            }
        } catch (\Throwable $e) {
            Log::warning('NewOrderToShop mail failed: '.$e->getMessage());
        }

        try {
            if (filled($order->customer_email)) {
                Mail::to($order->customer_email)->send(new OrderConfirmationToCustomer($order, $tenant));
            }
        } catch (\Throwable $e) {
            Log::warning('OrderConfirmation mail failed: '.$e->getMessage());
        }
    }

    /**
     * @param  array{name?: string, phone?: string, email?: string}  $data
     */
    protected function findOrCreateCustomer(array $data): ?Customer
    {
        $email = Customer::normalizeEmail($data['email'] ?? null);
        $phone = Customer::normalizePhone($data['phone'] ?? null);

        if ($email === null && $phone === null) {
            return null;
        }

        $find = fn (): ?Customer => Customer::query()
            ->when($email !== null, fn ($query) => $query->orWhere('email', $email))
            ->when($phone !== null, fn ($query) => $query->orWhere('phone', $phone))
            ->first();

        $customer = $find();

        if ($customer !== null) {
            return $customer;
        }

        try {
            return Customer::create([
                'name' => trim((string) ($data['name'] ?? 'Покупатель')) ?: 'Покупатель',
                'email' => $email,
                'phone' => $phone,
                'password' => (string) Str::uuid(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            return $find();
        }
    }

    protected function nextNumber(): string
    {
        $tenantId = app(TenantContext::class)->id();
        $count = Order::query()->where('tenant_id', $tenantId)->count() + 1;

        return str_pad((string) $count, 6, '0', STR_PAD_LEFT);
    }
}
