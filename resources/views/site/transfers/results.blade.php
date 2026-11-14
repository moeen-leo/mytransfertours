@extends('layouts.site', ['title' => 'Transfers'])

@php
    use App\Support\Money;
    use Illuminate\Support\Str;

    $fromName = $from->short_name ?: $from->name;
    $toName = $to->short_name ?: $to->name;
    $pickupAt = \Carbon\CarbonImmutable::createFromFormat('Y-m-d H:i', $search['date'].' '.$search['time'], config('app.timezone'));
    $minutes = $route?->duration_minutes;
    $arriveAt = $minutes ? $pickupAt->addMinutes($minutes) : null;
    $km = $route?->distance_km ? rtrim(rtrim(number_format((float) $route->distance_km, 1), '0'), '.') : null;
    $journeyTime = $minutes ? ($minutes >= 60 ? intdiv($minutes, 60).' h'.($minutes % 60 ? ' '.($minutes % 60).' min' : '') : $minutes.' min') : null;
    $returnDefault = $pickupAt->addDays(7);
@endphp

@if ($map)
    @push('head')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    @endpush
@endif

@section('content')
<div class="subbar">
    <form class="wrap" method="get" action="{{ route('transfers.search') }}">
        <label class="field"><span class="label">Pick-up</span>
            <select name="from_id" required>
                @foreach ($locations as $city => $opts)<optgroup label="{{ $city }}">@foreach ($opts as $id => $name)<option value="{{ $id }}" @selected($id == $search['from_id'])>{{ $name }}</option>@endforeach</optgroup>@endforeach
            </select>
        </label>
        <label class="field"><span class="label">Drop-off</span>
            <select name="to_id" required>
                @foreach ($locations as $city => $opts)<optgroup label="{{ $city }}">@foreach ($opts as $id => $name)<option value="{{ $id }}" @selected($id == $search['to_id'])>{{ $name }}</option>@endforeach</optgroup>@endforeach
            </select>
        </label>
        <label class="field"><span class="label">Date</span><input type="date" name="date" value="{{ $search['date'] }}" min="{{ now()->format('Y-m-d') }}" required></label>
        <label class="field"><span class="label">Time</span><input type="time" name="time" value="{{ $search['time'] }}" required></label>
        <label class="field"><span class="label"><x-mtt-icon name="person" /> Adults</span><input type="number" name="adults" value="{{ $search['adults'] }}" min="1" max="50"></label>
        <label class="field"><span class="label"><x-mtt-icon name="person" /> Children</span><input type="number" name="children" value="{{ $search['children'] }}" min="0" max="50"></label>
        <label class="field"><span class="label"><x-mtt-icon name="bag" /> Bags</span><input type="number" name="bags" value="{{ $search['bags'] }}" min="0" max="60"></label>
        <button class="btn coral" style="min-height: 58px">Update</button>
    </form>
</div>

