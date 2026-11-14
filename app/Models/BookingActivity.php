<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingActivity extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = ['meta' => 'array'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
