<?php

namespace App\Models;

use App\Models\Concerns\HasValidityWindow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferRoutePrice extends Model
{
    use HasValidityWindow;

    protected $guarded = ['id'];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransferRoute::class, 'transfer_route_id');
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** The supplier who fulfils this vehicle class: the override, else the route default. */
    public function effectiveSupplier(): ?Supplier
    {
        return $this->supplier ?? $this->route?->supplier;
    }
}
