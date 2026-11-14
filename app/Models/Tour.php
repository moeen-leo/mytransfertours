<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tour extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'highlights' => 'array',
        'itinerary' => 'array',
        'inclusions' => 'array',
        'exclusions' => 'array',
        'sic_departure_times' => 'array',
        'private_start_times' => 'array',
        'images' => 'array',
        'has_sic' => 'boolean',
        'has_private' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function sicPrices(): HasMany
    {
        return $this->hasMany(TourSicPrice::class);
    }

    public function privatePrices(): HasMany
    {
        return $this->hasMany(TourPrivatePrice::class);
    }

    public function blackoutDates(): MorphMany
    {
        return $this->morphMany(BlackoutDate::class, 'bookable');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
