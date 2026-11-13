<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attraction extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'good_to_know' => 'array',
        'closed_weekdays' => 'array',
        'images' => 'array',
        'is_refundable' => 'boolean',
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

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(AttractionTicketType::class)->orderBy('sort');
    }

    public function blackoutDates(): MorphMany
    {
        return $this->morphMany(BlackoutDate::class, 'bookable');
    }

    public function isOpenOn(CarbonInterface $date): bool
    {
        // Stored as strings by the admin checkbox list, so compare as integers.
        if (in_array($date->isoWeekday(), array_map('intval', $this->closed_weekdays ?? []), true)) {
            return false;
        }

        return ! $this->blackoutDates()->whereDate('date', $date)->exists();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
