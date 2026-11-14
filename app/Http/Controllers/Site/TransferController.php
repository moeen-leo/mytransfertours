<?php

namespace App\Http\Controllers\Site;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\TransferRoute;
use App\Services\CartService;
use App\Services\PricingService;
use App\Services\TransferQuoter;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TransferController extends Controller
{
    private const SEARCH_KEYS = ['from_id', 'to_id', 'date', 'time', 'adults', 'children', 'infants', 'bags'];

    public function __construct(private PricingService $pricing, private TransferQuoter $quoter, private CartService $cart) {}

    /** Step 1: vehicles and prices for a route. */
    public function search(Request $request): View|RedirectResponse
    {
        if (! $request->filled('from_id')) {
            return $this->landing();
        }

        $data = $request->validate([
            'from_id' => ['required', 'integer'],
            'to_id' => ['required', 'integer', 'different:from_id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'adults' => ['required', 'integer', 'min:1', 'max:50'],
            'children' => ['nullable', 'integer', 'min:0', 'max:50'],
            'infants' => ['nullable', 'integer', 'min:0', 'max:20'],
            'bags' => ['nullable', 'integer', 'min:0', 'max:60'],
        ], [
            'to_id.different' => 'Pick-up and drop-off must be different.',
        ]);
        $data += ['children' => 0, 'infants' => 0, 'bags' => 0];

        $from = Location::query()->where('is_active', true)->findOrFail($data['from_id']);
        $to = Location::query()->where('is_active', true)->findOrFail($data['to_id']);
        $route = TransferRoute::query()->between($from->id, $to->id)->first();
        $date = CarbonImmutable::createFromFormat('Y-m-d', $data['date'], config('app.timezone'))->startOfDay();
        $pax = (int) $data['adults'] + (int) $data['children'] + (int) $data['infants'];

        $options = $route ? $this->pricing->transferOptions($route, $date, $data['time'], $pax, (int) $data['bags']) : collect();

        return view('site.transfers.results', [
            'search' => $data,
            'from' => $from,
            'to' => $to,
            'route' => $route,
            'date' => $date,
            'pax' => $pax,
            'options' => $options,
            'locations' => HomeController::bookableLocations(),
            'tooSoon' => CarbonImmutable::createFromFormat('Y-m-d H:i', $data['date'].' '.$data['time'], config('app.timezone'))
                ->lt(now()->addHours(TransferQuoter::MIN_HOURS_BEFORE)),
            'map' => app(\App\Services\RouteMap::class)->for($from, $to),
            // The return card is only offered when the reverse route is sold too.
            'returnAvailable' => $route !== null && TransferRoute::query()->between($to->id, $from->id)->where('is_active', true)->exists(),
        ]);
    }

    /** /transfers without a search: the search box plus popular routes with "from" prices. */
    private function landing(): View
    {
        $routes = TransferRoute::query()
            ->where('is_active', true)
            ->whereHas('prices', fn ($q) => $q->where('is_active', true))
            ->with(['from.city', 'to'])
            ->withMin(['prices' => fn ($q) => $q->where('is_active', true)], 'selling_price')
            ->orderBy('prices_min_selling_price')
            ->limit(24)
            ->get();

        return view('site.transfers.index', [
            'routes' => $routes,
            'locations' => HomeController::bookableLocations(),
        ]);
    }

    /** Step 2: trip details for the chosen vehicle. */
    public function details(Request $request): View|RedirectResponse
    {
        $payload = $request->only([...self::SEARCH_KEYS, 'vehicle_type_id']);

        try {
            $quote = $this->quoter->quote($payload);
        } catch (ValidationException $e) {
            return redirect()->route('transfers.search', $request->only(self::SEARCH_KEYS))->withErrors($e->errors());
        }

        return view('site.transfers.details', [
            'quote' => $quote,
            'extras' => $this->pricing->extras(ProductType::Transfer),
            'search' => $request->only(self::SEARCH_KEYS),
            'returnSell' => $this->returnPreview($payload),
        ]);
    }

    /** Step 2 submit: validate details, re-price, add to the trip (plus the return leg, if ticked). */
    public function add(Request $request): RedirectResponse
    {
        $from = Location::query()->findOrFail((int) $request->input('from_id'));
        $to = Location::query()->findOrFail((int) $request->input('to_id'));
        $withReturn = $request->boolean('return');

        $request->validate([
            ...$this->quoter->detailRules($from, $to),
            ...($withReturn ? $this->returnRules($to, $from) : []),
        ], [
            'flight_number.required' => 'Please enter your flight number so the driver can track your arrival.',
            'flight_number.regex' => 'Please enter a flight number like MH 123 or AK6301.',
            'pickup_address.required' => 'Please tell us the hotel or address to pick you up from.',
            'dropoff_address.required' => 'Please tell us the hotel or address to drop you at.',
            'return_date.required' => 'Please choose the date of your return transfer.',
            'return_date.after_or_equal' => 'The return transfer can\'t be before the first transfer.',
            'return_time.required' => 'Please choose the pick-up time of your return transfer.',
            'return_flight_number.required' => 'Please enter the flight number for your return pick-up.',
            'return_flight_number.regex' => 'Please enter a flight number like MH 123 or AK6301.',
        ]);

        $quote = $this->quoter->quote($request->only([
            ...self::SEARCH_KEYS, 'vehicle_type_id', 'flight_number', 'pickup_address', 'dropoff_address', 'sign_name', 'driver_notes', 'extras',
        ]));

        $returnQuote = null;
        if ($withReturn) {
            $outAt = $quote['date']->setTimeFromTimeString($quote['time']);
            $backAt = CarbonImmutable::createFromFormat('Y-m-d H:i', $request->input('return_date').' '.$request->input('return_time'), config('app.timezone'));
            if ($backAt->lte($outAt->addHour())) {
                throw ValidationException::withMessages(['return_time' => 'The return pick-up must be at least an hour after the first pick-up.']);
            }

            try {
                $returnQuote = $this->quoter->quote($this->returnPayload($quote['payload'], $request));
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['return' => 'Return transfer: '.collect($e->errors())->flatten()->first()]);
            }
        }

        $this->cart->add($quote);
        if ($returnQuote) {
            $this->cart->add($returnQuote);
        }

        return redirect()->route('trip')->with('status', $returnQuote ? 'Transfer and return transfer added to your trip.' : 'Transfer added to your trip.');
    }

    /** Validation for the return leg. $pickup is where the return starts (the first leg's drop-off). */
    private function returnRules(Location $pickup, Location $dropoff): array
    {
        return [
            'return_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:date'],
            'return_time' => ['required', 'date_format:H:i'],
            'return_flight_number' => [$pickup->requires_flight_number ? 'required' : 'nullable', 'string', 'max:12', 'regex:/^[A-Za-z0-9]{2}\s?\d{1,4}[A-Za-z]?$/'],
            'return_pickup_address' => ['nullable', 'string', 'max:255'],
            'return_dropoff_address' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** The first leg reversed: same vehicle and passengers, the hotels swapped unless the customer typed others. */
    private function returnPayload(array $out, Request $request): array
    {
        return [
            'from_id' => $out['to_id'],
            'to_id' => $out['from_id'],
            'vehicle_type_id' => $out['vehicle_type_id'],
            'date' => $request->input('return_date'),
            'time' => $request->input('return_time'),
            'adults' => $out['adults'],
            'children' => $out['children'],
            'infants' => $out['infants'],
            'bags' => $out['bags'],
            'flight_number' => $request->input('return_flight_number'),
            'pickup_address' => $request->input('return_pickup_address') ?: $out['dropoff_address'],
            'dropoff_address' => $request->input('return_dropoff_address') ?: $out['pickup_address'],
            'sign_name' => $out['sign_name'],
            'driver_notes' => null,
            'extras' => [],
            'is_return_leg' => true,
        ];
    }

    /** Price of the same vehicle the other way, for the "Add a return" box. Null if the route can't be booked back. */
    private function returnPreview(array $payload): ?float
    {
        try {
            $back = $this->quoter->quote([...$payload, 'from_id' => $payload['to_id'] ?? 0, 'to_id' => $payload['from_id'] ?? 0]);

            return $back['vehicle_sell'];
        } catch (ValidationException) {
            return null;
        }
    }
}
