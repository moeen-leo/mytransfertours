<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function isUsable(float $orderAmount): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->isPast())
            && ($this->ends_at === null || $this->ends_at->isFuture())
            && ($this->max_uses === null || $this->used_count < $this->max_uses)
            && ($this->min_order_amount === null || $orderAmount >= (float) $this->min_order_amount);
    }

    public function discountFor(float $orderAmount): float
    {
        $discount = $this->type === 'percent'
            ? $orderAmount * ((float) $this->value / 100)
            : (float) $this->value;

        return round(min($discount, $orderAmount), 2);
    }
}
