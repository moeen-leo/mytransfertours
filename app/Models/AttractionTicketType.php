<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttractionTicketType extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'requires_id' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function attraction(): BelongsTo
    {
        return $this->belongsTo(Attraction::class);
    }
}
