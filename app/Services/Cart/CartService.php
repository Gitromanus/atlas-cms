<?php

namespace App\Services\Cart;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Product;
use App\Services\Tenant\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Корзина покупателя.
 *
 * Гостевая корзина привязана к session_id; при входе покупателя
 * его корзина объединяется с гостевой.
 */
class CartService
{
    protected function sessionKey(): ?string
    {
        return session()->getId();
    }

    protected function query(): Builder
    {
        $query = CartItem::query();

        $customer = Auth::guard('customers')->user();

        if ($customer !== null) {
            return $query->where('customer_id', $customer->id);
        }

        return $query->whereNull('customer_id')->where('session_id', $this->sessionKey());
    }

    public function add(Product $product, int $quantity = 1): CartItem
    {
        $item = $this->query()->where('product_id', $product->id)->first();

        if ($item !== null) {
            $item->increment('quantity', $quantity);

            return $item->fresh();
        }

        $customer = Auth::guard('customers')->user();

        return $this->query()->create([
            'customer_id' => $customer?->id,
            'session_id' => $customer === null ? $this->sessionKey() : null,
            'product_id' => $product->id,
            'quantity' => max(1, $quantity),
        ]);
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
            ->with(['product.mainImage', 'product.category'])
            ->get();
    }

    public function count(): int
    {
        return (int) $this->query()->sum('quantity');
    }

    public function total(): float
    {
        return (float) $this->items()->sum(fn (CartItem $item) => (float) ($item->product->price ?? 0) * $item->quantity);
    }

    /**
     * Объединение гостевой корзины с корзиной авторизованного покупателя.
     */
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
                ->first();

            if ($existing !== null) {
                $existing->increment('quantity', $guestItem->quantity);
            } else {
                $guestItem->update(['customer_id' => $customer->id]);
                continue;
            }

            $guestItem->delete();
        }
    }
}