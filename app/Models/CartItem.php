<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CartItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'product_type' => ProductType::class,
        'service_date' => 'date',
        'payload' => 'array',
        'quoted_cost' => 'decimal:2',
        'quoted_price' => 'decimal:2',
        'quoted_at' => 'datetime',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }
}
