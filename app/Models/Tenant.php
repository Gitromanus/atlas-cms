<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'subdomain',
        'slug',
        'logo_path',
        'theme_id',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Владелец магазина (предприниматель) — пользователь панели /shop.
     */
    public function owner()
    {
        return $this->hasOne(User::class, 'tenant_id')
            ->where('is_super_admin', false)
            ->latest('id');
    }

    /**
     * Общая выручка магазина (сумма заказов).
     */
    public function getRevenueAttribute(): float
    {
        return (float) $this->orders()->sum('total');
    }

    /**
     * Значение настройки магазина из JSON-поля settings.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Базовый URL витрины магазина.
     *
     * Приоритет:
     *  1. Свой (кастомный) домен, если подключён;
     *  2. Path на текущем хосте: /{slug} (shared-хостинг без wildcard DNS);
     *  3. Поддомен (если ATLAS_TENANT_ROUTING=subdomain).
     */
    public function url(): string
    {
        $primary = $this->domains()->where('is_primary', true)->value('domain');

        if ($primary) {
            return str_starts_with($primary, 'http://') || str_starts_with($primary, 'https://')
                ? $primary
                : 'https://'.$primary;
        }

        $slug = $this->slug ?: $this->subdomain;

        if ($slug === null || $slug === '') {
            return url('/');
        }

        $routing = config('atlas.tenant_routing', 'path');

        if ($routing === 'subdomain' && $this->subdomain) {
            $rootDomain = config('atlas.root_domain');

            return 'https://'.$this->subdomain.'.'.$rootDomain;
        }

        // Path-режим: абсолютный URL на текущий хост + /{slug}
        // url() учитывает запрос/APP_URL корректнее, чем ручная склейка config('app.url')
        return url('/'.$slug);
    }

    /**
     * URL точки обмена с 1С для этого магазина.
     */
    public function exchangeUrl(): string
    {
        return rtrim($this->url(), '/').'/1c/exchange';
    }
}
