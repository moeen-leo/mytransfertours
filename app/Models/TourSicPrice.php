<?php

namespace App\Models;

use App\Models\Concerns\HasValidityWindow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourSicPrice extends Model
{
    use HasValidityWindow;

    protected $guarded = ['id'];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
