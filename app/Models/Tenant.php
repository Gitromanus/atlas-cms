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
        'owner_id',
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function getRevenueAttribute(): float
    {
        return (float) $this->orders()->sum('total');
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /** Витрина на корне сайта. */
    public function url(): string
    {
        $primary = $this->domains()->where('is_primary', true)->value('domain');

        if ($primary) {
            return str_starts_with($primary, 'http://') || str_starts_with($primary, 'https://')
                ? $primary
                : 'https://'.$primary;
        }

        return url('/');
    }

    public function exchangeUrl(): string
    {
        return url('/1c/exchange');
    }
}
