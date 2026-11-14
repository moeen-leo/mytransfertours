<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingItemTransfer extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'flight_arrival_at' => 'datetime',
        'is_return_leg' => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(BookingItem::class, 'booking_item_id');
    }

    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'pickup_location_id');
    }

    public function dropoffLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'dropoff_location_id');
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function pairedItem(): BelongsTo
    {
        return $this->belongsTo(BookingItem::class, 'paired_item_id');
    }

    public function getPassengersAttribute(): int
    {
        return $this->adults + $this->children + $this->infants;
    }
}
