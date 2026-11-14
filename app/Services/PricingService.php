<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Models\Extra;
use App\Models\PricingRule;
use App\Models\TransferRoute;
use App\Models\TransferRoutePrice;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Works out cost and selling prices (always in MYR) for a given date and time,
 * applying the admin's dynamic pricing rules on top of the base prices.
 */
class PricingService
{
    /** @return array{cost: float, sell: float, rules: array<int, string>} */
    public function applyRules(ProductType $type, Model $bookable, float $cost, float $sell, CarbonInterface $date, ?string $time = null): array
    {
        $applied = [];

        $rules = PricingRule::query()
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('product_type')->orWhere('product_type', $type->value))
            ->where(fn (Builder $q) => $q->whereNull('bookable_type')->orWhere(fn (Builder $w) => $w
                ->where('bookable_type', $bookable->getMorphClass())
                ->where('bookable_id', $bookable->getKey())))
            ->orderBy('priority')
            ->get();

        foreach ($rules as $rule) {
            if (! $rule->matches($date, $time)) {
                continue;
            }
            if (in_array($rule->applies_to, ['selling', 'both'], true)) {
                $sell = $rule->apply($sell);
            }
            if (in_array($rule->applies_to, ['cost', 'both'], true)) {
                $cost = $rule->apply($cost);
            }
            $applied[] = $rule->name;
        }

        return ['cost' => round(max(0, $cost), 2), 'sell' => round(max(0, $sell), 2), 'rules' => $applied];
    }

    /** The active price row for one vehicle on a route on a given date (newest validity window wins). */
    public function transferPrice(TransferRoute $route, int $vehicleTypeId, CarbonInterface $date): ?TransferRoutePrice
    {
        return $route->prices()
            ->with(['vehicleType', 'supplier', 'route.supplier'])
            ->where('vehicle_type_id', $vehicleTypeId)
            ->where('is_active', true)
            ->validOn($date)
            ->orderByRaw('valid_from IS NULL')
            ->orderByDesc('valid_from')
            ->first();
    }

    /**
     * Vehicle options for a route, cheapest first, each priced for the date/time.
     *
     * @return Collection<int, array{price: TransferRoutePrice, vehicle: \App\Models\VehicleType, cost: float, sell: float, fits: bool, rules: array}>
     */
    public function transferOptions(TransferRoute $route, CarbonInterface $date, ?string $time, int $pax, int $bags): Collection
    {
        $prices = $route->prices()
            ->with(['vehicleType', 'supplier', 'route.supplier'])
            ->where('is_active', true)
            ->whereHas('vehicleType', fn (Builder $q) => $q->where('is_active', true))
            ->validOn($date)
            ->orderByRaw('valid_from IS NULL')
            ->orderByDesc('valid_from')
            ->get()
            ->unique('vehicle_type_id');

        return $prices
            ->map(function (TransferRoutePrice $price) use ($route, $date, $time, $pax, $bags) {
                $priced = $this->applyRules(ProductType::Transfer, $route, (float) $price->cost_price, (float) $price->selling_price, $date, $time);

                return [
                    'price' => $price,
                    'vehicle' => $price->vehicleType,
                    'cost' => $priced['cost'],
                    'sell' => $priced['sell'],
                    'rules' => $priced['rules'],
                    'fits' => $price->vehicleType->fits($pax, $bags),
                ];
            })
            ->sortBy([['fits', 'desc'], ['sell', 'asc']])
            ->values();
    }

    /** Active add-ons for a product type. */
    public function extras(ProductType $type): EloquentCollection
    {
        return Extra::query()->where('product_type', $type->value)->where('is_active', true)->orderBy('sort')->get();
    }

    /**
     * Prices selected add-ons.
     *
     * @param  array<int|string, int|string>  $selected  extra id => quantity
     * @return array{cost: float, sell: float, lines: array<int, array{extra: Extra, qty: int}>}
     */
    public function priceExtras(ProductType $type, array $selected): array
    {
        $cost = 0.0;
        $sell = 0.0;
        $lines = [];

        foreach ($this->extras($type) as $extra) {
            $qty = (int) ($selected[$extra->id] ?? 0);
            $qty = max(0, min($qty, (int) $extra->max_quantity));
            if ($qty === 0) {
                continue;
            }
            $cost += $qty * (float) $extra->cost_price;
            $sell += $qty * (float) $extra->selling_price;
            $lines[] = ['extra' => $extra, 'qty' => $qty];
        }

        return ['cost' => round($cost, 2), 'sell' => round($sell, 2), 'lines' => $lines];
    }
}
