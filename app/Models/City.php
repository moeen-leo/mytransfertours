<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class);
    }

    public function attractions(): HasMany
    {
        return $this->hasMany(Attraction::class);
    }
}
