<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Tenant\TenantContext;
use App\Support\Slugger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'name',
        'slug',
        'ext_id',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if (blank($model->slug) && $model->name) {
                $model->slug = $model->uniqueSlug();
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Уникальный slug в пределах магазина (кириллица → латиница).
     */
    public function uniqueSlug(): string
    {
        $base = Slugger::slug($this->name) ?: 'kategoriya';
        $tenantId = $this->tenant_id ?? app(TenantContext::class)->id();

        $slug = $base;
        $i = 2;

        while (static::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->whereKeyNot($this->getKey())
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}