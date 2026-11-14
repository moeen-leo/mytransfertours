<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleType extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function fits(int $pax, int $bags = 0): bool
    {
        return $pax <= $this->max_pax && $bags <= $this->max_bags;
    }
}
