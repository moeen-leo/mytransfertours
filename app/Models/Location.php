<?php

namespace App\Models;

use App\Enums\LocationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'type' => LocationType::class,
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'requires_flight_number' => 'boolean',
        'requires_address' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function routesFrom(): HasMany
    {
        return $this->hasMany(TransferRoute::class, 'from_location_id');
    }

    public function routesTo(): HasMany
    {
        return $this->hasMany(TransferRoute::class, 'to_location_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort')->orderBy('name');
    }
}
