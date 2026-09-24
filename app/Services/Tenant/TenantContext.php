<?php

namespace App\Services\Tenant;

use App\Models\Tenant;

/**
 * Контекст текущего магазина (тенанта).
 *
 * Устанавливается middleware ResolveTenant в начале каждого запроса
 * и используется глобальными скоупами для изоляции данных.
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

    /**
     * Значение настройки магазина (из tenants.settings).
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->tenant?->setting($key, $default) ?? $default;
    }

    /**
     * Слаг активной темы витрины.
     */
    public function themeSlug(): string
    {
        return $this->tenant?->theme?->slug ?? config('atlas.themes.default');
    }
}