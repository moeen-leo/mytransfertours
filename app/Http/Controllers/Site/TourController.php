<?php

namespace App\Http\Controllers\Site;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Tour;
use App\Services\CartService;
use App\Services\PricingService;
use App\Services\TourQuoter;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TourController extends Controller
{
    private const FIELDS = ['date', 'option', 'vehicle_type_id', 'adults', 'children', 'start_time', 'pickup_hotel', 'extras'];

    public function __construct(private TourQuoter $quoter, private CartService $cart, private PricingService $pricing) {}

    public function index(Request $request): View
    {
        $city = $request->query('city');

        $tours = Tour::query()
            ->where('is_active', true)
            ->when($city, fn ($q) => $q->whereHas('city', fn ($c) => $c->where('slug', $city)))
            ->with(['city', 'sicPrices', 'privatePrices'])
            ->orderBy('sort')->orderBy('title')
            ->get();

        return view('site.tours.index', [
            'tours' => $tours,
            'cities' => City::query()->where('is_active', true)->whereHas('tours', fn ($q) => $q->where('is_active', true))->orderBy('sort')->get(),
            'city' => $city,
        ]);
    }

    public function show(Tour $tour): View
    {
        abort_unless($tour->is_active, 404);
        $tour->load(['city', 'privatePrices.vehicleType', 'sicPrices']);

        return view('site.tours.show', [
            'tour' => $tour,
            'vehicles' => $tour->privatePrices->filter(fn ($p) => $p->vehicleType?->is_active)->unique('vehicle_type_id')->sortBy(fn ($p) => $p->vehicleType->max_pax)->values(),
            'extras' => $this->pricing->extras(ProductType::Tour),
            'minDate' => now()->addHours(TourQuoter::MIN_HOURS_BEFORE)->addDay()->format('Y-m-d'),
        ]);
    }

    /** Live price for the booking panel (JSON). */
    public function quote(Request $request, Tour $tour): JsonResponse
    {
        try {
            $q = $this->quoter->quote(['tour_id' => $tour->id] + $request->only(self::FIELDS));
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'error' => collect($e->errors())->flatten()->first()]);
        }

        return response()->json([
            'ok' => true,
            'total' => Money::format($q['sell']),
            'summary' => $q['summary'],
            'lines' => collect($q['lines'])->map(fn ($l) => $l['qty'].' × '.$l['label'].' · '.Money::format($l['unit']))->all(),
        ]);
    }

    public function add(Request $request, Tour $tour): RedirectResponse
    {
        $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'option' => ['required', 'in:sic,private'],
            'adults' => ['required', 'integer', 'min:1', 'max:60'],
            'children' => ['nullable', 'integer', 'min:0', 'max:60'],
            'pickup_hotel' => ['nullable', 'string', 'max:255'],
            'extras' => ['nullable', 'array'],
            'extras.*' => ['integer', 'min:0', 'max:10'],
        ]);

        $quote = $this->quoter->quote(['tour_id' => $tour->id] + $request->only(self::FIELDS));
        $this->cart->add($quote);

        return redirect()->route('trip')->with('status', $tour->title.' added to your trip.');
    }
}
