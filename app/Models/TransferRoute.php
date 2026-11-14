<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TransferRoute extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'distance_km' => 'decimal:1',
        'is_bidirectional' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function from(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(TransferRoutePrice::class);
    }

    public function blackoutDates(): MorphMany
    {
        return $this->morphMany(BlackoutDate::class, 'bookable');
    }

    /** Finds the route between two locations, honouring bidirectional routes. */
    public function scopeBetween(Builder $query, int $fromId, int $toId): Builder
    {
        return $query->where('is_active', true)->where(function (Builder $q) use ($fromId, $toId) {
            $q->where(fn (Builder $w) => $w->where('from_location_id', $fromId)->where('to_location_id', $toId))
              ->orWhere(fn (Builder $w) => $w->where('is_bidirectional', true)
                  ->where('from_location_id', $toId)->where('to_location_id', $fromId));
        });
    }

    public function getTitleAttribute(): string
    {
        return ($this->from?->short_name ?? $this->from?->name).' → '.($this->to?->short_name ?? $this->to?->name);
    }
}
