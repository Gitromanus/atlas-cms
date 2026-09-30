<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'subdomain',
        'theme_id',
        'owner_id',
        'logo_path',
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $settings = is_array($this->settings) ? $this->settings : [];

        return $settings[$key] ?? $default;
    }

    /**
     * Базовый URL витрины (один магазин — корень сайта).
     * Кастомный домен, если подключён; иначе APP_URL /.
     */
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

    /**
     * URL точки обмена с 1С.
     */
    public function exchangeUrl(): string
    {
        return url('/1c/exchange');
    }
}
