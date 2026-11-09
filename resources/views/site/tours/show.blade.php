@extends('layouts.site', ['title' => $tour->title, 'description' => $tour->summary])

@php
    use App\Support\Money;
    $images = array_values((array) $tour->images);
    $sicTimes = array_values(array_filter((array) $tour->sic_departure_times));
    $privTimes = array_values(array_filter((array) $tour->private_start_times));
    $defaultOption = old('option', $tour->has_sic ? 'sic' : 'private');
    $sicAdult = $tour->sicPrices->where('pax_type', 'adult')->min('selling_price');
@endphp

@section('content')
<div class="wrap page">
    <div class="small muted" style="margin-bottom: 8px"><a href="{{ route('tours.index') }}">Tours</a> › {{ $tour->city?->name }}</div>
    <form method="post" action="{{ route('tours.add', $tour) }}" class="layout" id="tour-form">
        @csrf
        <section class="main">
            <h1 style="font-size: clamp(30px, 4vw, 40px)">{{ $tour->title }}</h1>
            <div class="row">
                <span class="chip">{{ intdiv($tour->duration_minutes, 60) }}h{{ $tour->duration_minutes % 60 ? ' '.($tour->duration_minutes % 60).'m' : '' }}</span>
                @if ($tour->has_sic)<span class="chip">Seat-in-coach</span>@endif
                @if ($tour->has_private)<span class="chip">Private</span>@endif
                <span class="chip">Free cancellation up to {{ $tour->cancellation_hours }}h before</span>
            </div>

            @if (count($images))
                <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); grid-auto-rows: 170px; gap: 8px">
                    @foreach (array_slice($images, 0, 5) as $i => $img)
                        <div class="ph" style="padding: 0; {{ $i === 0 ? 'grid-column: span 2; grid-row: span 2' : '' }}"><img src="{{ route('media', ['path' => $img]) }}" alt="{{ $tour->title }}" loading="lazy"></div>
                    @endforeach
                </div>
            @endif

            @if ($tour->summary)<p style="font-size: 18px">{{ $tour->summary }}</p>@endif
            @if ($tour->description)<div class="card pad">{!! \Illuminate\Support\Str::markdown($tour->description, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>@endif

            @if (! empty($tour->itinerary))
                <div class="card pad stack">
                    <h2 style="font-size: 22px">What you'll do</h2>
                    <div style="display: grid; grid-template-columns: 64px 1fr; gap: 10px 14px">
                        @foreach ($tour->itinerary as $stop)<b>{{ $stop['time'] ?? '' }}</b><span>{{ $stop['text'] ?? '' }}</span>@endforeach
                    </div>
                </div>
            @endif

            <div class="grid-2" style="gap: 16px">
                @if (! empty($tour->inclusions))<div class="card pad"><b>Included</b><ul class="small muted" style="margin: 8px 0 0; padding-left: 18px">@foreach ($tour->inclusions as $x)<li>{{ $x }}</li>@endforeach</ul></div>@endif
                @if (! empty($tour->exclusions))<div class="card pad"><b>Not included</b><ul class="small muted" style="margin: 8px 0 0; padding-left: 18px">@foreach ($tour->exclusions as $x)<li>{{ $x }}</li>@endforeach</ul></div>@endif
            </div>
        </section>

        <aside class="side">
            <div class="card summary">
                <span class="label">Book this tour</span>
                @if ($errors->any())<div class="alert error" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

                <label class="field"><span class="label">Date</span><input type="date" name="date" min="{{ $minDate }}" value="{{ old('date', $minDate) }}" required></label>

                @if ($tour->has_sic && $tour->has_private)
                    <div class="grid-2">
                        <label class="check card pad" style="padding: 12px"><input type="radio" name="option" value="sic" @checked($defaultOption === 'sic')><span><b>Seat-in-coach</b><br><span class="tiny muted">Shared group{{ $sicAdult ? ' · from '.Money::format((float) $sicAdult).'/adult' : '' }}</span></span></label>
                        <label class="check card pad" style="padding: 12px"><input type="radio" name="option" value="private" @checked($defaultOption === 'private')><span><b>Private</b><br><span class="tiny muted">Your own vehicle</span></span></label>
                    </div>
                @else
                    <input type="hidden" name="option" value="{{ $defaultOption }}">
                @endif

                <div data-show="private" @if ($defaultOption !== 'private') hidden @endif>
                    <label class="field"><span class="label">Vehicle</span>
                        <select name="vehicle_type_id">
                            @foreach ($vehicles as $v)<option value="{{ $v->vehicle_type_id }}" @selected(old('vehicle_type_id') == $v->vehicle_type_id)>{{ $v->vehicleType->name }} · up to {{ $v->vehicleType->max_pax }} · {{ Money::format((float) $v->selling_price) }}</option>@endforeach
                        </select>
                    </label>
                </div>

                <div class="grid-2">
                    <label class="field"><span class="label">Adults</span><input type="number" name="adults" min="1" max="60" value="{{ old('adults', 2) }}"></label>
                    <label class="field"><span class="label">Children ({{ $tour->child_age_from }}–{{ $tour->child_age_to }})</span><input type="number" name="children" min="0" max="60" value="{{ old('children', 0) }}"></label>
                </div>

                <label class="field" data-show="sic" @if ($defaultOption !== 'sic') hidden @endif><span class="label">Departure</span>
                    <select name="start_time" data-times="sic">@foreach ($sicTimes as $t)<option>{{ $t }}</option>@endforeach</select>
                </label>
                <label class="field" data-show="private" @if ($defaultOption !== 'private') hidden @endif><span class="label">Start time</span>
                    <select name="start_time" data-times="private" @if ($defaultOption !== 'private') disabled @endif>
                        @forelse ($privTimes as $t)<option>{{ $t }}</option>@empty @foreach (['08:00', '09:00', '10:00', '13:00', '14:00'] as $t)<option>{{ $t }}</option>@endforeach @endforelse
                    </select>
                </label>

                <label class="field"><span class="label">Pick-up hotel (optional)</span><input name="pickup_hotel" value="{{ old('pickup_hotel') }}" placeholder="Hotel name in {{ $tour->city?->name }}"></label>

                @foreach ($extras as $extra)
                    <div class="row between small"><span>{{ $extra->name }} · {{ Money::format((float) $extra->selling_price) }}</span><input type="number" name="extras[{{ $extra->id }}]" min="0" max="{{ $extra->max_quantity }}" value="0" style="width: 60px" class="stepper"></div>
                @endforeach

                <div class="total-box"><span id="quote-summary" class="small muted">Choose your options</span><span class="price" id="quote-total">—</span></div>
                <div id="quote-error" class="error" role="alert" hidden></div>
                <button class="btn coral block" id="add-btn">Add to my trip</button>
            </div>
        </aside>
    </form>
</div>

<script>
(function () {
    var form = document.getElementById('tour-form');
    var url = @json(route('tours.quote', $tour));
    var timer;
    function option() { var r = form.querySelector('[name=option]:checked') || form.querySelector('[name=option]'); return r ? r.value : 'sic'; }
    function toggle() {
        var o = option();
        form.querySelectorAll('[data-show]').forEach(function (el) { el.hidden = el.dataset.show !== o; });
        form.querySelectorAll('[data-times]').forEach(function (el) { el.disabled = el.dataset.times !== o; });
    }
    function quote() {
        clearTimeout(timer);
        timer = setTimeout(function () {
            var params = new URLSearchParams(new FormData(form)); params.delete('_token');
            fetch(url + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    var err = document.getElementById('quote-error');
                    document.getElementById('quote-total').textContent = d.ok ? d.total : '—';
                    document.getElementById('quote-summary').textContent = d.ok ? d.lines.join(' + ') : 'Choose your options';
                    err.hidden = d.ok; err.textContent = d.ok ? '' : d.error;
                    document.getElementById('add-btn').disabled = !d.ok;
                });
        }, 250);
    }
    form.addEventListener('change', function () { toggle(); quote(); });
    form.addEventListener('input', quote);
    toggle(); quote();
})();
</script>
@endsection
