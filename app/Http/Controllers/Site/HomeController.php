<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Attraction;
use App\Models\City;
use App\Models\Location;
use App\Models\Tour;
use App\Models\TransferRoute;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('site.home', [
            'locations' => self::bookableLocations(),
            'tours' => Tour::query()->where('is_active', true)->with('city')->orderBy('sort')->limit(6)->get(),
            'attractions' => Attraction::query()->where('is_active', true)->with(['city', 'ticketTypes'])->orderBy('sort')->limit(6)->get(),
            'cities' => City::query()->where('is_active', true)->orderBy('sort')->limit(4)->get(),
            'fromPrices' => self::fromPrices(),
        ]);
    }

    /**
     * Pick-up / drop-off points that are part of at least one active route, grouped by city
     * for the search dropdowns: [city name => [location id => name]].
     */
    public static function bookableLocations(): array
    {
        $routes = TransferRoute::query()->where('is_active', true)->whereHas('prices', fn ($q) => $q->where('is_active', true))->get(['from_location_id', 'to_location_id']);
        $ids = $routes->pluck('from_location_id')->merge($routes->pluck('to_location_id'))->unique();

        return Location::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->with('city')
            ->orderBy('city_id')->orderBy('sort')->orderBy('name')
            ->get()
            ->groupBy(fn (Location $l) => $l->city?->name ?? 'Other')
            ->map(fn ($group) => $group->mapWithKeys(fn (Location $l) => [$l->id => $l->name])->all())
            ->all();
    }

    /** Lowest selling prices (MYR) per product type, for the "from RM …" labels. */
    public static function fromPrices(): array
    {
        return [
            'transfer' => (float) \App\Models\TransferRoutePrice::query()->where('is_active', true)->whereHas('route', fn ($q) => $q->where('is_active', true))->min('selling_price'),
            'tour' => (float) \App\Models\TourSicPrice::query()->where('pax_type', 'adult')->whereHas('tour', fn ($q) => $q->where('is_active', true))->min('selling_price'),
            'attraction' => (float) \App\Models\AttractionTicketType::query()->where('is_active', true)->whereHas('attraction', fn ($q) => $q->where('is_active', true))->min('selling_price'),
        ];
    }
}
