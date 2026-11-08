<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingItemExtra extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'unit_price' => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(BookingItem::class, 'booking_item_id');
    }

    public function extra(): BelongsTo
    {
        return $this->belongsTo(Extra::class);
    }
}
