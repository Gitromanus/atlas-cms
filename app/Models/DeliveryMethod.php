<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DeliveryMethod extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'code', 'description', 'price', 'free_from',
        'require_address', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'free_from' => 'decimal:2',
            'require_address' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function costFor(float $itemsTotal): float
    {
        if ($this->free_from !== null && (float) $this->free_from > 0 && $itemsTotal >= (float) $this->free_from) {
            return 0.0;
        }

        return (float) $this->price;
    }
}
