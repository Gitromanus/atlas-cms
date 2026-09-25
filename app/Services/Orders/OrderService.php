<?php

namespace App\Services\Orders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Services\Cart\CartService;
use App\Services\Tenant\TenantContext;
use Illuminate\Support\Facades\DB;
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

        // Покупатель, оформивший заказ без входа в личный кабинет, автоматически
        // привязывается к существующему или вновь созданному профилю (по email/телефону),
        // чтобы заказ и покупатель были видны в админке магазина.
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

            return $order->load('items');
        });
    }

    /**
     * Находит покупателя в текущем магазине по email/телефону или создаёт профиль.
     * Пароль генерируется случайно — восстановление/вход не затронут существующих пользователей.
     *
     * @param  array{name?: string, phone?: string, email?: string}  $data
     */
    protected function findOrCreateCustomer(array $data): ?Customer
    {
        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        $phone = isset($data['phone']) ? trim((string) $data['phone']) : '';

        if ($email === '' && $phone === '') {
            return null;
        }

        $customer = Customer::query()
            ->where(fn ($query) => $query
                ->when($email !== '', fn ($q) => $q->orWhere('email', $email))
                ->when($phone !== '', fn ($q) => $q->orWhere('phone', $phone)))
            ->first();

        if ($customer !== null) {
            return $customer;
        }

        return Customer::create([
            'name' => $data['name'] ?? 'Покупатель',
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'password' => (string) Str::uuid(),
        ]);
    }

    protected function nextNumber(): string
    {
        $tenantId = app(TenantContext::class)->id();
        $count = Order::query()->where('tenant_id', $tenantId)->count() + 1;

        return str_pad((string) $count, 6, '0', STR_PAD_LEFT);
    }
}