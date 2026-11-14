<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Attraction;
use App\Models\City;
use App\Services\CartService;
use App\Services\TicketQuoter;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttractionController extends Controller
{
    public function __construct(private TicketQuoter $quoter, private CartService $cart) {}

    public function index(Request $request): View
    {
        $city = $request->query('city');

        return view('site.tickets.index', [
            'attractions' => Attraction::query()
                ->where('is_active', true)
                ->when($city, fn ($q) => $q->whereHas('city', fn ($c) => $c->where('slug', $city)))
                ->with(['city', 'ticketTypes'])
                ->orderBy('sort')->orderBy('title')
                ->get(),
            'cities' => City::query()->where('is_active', true)->whereHas('attractions', fn ($q) => $q->where('is_active', true))->orderBy('sort')->get(),
            'city' => $city,
        ]);
    }

    public function show(Request $request, Attraction $attraction): View
    {
        abort_unless($attraction->is_active, 404);
        $attraction->load(['city', 'ticketTypes']);

        $tz = config('app.timezone');
        $first = now($tz)->startOfMonth()->toImmutable();
        try {
            $month = $request->filled('month') ? CarbonImmutable::createFromFormat('Y-m', (string) $request->query('month'), $tz)->startOfMonth() : $first;
        } catch (\Throwable) {
            $month = $first;
        }
        $month = $month->lt($first) ? $first : ($month->gt($first->addMonths(11)) ? $first->addMonths(11) : $month);

        return view('site.tickets.show', [
            'attraction' => $attraction,
            'types' => $attraction->ticketTypes->where('is_active', true)->sortBy('sort')->values(),
            'month' => $month,
            'calendar' => $this->quoter->calendar($attraction, $month),
            'canPrev' => $month->gt($first),
            'canNext' => $month->lt($first->addMonths(11)),
            'selected' => $request->query('date'),
        ]);
    }

    public function quote(Request $request, Attraction $attraction): JsonResponse
    {
        try {
            $q = $this->quoter->quote(['attraction_id' => $attraction->id] + $request->only(['date', 'tickets']));
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

    public function add(Request $request, Attraction $attraction): RedirectResponse
    {
        $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'tickets' => ['required', 'array'],
            'tickets.*' => ['integer', 'min:0', 'max:50'],
        ], ['date.required' => 'Please pick a date in the calendar.']);

        $quote = $this->quoter->quote(['attraction_id' => $attraction->id] + $request->only(['date', 'tickets']));
        $this->cart->add($quote);

        return redirect()->route('trip')->with('status', $attraction->title.' tickets added to your trip.');
    }
}
