<?php

namespace App\Services\Tenant;

use App\Models\Tenant;

/**
 * Контекст текущего магазина (режим одного магазина).
 */
class TenantContext
{
    protected ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->tenant?->setting($key, $default) ?? $default;
    }

    public function themeSlug(): string
    {
        return $this->tenant?->theme?->slug ?? config('atlas.themes.default', 'default');
    }

    /**
     * Единственный активный магазин. Остальные is_active=false.
     */
    public function resolveSingle(): ?Tenant
    {
        if ($this->tenant !== null) {
            return $this->tenant;
        }

        $tenant = Tenant::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->with('theme')
            ->first();

        if ($tenant === null) {
            $tenant = Tenant::query()->orderBy('id')->with('theme')->first();
        }

        if ($tenant !== null) {
            Tenant::query()
                ->where('id', '!=', $tenant->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        $this->tenant = $tenant;

        return $tenant;
    }
}
