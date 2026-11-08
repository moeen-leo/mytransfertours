<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Models\Location;
use App\Models\TransferRoute;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Turns what the customer chose for a transfer (the "payload") into a priced,
 * validated line. Used when adding to the trip and again at checkout, so
 * prices and availability are always re-checked before a booking is made.
 */
class TransferQuoter
{
    /** Customers can't book a transfer closer than this to pick-up time. */
    public const MIN_HOURS_BEFORE = 12;

    public function __construct(private PricingService $pricing) {}

    /**
     * @param  array<string, mixed>  $p  payload
     * @return array{route: TransferRoute, from: Location, to: Location, price: \App\Models\TransferRoutePrice, vehicle: \App\Models\VehicleType, supplier: \App\Models\Supplier, date: CarbonImmutable, time: string, cost: float, sell: float, extras: array, title: string, payload: array}
     */
    public function quote(array $p): array
    {
        $from = Location::query()->where('is_active', true)->find($p['from_id'] ?? 0);
        $to = Location::query()->where('is_active', true)->find($p['to_id'] ?? 0);

        if (! $from || ! $to) {
            throw ValidationException::withMessages(['from_id' => 'Please choose a pick-up and drop-off point.']);
        }

        $route = TransferRoute::query()->between($from->id, $to->id)->with('supplier')->first();
        if (! $route) {
            throw ValidationException::withMessages(['to_id' => 'Sorry, we don\'t offer this route yet.']);
        }

        $tz = config('app.timezone');
        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', (string) ($p['date'] ?? ''), $tz)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['date' => 'Please choose a valid date.']);
        }
        $time = substr((string) ($p['time'] ?? ''), 0, 5);
        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            throw ValidationException::withMessages(['time' => 'Please choose a pick-up time.']);
        }
        $pickupAt = CarbonImmutable::createFromFormat('Y-m-d H:i', $date->format('Y-m-d').' '.$time, $tz);
        if ($pickupAt->lt(now($tz)->addHours(self::MIN_HOURS_BEFORE))) {
            throw ValidationException::withMessages(['date' => 'Transfers must be booked at least '.self::MIN_HOURS_BEFORE.' hours ahead.']);
        }

        $adults = max(1, (int) ($p['adults'] ?? 1));
        $children = max(0, (int) ($p['children'] ?? 0));
        $infants = max(0, (int) ($p['infants'] ?? 0));
        $bags = max(0, (int) ($p['bags'] ?? 0));
        $pax = $adults + $children + $infants;

        $price = $this->pricing->transferPrice($route, (int) ($p['vehicle_type_id'] ?? 0), $date);
        if (! $price || ! $price->vehicleType?->is_active) {
            throw ValidationException::withMessages(['vehicle_type_id' => 'That vehicle is no longer available on this route.']);
        }
        if (! $price->vehicleType->fits($pax, $bags)) {
            throw ValidationException::withMessages(['vehicle_type_id' => "A {$price->vehicleType->name} takes up to {$price->vehicleType->max_pax} passengers and {$price->vehicleType->max_bags} large bags. Please pick a bigger vehicle."]);
        }

        $base = $this->pricing->applyRules(ProductType::Transfer, $route, (float) $price->cost_price, (float) $price->selling_price, $date, $time);
        $extras = $this->pricing->priceExtras(ProductType::Transfer, (array) ($p['extras'] ?? []));
        $supplier = $price->effectiveSupplier();

        $payload = [
            'route_id' => $route->id,
            'from_id' => $from->id,
            'to_id' => $to->id,
            'vehicle_type_id' => $price->vehicle_type_id,
            'date' => $date->format('Y-m-d'),
            'time' => $time,
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants,
            'bags' => $bags,
            'flight_number' => strtoupper(trim((string) ($p['flight_number'] ?? ''))) ?: null,
            'pickup_address' => trim((string) ($p['pickup_address'] ?? '')) ?: null,
            'dropoff_address' => trim((string) ($p['dropoff_address'] ?? '')) ?: null,
            'sign_name' => trim((string) ($p['sign_name'] ?? '')) ?: null,
            'driver_notes' => trim((string) ($p['driver_notes'] ?? '')) ?: null,
            'extras' => collect($extras['lines'])->mapWithKeys(fn ($l) => [$l['extra']->id => $l['qty']])->all(),
        ];

        return [
            'route' => $route,
            'from' => $from,
            'to' => $to,
            'price' => $price,
            'vehicle' => $price->vehicleType,
            'supplier' => $supplier,
            'date' => $date,
            'time' => $time,
            'cost' => round($base['cost'] + $extras['cost'], 2),
            'sell' => round($base['sell'] + $extras['sell'], 2),
            'vehicle_sell' => $base['sell'],
            'extras' => $extras['lines'],
            'title' => ($from->short_name ?: $from->name).' → '.($to->short_name ?: $to->name),
            'payload' => $payload,
        ];
    }

    /** Validation rules for the trip-details form (checked before quoting). */
    public function detailRules(Location $from, Location $to): array
    {
        return [
            'flight_number' => [$from->requires_flight_number ? 'required' : 'nullable', 'string', 'max:12', 'regex:/^[A-Za-z0-9]{2}\s?\d{1,4}[A-Za-z]?$/'],
            'pickup_address' => [$from->requires_address ? 'required' : 'nullable', 'string', 'max:255'],
            'dropoff_address' => [$to->requires_address ? 'required' : 'nullable', 'string', 'max:255'],
            'sign_name' => ['nullable', 'string', 'max:60'],
            'driver_notes' => ['nullable', 'string', 'max:500'],
            'extras' => ['nullable', 'array'],
            'extras.*' => ['integer', 'min:0', 'max:10'],
        ];
    }
}
