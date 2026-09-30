<?php

namespace App\Services\Orders;

use App\Mail\NewOrderToShop;
use App\Mail\OrderConfirmationToCustomer;
use App\Models\Customer;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use App\Services\Cart\CartService;
use App\Services\Delivery\YandexDeliveryService;
use App\Services\Tenant\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        protected CartService $cart,
        protected YandexDeliveryService $yandex,
    ) {}

    public function create(array $data): Order
    {
        $customer = auth('customers')->user();

        if ($customer === null) {
            $customer = $this->findOrCreateCustomer($data);
        }

        $items = $this->cart->items();

        abort_if($items->isEmpty(), 422, 'Корзина пуста');

        $itemsTotal = $items->sum(function ($item) {
            return $this->unitPrice($item) * $item->quantity;
        });
        $deliveryMeta = $this->resolveDelivery($data, $itemsTotal);
        $deliveryCost = $deliveryMeta['cost'];
        $deliveryMethodId = $deliveryMeta['method_id'];
        $deliveryMethodName = $deliveryMeta['method_name'];

        $status = OrderStatus::query()->where('code', 'new')->first();
        if ($status === null) {
            $tenantId = app(TenantContext::class)->id();
            $status = OrderStatus::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => 'new'],
                ['name' => 'Новый', 'is_system' => true]
            );
            foreach ([
                ['code' => 'processing', 'name' => 'В обработке'],
                ['code' => 'shipped', 'name' => 'Отправлен'],
                ['code' => 'completed', 'name' => 'Выполнен'],
                ['code' => 'cancelled', 'name' => 'Отменён'],
            ] as $row) {
                OrderStatus::query()->firstOrCreate(
                    ['tenant_id' => $tenantId, 'code' => $row['code']],
                    ['name' => $row['name'], 'is_system' => true]
                );
            }
        }

        return DB::transaction(function () use ($data, $customer, $items, $itemsTotal, $deliveryCost, $deliveryMethodId, $deliveryMethodName, $status) {
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
                'delivery_method' => $deliveryMethodName ?? ($data['delivery_method'] ?? null),
                'delivery_method_id' => $deliveryMethodId,
                'delivery_address' => $data['delivery_address'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'comment' => $data['comment'] ?? null,
                'placed_at' => now(),
            ]);

            foreach ($items as $item) {
                $unit = $this->unitPrice($item);
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'sku' => $item->product->sku,
                    'options' => $item->options ?? [],
                    'quantity' => $item->quantity,
                    'price' => $unit,
                    'total' => $unit * $item->quantity,
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
        $tenant = app(TenantContext::class)->current() ?? $order->tenant ?? null;
        if ($tenant === null) {
            return;
        }

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
            Log::warning('OrderConfirmationToCustomer mail failed: '.$e->getMessage());
        }
    }

    protected function unitPrice($item): float
    {
        $product = $item->product;
        $options = is_array($item->options) ? $item->options : [];

        if ($product && $options !== [] && $product->relationLoaded('variants') === false) {
            $product->load('variants');
        }

        if ($product && $options !== [] && $product->variants) {
            foreach ($product->variants as $variant) {
                $vo = $variant->options ?? [];
                if (! is_array($vo) || $vo === []) {
                    continue;
                }
                $match = true;
                foreach ($vo as $k => $v) {
                    if ((string) ($options[$k] ?? '') !== (string) $v) {
                        $match = false;
                        break;
                    }
                }
                if ($match && $variant->price !== null) {
                    return (float) $variant->price;
                }
            }
        }

        return (float) ($product->price ?? 0);
    }

    protected function resolveDelivery(array $data, float $itemsTotal): array
    {
        $clientCost = isset($data['delivery_cost']) ? (float) $data['delivery_cost'] : null;

        if (! empty($data['delivery_method_id'])) {
            $method = DeliveryMethod::query()->find($data['delivery_method_id']);
            if ($method !== null) {
                $isYandex = str_contains(mb_strtolower($method->code.' '.$method->name), 'yandex')
                    || str_contains(mb_strtolower($method->code.' '.$method->name), 'яндекс');

                if ($clientCost !== null && $clientCost >= 0 && $isYandex) {
                    return [
                        'cost' => $clientCost,
                        'method_id' => $method->id,
                        'method_name' => $method->name,
                    ];
                }

                return [
                    'cost' => (float) $method->costFor($itemsTotal),
                    'method_id' => $method->id,
                    'method_name' => $method->name,
                ];
            }
        }

        $code = (string) ($data['delivery_method'] ?? 'pickup');
        $cost = match ($code) {
            'pickup' => 0.0,
            'courier' => 300.0,
            'post' => 350.0,
            'yandex' => $clientCost ?? 0.0,
            default => $clientCost ?? 0.0,
        };

        return [
            'cost' => (float) $cost,
            'method_id' => null,
            'method_name' => $code,
        ];
    }

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
