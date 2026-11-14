<?php

namespace App\Models\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/** For price rows with optional valid_from / valid_to dates. */
trait HasValidityWindow
{
    public function scopeValidOn(Builder $query, CarbonInterface $date): Builder
    {
        $day = $date->toDateString();

        return $query
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $day))
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $day));
    }
}
