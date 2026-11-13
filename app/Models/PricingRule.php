<?php

namespace App\Models;

use App\Enums\ProductType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PricingRule extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'product_type' => ProductType::class,
        'weekdays' => 'array',
        'date_from' => 'date',
        'date_to' => 'date',
        'adjustment_value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Does this rule apply to a service on the given date and (optional) time "HH:MM"? */
    public function matches(CarbonInterface $date, ?string $time = null): bool
    {
        return match ($this->rule_type) {
            'weekday' => in_array($date->isoWeekday(), array_map('intval', $this->weekdays ?? []), true),
            'date_range' => $date->betweenIncluded($this->date_from, $this->date_to),
            'time_range' => $time !== null && $this->timeInRange($time),
            default => false,
        };
    }

    public function apply(float $amount): float
    {
        $value = (float) $this->adjustment_value;

        return round($this->adjustment_type === 'percent'
            ? $amount * (1 + $value / 100)
            : $amount + $value, 2);
    }

    private function timeInRange(string $time): bool
    {
        $from = substr((string) $this->time_from, 0, 5);
        $to = substr((string) $this->time_to, 0, 5);

        // Ranges may cross midnight, e.g. 23:00 – 06:00.
        return $from <= $to ? ($time >= $from && $time < $to) : ($time >= $from || $time < $to);
    }
}