<div class="wrap page stack" style="gap: 16px">
    <div class="steps"><b>1. Choose a vehicle</b> › 2. Trip details › 3. My trip › 4. Checkout</div>

    @if ($errors->any())
        <div class="alert error" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    @if ($tooSoon)
        <div class="alert error" role="alert">Transfers must be booked at least {{ \App\Services\TransferQuoter::MIN_HOURS_BEFORE }} hours before pick-up. Please choose a later time, or contact us for urgent rides.</div>
    @endif

    <div class="results-layout">
        <section class="results-main card" aria-labelledby="results-title">
            <h1 id="results-title">One-way options for {{ $pax }} {{ Str::plural('passenger', $pax) }}</h1>
            <p class="muted">Fixed price per vehicle, not per person. A local licensed driver is assigned after you book.</p>

            @if (! $route)
                <div class="results-empty">
                    <h2>We don't run this route yet</h2>
                    <p class="muted">Try a nearby pick-up or drop-off point, or contact us and we'll arrange it for you.</p>
                </div>
            @elseif ($options->isEmpty())
                <div class="results-empty"><p>No vehicles are available on this date. Please try another date.</p></div>
            @else
                @foreach ($options as $opt)
                    @php($v = $opt['vehicle'])
                    <article class="vehicle-card {{ $opt['fits'] ? '' : 'unfit' }}">
                        <div class="pic">
                            @if ($v->image)
                                <img src="{{ route('media', ['path' => $v->image]) }}" alt="" loading="lazy">
                            @else
                                <svg width="96" height="40" viewBox="0 0 76 34" aria-hidden="true"><path d="M6 24 L10 13 Q12 8 18 8 H50 Q56 8 60 13 L68 18 Q72 19 72 24 V26 H6 Z" fill="#0F4C3A"/><circle cx="20" cy="27" r="5" fill="#10231C"/><circle cx="56" cy="27" r="5" fill="#10231C"/></svg>
                            @endif
                        </div>
                        <div class="info">
                            <div class="row" style="gap: 10px; flex-wrap: wrap">
                                <h2>{{ $v->name }}</h2>
                                @if ($loop->first && $opt['fits'])<span class="chip">Best value</span>@endif
                            </div>
                            @if ($v->example_models)<span class="small muted">{{ $v->example_models }}</span>@endif
                            <div class="capacity">
                                <span><x-mtt-icon name="seat" /> {{ $v->max_pax }} {{ Str::plural('seat', $v->max_pax) }}</span>
                                <span><x-mtt-icon name="bag" /> {{ $v->max_bags }} large {{ Str::plural('bag', $v->max_bags) }}</span>
                                <span class="tip">
                                    <button type="button" class="tip-btn" aria-label="What fits in the {{ $v->name }}" aria-describedby="fits-{{ $v->id }}">i</button>
                                    <span class="tip-text" role="tooltip" id="fits-{{ $v->id }}">Up to {{ $v->max_pax }} {{ Str::plural('passenger', $v->max_pax) }} including children, and {{ $v->max_bags }} large {{ Str::plural('suitcase', $v->max_bags) }} plus a small bag each.</span>
                                </span>
                            </div>
                            <div class="perks">
                                <span>✓ Free cancellation</span>
                                @foreach ($v->features ?? [] as $f)<span>✓ {{ $f }}</span>@endforeach
                            </div>
                            @if (! $opt['fits'])<span class="error">Too small for {{ $pax }} {{ Str::plural('passenger', $pax) }} / {{ $search['bags'] }} {{ Str::plural('bag', (int) $search['bags']) }}</span>@endif
                        </div>
                        <div class="buy">
                            <span class="price">{{ Money::format($opt['sell']) }}</span>
                            @if ($opt['fits'] && ! $tooSoon)
                                <a class="btn" data-select href="{{ route('transfers.details', array_merge($search, ['vehicle_type_id' => $v->id])) }}">Select</a>
                            @endif
                        </div>
                    </article>

                    @if ($loop->first && $returnAvailable && ! $tooSoon)
                        <div class="return-card" id="return-card">
                            <span class="return-icon" aria-hidden="true"><x-mtt-icon name="swap" /></span>
                            <div class="stack" style="gap: 4px; flex: 1 1 240px">
                                <b>Add a return journey</b>
                                <span class="small muted">{{ $toName }} → {{ $fromName }}, booked together in one payment. Same vehicle you select.</span>
                                <span class="small" id="return-status" hidden style="color: var(--green); font-weight: 600"></span>
                            </div>
                            <div class="return-fields">
                                <div class="row" style="gap: 8px">
                                    <label class="field"><span class="label">Return date</span><input type="date" id="return-date" value="{{ $returnDefault->format('Y-m-d') }}" min="{{ $search['date'] }}"></label>
                                    <label class="field"><span class="label">Pick-up time</span><input type="time" id="return-time" value="{{ $search['time'] }}"></label>
                                </div>
                                <button type="button" class="btn ghost" id="return-toggle">Add return</button>
                            </div>
                        </div>
                    @endif
                @endforeach
            @endif
        </section>

        <aside class="results-side" aria-label="Your journey">
            <div class="card journey">
                @if ($map)
                    <div class="route-map">
                        <div id="route-map" role="img" aria-label="Map of the route from {{ $from->name }} to {{ $to->name }}"></div>
                        @if ($km || $journeyTime)
                            <span class="map-chip">@if ($km)≈ {{ $km }} km @endif @if ($km && $journeyTime)·@endif @if ($journeyTime)~{{ $journeyTime }}@endif</span>
                        @endif
                    </div>
                @endif
                <div class="journey-body">
                    <div class="row between" style="gap: 10px; flex-wrap: wrap">
                        <h2>Your journey</h2>
                        <span class="small muted row" style="gap: 10px"><span><x-mtt-icon name="person" /> {{ $pax }}</span><span><x-mtt-icon name="bag" /> {{ $search['bags'] }}</span></span>
                    </div>
                    <ol class="timeline">
                        <li>
                            <span class="small muted">{{ $pickupAt->format('D j M') }} · {{ $pickupAt->format('H:i') }}</span>
                            <b>{{ $from->name }}</b>
                            @if ($from->requires_flight_number)<span class="small muted">Your driver meets you in arrivals with a name sign</span>
                            @elseif ($from->requires_address)<span class="small muted">Hotel or address in the next step</span>@endif
                        </li>
                        <li class="end">
                            <span class="small muted">{{ $pickupAt->format('D j M') }}@if ($arriveAt) · about {{ $arriveAt->format('H:i') }}@endif</span>
                            <b>{{ $to->name }}</b>
                            @if ($to->requires_address)<span class="small muted">Hotel or address in the next step</span>@endif
                        </li>
                    </ol>
                    @if ($journeyTime)
                        <div class="journey-time">
                            <x-mtt-icon name="clock" />
                            <div class="stack" style="gap: 0"><b>Total journey time</b><span class="small muted">About {{ $journeyTime }}, depending on traffic</span></div>
                        </div>
                    @endif
                </div>
            </div>

            @if ($route)
                <div class="card policies">
                    <h2>Policies</h2>
                    <div><span class="tick" aria-hidden="true">✓</span><div><b>Free cancellation</b><span class="small muted">Cancel for free up to {{ $route->cancellation_hours }} hours before pick-up</span></div></div>
                    @if ($from->requires_flight_number)
                        <div><span class="tick" aria-hidden="true">✓</span><div><b>Flight delayed?</b><span class="small muted">We track your flight. {{ $route->free_waiting_minutes }} min free waiting after landing</span></div></div>
                    @else
                        <div><span class="tick" aria-hidden="true">✓</span><div><b>Free waiting</b><span class="small muted">{{ $route->free_waiting_minutes }} min free waiting at pick-up</span></div></div>
                    @endif
                    <div><span class="tick" aria-hidden="true">✓</span><div><b>One price per vehicle</b><span class="small muted">Tolls and taxes included, no hidden fees</span></div></div>
                </div>
            @endif
        </aside>
    </div>
