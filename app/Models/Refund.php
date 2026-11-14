<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount' => 'decimal:2'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookingItem(): BelongsTo
    {
        return $this->belongsTo(BookingItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
