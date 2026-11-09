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
        ]);
    }

    /** Step 2 submit: validate details, re-price, add to the trip. */
    public function add(Request $request): RedirectResponse
    {
        $from = Location::query()->findOrFail((int) $request->input('from_id'));
        $to = Location::query()->findOrFail((int) $request->input('to_id'));

        $request->validate($this->quoter->detailRules($from, $to), [
            'flight_number.required' => 'Please enter your flight number so the driver can track your arrival.',
            'flight_number.regex' => 'Please enter a flight number like MH 123 or AK6301.',
            'pickup_address.required' => 'Please tell us the hotel or address to pick you up from.',
            'dropoff_address.required' => 'Please tell us the hotel or address to drop you at.',
        ]);

        $quote = $this->quoter->quote($request->only([
            ...self::SEARCH_KEYS, 'vehicle_type_id', 'flight_number', 'pickup_address', 'dropoff_address', 'sign_name', 'driver_notes', 'extras',
        ]));

        $this->cart->add($quote);

        return redirect()->route('trip')->with('status', 'Transfer added to your trip.');
    }
}