</div>

@if ($returnAvailable && $route && $options->isNotEmpty() && ! $tooSoon)
<script>
    // Return card: when added, every Select link carries the return date and time to step 2,
    // where the return leg is pre-ticked and priced.
    (() => {
        const btn = document.getElementById('return-toggle');
        const date = document.getElementById('return-date');
        const time = document.getElementById('return-time');
        const status = document.getElementById('return-status');
        const card = document.getElementById('return-card');
        let on = false;
        const fmt = (d, t) => new Date(d + 'T' + t).toLocaleString(undefined, { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
        const apply = () => document.querySelectorAll('a[data-select]').forEach(a => {
            const u = new URL(a.href);
            ['return', 'return_date', 'return_time'].forEach(k => u.searchParams.delete(k));
            if (on) { u.searchParams.set('return', '1'); u.searchParams.set('return_date', date.value); u.searchParams.set('return_time', time.value); }
            a.href = u.toString();
        });
        btn.addEventListener('click', () => {
            if (!on && (!date.value || !time.value)) { (date.value ? time : date).focus(); return; }
            on = !on;
            btn.textContent = on ? 'Remove return' : 'Add return';
            card.classList.toggle('on', on);
            status.hidden = !on;
            status.textContent = on ? '✓ Return added for ' + fmt(date.value, time.value) + '. Now select a vehicle.' : '';
            apply();
        });
        [date, time].forEach(el => el.addEventListener('change', () => { if (on) { status.textContent = '✓ Return added for ' + fmt(date.value, time.value) + '. Now select a vehicle.'; apply(); } }));
    })();
</script>
@endif

@if ($map)
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
    (() => {
        const data = @json($map);
        const el = document.getElementById('route-map');
        if (!window.L || !el) return;
        const map = L.map(el, { scrollWheelZoom: false, attributionControl: true });
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(map);
        const line = data.line
            ? L.polyline(data.line, { color: '#0F4C3A', weight: 5, opacity: .9 })
            : L.polyline([data.from, data.to], { color: '#0F4C3A', weight: 4, dashArray: '8 8', opacity: .9 });
        line.addTo(map);
        L.circleMarker(data.from, { radius: 8, color: '#0F4C3A', weight: 3, fillColor: '#fff', fillOpacity: 1 }).addTo(map).bindTooltip(@json($fromName));
        L.circleMarker(data.to, { radius: 8, color: '#B8401C', weight: 3, fillColor: '#B8401C', fillOpacity: 1 }).addTo(map).bindTooltip(@json($toName));
        map.fitBounds(line.getBounds(), { padding: [28, 28] });
    })();
</script>
@endif
@endsection
