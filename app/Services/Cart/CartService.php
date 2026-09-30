<?php

namespace App\Services\Cart;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CartService
{
    protected function sessionKey(): string
    {
        if (! session()->has('guest_cart_session')) {
            session()->put('guest_cart_session', (string) Str::uuid());
        }

        return (string) session('guest_cart_session');
    }

    protected function query()
    {
        $customer = Auth::guard('customers')->user();

        $q = CartItem::query();

        if ($customer !== null) {
            return $q->where('customer_id', $customer->id);
        }

        return $q->whereNull('customer_id')->where('session_id', $this->sessionKey());
    }

    /**
     * @param  array<string, string>  $options
     */
    public function add(Product $product, int $quantity = 1, array $options = []): CartItem
    {
        $filtered = array_filter($options, fn ($v) => $v !== null && $v !== '');
        ksort($filtered);

        $item = $this->query()
            ->where('product_id', $product->id)
            ->get()
            ->first(function (CartItem $row) use ($filtered) {
                $rowOpts = is_array($row->options) ? array_filter($row->options) : [];
                ksort($rowOpts);

                return $rowOpts == $filtered;
            });

        if ($item !== null) {
            $item->increment('quantity', $quantity);

            return $item->fresh();
        }

        $customer = Auth::guard('customers')->user();

        try {
            return $this->query()->create([
                'customer_id' => $customer?->id,
                'session_id' => $customer === null ? $this->sessionKey() : null,
                'product_id' => $product->id,
                'options' => $filtered === [] ? null : $filtered,
                'quantity' => max(1, $quantity),
            ]);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'options')) {
                return $this->query()->create([
                    'customer_id' => $customer?->id,
                    'session_id' => $customer === null ? $this->sessionKey() : null,
                    'product_id' => $product->id,
                    'quantity' => max(1, $quantity),
                ]);
            }
            throw $e;
        }
    }

    protected function normalizeOptions(array $options): ?string
    {
        $filtered = array_filter($options, fn ($v) => $v !== null && $v !== '');

        return $filtered === [] ? null : json_encode($filtered, JSON_UNESCAPED_UNICODE);
    }

    public function setQuantity(CartItem $item, int $quantity): void
    {
        $item->update(['quantity' => max(1, $quantity)]);
    }

    public function remove(CartItem $item): void
    {
        $item->delete();
    }

    public function clear(): void
    {
        $this->query()->delete();
    }

    /**
     * @return Collection<int, CartItem>
     */
    public function items(): Collection
    {
        return $this->query()
            ->with(['product.mainImage', 'product.category', 'product.prices', 'product.variants'])
            ->get();
    }

    public function count(): int
    {
        return (int) $this->query()->sum('quantity');
    }

    public function total(): float
    {
        return (float) $this->items()->sum(function (CartItem $item) {
            return $this->unitPrice($item) * $item->quantity;
        });
    }

    public function unitPrice(CartItem $item): float
    {
        $product = $item->product;
        $options = is_array($item->options) ? $item->options : [];

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

    public function mergeGuestCart(Customer $customer): void
    {
        $guestKey = session()->get('guest_cart_session', $this->sessionKey());

        $guestItems = CartItem::query()
            ->whereNull('customer_id')
            ->where('session_id', $guestKey)
            ->get();

        foreach ($guestItems as $guestItem) {
            $existing = CartItem::query()
                ->where('customer_id', $customer->id)
                ->where('product_id', $guestItem->product_id)
                ->get()
                ->first(function (CartItem $row) use ($guestItem) {
                    $a = is_array($row->options) ? $row->options : [];
                    $b = is_array($guestItem->options) ? $guestItem->options : [];
                    ksort($a);
                    ksort($b);

                    return $a == $b;
                });

            if ($existing !== null) {
                $existing->increment('quantity', $guestItem->quantity);
                $guestItem->delete();
            } else {
                $guestItem->update(['customer_id' => $customer->id, 'session_id' => null]);
            }
        }
    }
}
