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
     * URL витрины магазина.
     */
    public function url(): string
    {
        $primary = $this->domains()->where('is_primary', true)->value('domain')
            ?? ($this->subdomain ? $this->subdomain.'.'.config('atlas.root_domain') : null);

        return $primary ? 'https://'.$primary : '#';
    }
}