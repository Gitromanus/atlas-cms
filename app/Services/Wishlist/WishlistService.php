<?php

namespace App\Services\Wishlist;

use App\Models\Product;
use App\Models\Wishlist;
use App\Services\Tenant\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class WishlistService
{
    public function sessionKey(): string
    {
        if (! Session::has('wishlist_sid')) {
            Session::put('wishlist_sid', bin2hex(random_bytes(16)));
        }

        return (string) Session::get('wishlist_sid');
    }

    public function toggle(int $productId): bool
    {
        $tenantId = app(TenantContext::class)->id();
        $customerId = auth('customers')->id();
        $sessionId = $this->sessionKey();

        $query = Wishlist::query()->where('tenant_id', $tenantId)->where('product_id', $productId);
        if ($customerId) {
            $query->where('customer_id', $customerId);
        } else {
            $query->where('session_id', $sessionId)->whereNull('customer_id');
        }

        $existing = $query->first();
        if ($existing) {
            $existing->delete();

            return false;
        }

        Wishlist::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'session_id' => $customerId ? null : $sessionId,
            'product_id' => $productId,
        ]);

        return true;
    }

    public function ids(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $customerId = auth('customers')->id();
        $sessionId = $this->sessionKey();

        return Wishlist::query()
            ->where('tenant_id', $tenantId)
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId), fn ($q) => $q->where('session_id', $sessionId)->whereNull('customer_id'))
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function count(): int
    {
        return count($this->ids());
    }

    public function products(): Collection
    {
        $ids = $this->ids();
        if ($ids === []) {
            return collect();
        }

        return Product::query()->active()->with(['images', 'prices', 'stocks', 'features'])->whereIn('id', $ids)->get();
    }

    public function has(int $productId): bool
    {
        return in_array($productId, $this->ids(), true);
    }
}
