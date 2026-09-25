<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'number',
        'status_id',
        'ext_id',
        'items_total',
        'delivery_cost',
        'total',
        'currency',
        'customer_name',
        'customer_phone',
        'customer_email',
        'delivery_method',
        'delivery_method_id',
        'delivery_address',
        'payment_method',
        'payment_status',
        'payment_id',
        'comment',
        'is_paid',
        'exported_to_1c',
        'placed_at',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
    ];

    protected function casts(): array
    {
        return [
            'items_total' => 'decimal:2',
            'delivery_cost' => 'decimal:2',
            'total' => 'decimal:2',
            'is_paid' => 'boolean',
            'exported_to_1c' => 'boolean',
            'placed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'status_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function deliveryMethod(): BelongsTo
    {
        return $this->belongsTo(DeliveryMethod::class);
    }
}
