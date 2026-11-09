@extends('layouts.site', ['title' => 'Airport & city transfers in Malaysia', 'description' => 'Private airport and city transfers in Malaysia with a fixed price per car. KLIA, klia2 and hotels: meet and greet, flight tracking and free waiting time.'])

@php
    use App\Support\Money;
    $minDate = now()->addDay()->format('Y-m-d');
@endphp

@section('content')
<section class="hero" style="padding: 40px 0 56px">
    <div class="wrap hero-grid">
        <div class="stack" style="gap: 16px">
            <span class="chip" style="background: rgba(242,184,75,.18); color: #F7D58E; align-self: flex-start">Transfers</span>
            <h1 style="font-size: clamp(34px, 5vw, 52px)">Airport &amp; city rides,<br><span>one fixed price.</span></h1>
            <p class="lead">Private car, MPV or van. The price is for the whole vehicle, not per person. Your driver tracks your flight and waits if it's late.</p>
            <div class="ticks"><span>✓ Meet &amp; greet</span><span>✓ Tolls included</span><span>✓ Free cancellation window</span></div>
        </div>
        <form class="card search-card stack" method="get" action="{{ route('transfers.search') }}" style="gap: 12px">
            @if (count($locations) === 0)
                <div class="alert info">Transfers are being added. Please check back soon.</div>
            @else
                <div class="grid-2">
                    <label class="field span-2"><span class="label">Pick-up</span>
                        <select name="from_id" required><option value="">Choose pick-up</option>
                            @foreach ($locations as $city => $options)<optgroup label="{{ $city }}">@foreach ($options as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</optgroup>@endforeach
                        </select>
                    </label>
                    <label class="field span-2"><span class="label">Drop-off</span>
                        <select name="to_id" required><option value="">Choose drop-off</option>
                            @foreach ($locations as $city => $options)<optgroup label="{{ $city }}">@foreach ($options as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</optgroup>@endforeach
                        </select>
                    </label>
                    <label class="field"><span class="label">Date</span><input type="date" name="date" min="{{ $minDate }}" value="{{ $minDate }}" required></label>
                    <label class="field"><span class="label">Time</span><input type="time" name="time" value="14:00" required></label>
                    <label class="field"><span class="label">Adults</span><input type="number" name="adults" min="1" max="50" value="2"></label>
                    <label class="field"><span class="label">Children</span><input type="number" name="children" min="0" max="50" value="0"></label>
                    <label class="field span-2"><span class="label">Large bags</span><input type="number" name="bags" min="0" max="60" value="2"></label>
                </div>
                <button class="btn coral big">See vehicles &amp; prices →</button>
            @endif
        </form>
    </div>
</section>

@if ($routes->isNotEmpty())
<section class="section wrap">
    <h2 style="margin-bottom: 20px">Popular routes</h2>
    <div class="tiles">
        @foreach ($routes as $r)
            <a class="card pad tile" href="{{ route('transfers.search', ['from_id' => $r->from_location_id, 'to_id' => $r->to_location_id, 'date' => $minDate, 'time' => '14:00', 'adults' => 2, 'children' => 0, 'bags' => 2]) }}">
                <span class="small muted">{{ $r->from?->city?->name }}</span>
                <b style="font-size: 17px">{{ $r->from?->short_name ?: $r->from?->name }} {{ $r->is_bidirectional ? '↔' : '→' }} {{ $r->to?->short_name ?: $r->to?->name }}</b>
                <span class="small muted">@if ($r->duration_minutes)~{{ $r->duration_minutes }} min · @endif from <b style="color: var(--ink)">{{ Money::format((float) $r->prices_min_selling_price) }}</b> / vehicle</span>
            </a>
        @endforeach
    </div>
</section>
@endif
@endsection
