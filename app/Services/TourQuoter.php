<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Models\Tour;
use App\Models\TourPrivatePrice;
use App\Models\TourSicPrice;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Prices a tour choice:
 *  - seat-in-coach (shared): per person, adult and child prices, fixed departure times
 *  - private: per vehicle, the group must fit the vehicle
 * Dynamic pricing rules apply to the per-person / per-vehicle price.
 */
class TourQuoter
{
    /** Tours can't be booked closer than this to the start. */
    public const MIN_HOURS_BEFORE = 24;

    public function __construct(private PricingService $pricing) {}

    /** @param array<string, mixed> $p payload */
    public function quote(array $p): array
    {
        $tour = Tour::query()->where('is_active', true)->with(['supplier', 'city'])->find($p['tour_id'] ?? 0);
        if (! $tour) {
            throw ValidationException::withMessages(['tour_id' => 'This tour is not available.']);
        }

        $tz = config('app.timezone');
        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', (string) ($p['date'] ?? ''), $tz)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['date' => 'Please choose a date.']);
        }
        if ($tour->blackoutDates()->whereDate('date', $date)->exists()) {
            throw ValidationException::withMessages(['date' => 'This tour doesn\'t run on '.$date->format('j M Y').'. Please pick another day.']);
        }

        $option = ($p['option'] ?? '') === 'private' ? 'private' : 'sic';
        if ($option === 'sic' && ! $tour->has_sic) {
            $option = 'private';
        }
        if ($option === 'private' && ! $tour->has_private) {
            throw ValidationException::withMessages(['option' => 'Please choose how you want to go.']);
        }

        $adults = max(0, (int) ($p['adults'] ?? 0));
        $children = max(0, (int) ($p['children'] ?? 0));
        $pax = $adults + $children;
        if ($adults < 1) {
            throw ValidationException::withMessages(['adults' => 'At least one adult is needed.']);
        }
        if ($pax < (int) $tour->min_pax) {
            throw ValidationException::withMessages(['adults' => 'This tour needs at least '.$tour->min_pax.' people.']);
        }

        // Start time: from the tour's list for the chosen option.
        $times = array_values(array_filter((array) ($option === 'sic' ? $tour->sic_departure_times : $tour->private_start_times)));
        $time = substr((string) ($p['start_time'] ?? ''), 0, 5);
        if ($times !== [] && ! in_array($time, $times, true)) {
            $time = $times[0];
        }
        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            throw ValidationException::withMessages(['start_time' => 'Please choose a start time.']);
        }
        $startAt = CarbonImmutable::createFromFormat('Y-m-d H:i', $date->format('Y-m-d').' '.$time, $tz);
        if ($startAt->lt(now($tz)->addHours(self::MIN_HOURS_BEFORE))) {
            throw ValidationException::withMessages(['date' => 'Tours must be booked at least '.self::MIN_HOURS_BEFORE.' hours ahead.']);
        }

        $vehicle = null;
        $lines = [];
        if ($option === 'sic') {
            $prices = $tour->sicPrices()->validOn($date)->orderByRaw('valid_from IS NULL')->orderByDesc('valid_from')->get()->unique('pax_type')->keyBy('pax_type');
            /** @var TourSicPrice|null $adult */
            $adult = $prices->get('adult');
            if (! $adult) {
                throw ValidationException::withMessages(['option' => 'Seat-in-coach isn\'t available on this date.']);
            }
            $child = $prices->get('child') ?? $adult;

            $a = $this->pricing->applyRules(ProductType::Tour, $tour, (float) $adult->cost_price, (float) $adult->selling_price, $date, $time);
            $c = $this->pricing->applyRules(ProductType::Tour, $tour, (float) $child->cost_price, (float) $child->selling_price, $date, $time);
            $cost = $adults * $a['cost'] + $children * $c['cost'];
            $sell = $adults * $a['sell'] + $children * $c['sell'];
            $lines[] = ['label' => 'Adult', 'qty' => $adults, 'unit' => $a['sell']];
            if ($children) {
                $lines[] = ['label' => 'Child', 'qty' => $children, 'unit' => $c['sell']];
            }
        } else {
            /** @var TourPrivatePrice|null $price */
            $price = $tour->privatePrices()->with('vehicleType')
                ->where('vehicle_type_id', (int) ($p['vehicle_type_id'] ?? 0))
                ->validOn($date)->orderByRaw('valid_from IS NULL')->orderByDesc('valid_from')->first();
            if (! $price || ! $price->vehicleType?->is_active) {
                throw ValidationException::withMessages(['vehicle_type_id' => 'Please choose a vehicle.']);
            }
            if ($pax > (int) $price->vehicleType->max_pax) {
                throw ValidationException::withMessages(['vehicle_type_id' => "A {$price->vehicleType->name} takes up to {$price->vehicleType->max_pax} people. Please pick a bigger vehicle."]);
            }
            $vehicle = $price->vehicleType;
            $r = $this->pricing->applyRules(ProductType::Tour, $tour, (float) $price->cost_price, (float) $price->selling_price, $date, $time);
            $cost = $r['cost'];
            $sell = $r['sell'];
            $lines[] = ['label' => 'Private · '.$vehicle->name, 'qty' => 1, 'unit' => $r['sell']];
        }

        $extras = $this->pricing->priceExtras(ProductType::Tour, (array) ($p['extras'] ?? []));
        $hotel = trim((string) ($p['pickup_hotel'] ?? '')) ?: null;

        $payload = [
            'tour_id' => $tour->id,
            'date' => $date->format('Y-m-d'),
            'option' => $option,
            'vehicle_type_id' => $vehicle?->id,
            'adults' => $adults,
            'children' => $children,
            'start_time' => $time,
            'pickup_hotel' => $hotel,
            'extras' => collect($extras['lines'])->mapWithKeys(fn ($l) => [$l['extra']->id => $l['qty']])->all(),
        ];

        return [
            'type' => ProductType::Tour,
            'bookable' => $tour,
            'cancellation_hours' => (int) $tour->cancellation_hours,
            'summary' => ($option === 'sic' ? 'Seat-in-coach' : 'Private · '.$vehicle->name).' · '.$adults.' '.($adults === 1 ? 'adult' : 'adults').($children ? ', '.$children.' '.($children === 1 ? 'child' : 'children') : '').($hotel ? ' · pick-up '.$hotel : ''),
            'tour' => $tour,
            'vehicle' => $vehicle,
            'supplier' => $tour->supplier,
            'date' => $date,
            'time' => $time,
            'cost' => round($cost + $extras['cost'], 2),
            'sell' => round($sell + $extras['sell'], 2),
            'lines' => $lines,
            'extras' => $extras['lines'],
            'title' => $tour->title,
            'payload' => $payload,
        ];
    }
}
