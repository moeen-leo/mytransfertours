<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'cc_emails' => 'array',
        'bank_details' => 'encrypted',
        'is_active' => 'boolean',
    ];

    public function transferRoutes(): HasMany
    {
        return $this->hasMany(TransferRoute::class);
    }

    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class);
    }

    public function attractions(): HasMany
    {
        return $this->hasMany(Attraction::class);
    }

    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }
}
