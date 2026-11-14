<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Models\Attraction;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/** Prices attraction tickets: a date plus a quantity of each ticket type (Adult, Child, MyKad…). */
class TicketQuoter
{
    public function __construct(private PricingService $pricing) {}

    /** @param array<string, mixed> $p payload */
    public function quote(array $p): array
    {
        $attraction = Attraction::query()->where('is_active', true)->with(['supplier', 'ticketTypes'])->find($p['attraction_id'] ?? 0);
        if (! $attraction) {
            throw ValidationException::withMessages(['attraction_id' => 'This attraction is not available.']);
        }

        $tz = config('app.timezone');
        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', (string) ($p['date'] ?? ''), $tz)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['date' => 'Please choose a date.']);
        }
        if ($date->lt(now($tz)->startOfDay()->addDay())) {
            throw ValidationException::withMessages(['date' => 'Tickets can be booked from tomorrow onwards.']);
        }
        if (! $attraction->isOpenOn($date)) {
            throw ValidationException::withMessages(['date' => $attraction->title.' is closed on '.$date->format('l j M').'. Please pick another day.']);
        }

        $requested = (array) ($p['tickets'] ?? []);
        $cost = 0.0;
        $sell = 0.0;
        $lines = [];
        $clean = [];

        foreach ($attraction->ticketTypes->where('is_active', true) as $type) {
            $qty = max(0, min(50, (int) ($requested[$type->id] ?? 0)));
            if ($qty === 0) {
                continue;
            }
            $r = $this->pricing->applyRules(ProductType::Attraction, $attraction, (float) $type->cost_price, (float) $type->selling_price, $date);
            $cost += $qty * $r['cost'];
            $sell += $qty * $r['sell'];
            $lines[] = ['type' => $type, 'label' => $type->name, 'qty' => $qty, 'unit' => $r['sell'], 'unit_cost' => $r['cost']];
            $clean[$type->id] = $qty;
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['tickets' => 'Please choose at least one ticket.']);
        }

        return [
            'type' => ProductType::Attraction,
            'bookable' => $attraction,
            'cancellation_hours' => (int) $attraction->cancellation_hours,
            'summary' => collect($lines)->map(fn ($l) => $l['qty'].' × '.$l['label'])->join(', '),
            'attraction' => $attraction,
            'vehicle' => null,
            'supplier' => $attraction->supplier,
            'date' => $date,
            'time' => null,
            'cost' => round($cost, 2),
            'sell' => round($sell, 2),
            'lines' => $lines,
            'extras' => [],
            'title' => $attraction->title,
            'payload' => ['attraction_id' => $attraction->id, 'date' => $date->format('Y-m-d'), 'tickets' => $clean],
        ];
    }

    /**
     * Adult price per day for a month, for the booking calendar. Closed days are null.
     *
     * @return array<string, ?float> 'Y-m-d' => MYR price
     */
    public function calendar(Attraction $attraction, CarbonImmutable $month): array
    {
        $type = $attraction->ticketTypes->where('is_active', true)->sortBy('sort')->first();
        $days = [];
        $tomorrow = now(config('app.timezone'))->startOfDay()->addDay();

        for ($d = $month->startOfMonth(); $d->lte($month->endOfMonth()); $d = $d->addDay()) {
            if (! $type || $d->lt($tomorrow) || ! $attraction->isOpenOn($d)) {
                $days[$d->format('Y-m-d')] = null;

                continue;
            }
            $days[$d->format('Y-m-d')] = $this->pricing->applyRules(ProductType::Attraction, $attraction, (float) $type->cost_price, (float) $type->selling_price, $d)['sell'];
        }

        return $days;
    }
}
