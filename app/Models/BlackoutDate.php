<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BlackoutDate extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['date' => 'date'];

    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }
}
