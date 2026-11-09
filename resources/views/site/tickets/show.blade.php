@extends('layouts.site', ['title' => $attraction->title, 'description' => $attraction->summary])

@php
    use App\Support\Money;
    $images = array_values((array) $attraction->images);
    $offset = $month->startOfMonth()->dayOfWeekIso - 1;   // Monday-first grid
@endphp

@section('content')
<div class="wrap page">
    <div class="small muted" style="margin-bottom: 8px"><a href="{{ route('tickets.index') }}">Attraction tickets</a> › {{ $attraction->city?->name }}</div>
    <form method="post" action="{{ route('tickets.add', $attraction) }}" class="layout" id="ticket-form">
        @csrf
        <section class="main">
            <h1 style="font-size: clamp(30px, 4vw, 40px)">{{ $attraction->title }}</h1>
            <div class="row">
                <span class="chip">{{ $attraction->delivery_method === 'instant_voucher' ? 'Instant voucher' : 'E-ticket by email' }}</span>
                <span class="chip">{{ $attraction->is_refundable ? 'Free cancellation up to '.$attraction->cancellation_hours.'h before' : 'Non-refundable' }}</span>
            </div>
            @if (count($images))<div class="ph" style="height: 360px; padding: 0"><img src="{{ route('media', ['path' => $images[0]]) }}" alt="{{ $attraction->title }}"></div>@endif
            @if ($attraction->summary)<p style="font-size: 18px">{{ $attraction->summary }}</p>@endif
            @if ($attraction->description)<div class="card pad">{!! \Illuminate\Support\Str::markdown($attraction->description, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>@endif
            @if (! empty($attraction->good_to_know))
                <div class="card pad"><b>Good to know</b><ul class="small muted" style="margin: 8px 0 0; padding-left: 18px">@foreach ($attraction->good_to_know as $x)<li>{{ $x }}</li>@endforeach</ul></div>
            @endif
        </section>

        <aside class="side">
            <div class="card summary">
                <span class="label">1 · Pick a date</span>
                @if ($errors->any())<div class="alert error" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
                <div class="row between">
                    @if ($canPrev)<a class="btn small ghost" href="{{ route('tickets.show', [$attraction, 'month' => $month->subMonth()->format('Y-m')]) }}" aria-label="Previous month">‹</a>@else<span></span>@endif
                    <b>{{ $month->format('F Y') }}</b>
                    @if ($canNext)<a class="btn small ghost" href="{{ route('tickets.show', [$attraction, 'month' => $month->addMonth()->format('Y-m')]) }}" aria-label="Next month">›</a>@else<span></span>@endif
                </div>
                <div class="cal" role="radiogroup" aria-label="Date" style="display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 4px; text-align: center">
                    @foreach (['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'] as $d)<span class="label">{{ $d }}</span>@endforeach
                    @for ($i = 0; $i < $offset; $i++)<span></span>@endfor
                    @foreach ($calendar as $date => $price)
                        @if ($price === null)
                            <span style="min-height: 44px; display: flex; align-items: center; justify-content: center; color: #A9AFAB">{{ (int) substr($date, 8) }}</span>
                        @else
                            <label style="min-height: 44px; border-radius: 10px; cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 14px" class="cal-day">
                                <input type="radio" name="date" value="{{ $date }}" class="sr-only" @checked(old('date', $selected) === $date)>
                                <span>{{ (int) substr($date, 8) }}</span><small class="tiny muted">{{ rtrim(rtrim(number_format(\App\Support\Money::convert($price), 0), '0'), '.') ?: '0' }}</small>
                            </label>
                        @endif
                    @endforeach
                </div>
                <p class="tiny muted">Lowest adult price per day in {{ $siteCurrency->code }}. Greyed-out days are closed or past.</p>

                <span class="label" style="margin-top: 6px">2 · How many?</span>
                @foreach ($types as $type)
                    <div class="row between" style="border-bottom: 1px solid var(--line); padding: 8px 0">
                        <div><b>{{ $type->name }}</b>@if ($type->age_label)<span class="tiny muted"> · {{ $type->age_label }}</span>@endif<br><span class="small muted">{{ Money::format((float) $type->selling_price) }}{{ $type->requires_id ? ' · ID required' : '' }}</span></div>
                        <label class="stepper"><span class="sr-only">{{ $type->name }}</span><input type="number" name="tickets[{{ $type->id }}]" min="0" max="50" value="{{ old('tickets.'.$type->id, $loop->first ? 2 : 0) }}"></label>
                    </div>
                @endforeach

                <div class="total-box"><span id="quote-summary" class="small muted">Pick a date</span><span class="price" id="quote-total">—</span></div>
                <div id="quote-error" class="error" role="alert" hidden></div>
                <button class="btn coral block" id="add-btn">Add to my trip</button>
            </div>
        </aside>
    </form>
</div>

<style>
    .cal-day:has(input:checked) { background: var(--green); color: #fff; }
    .cal-day:has(input:checked) small { color: #CFE3D8; }
    .cal-day:hover { background: var(--green-soft); }
    .cal-day:has(input:focus-visible) { outline: 2px solid var(--green); }
</style>
<script>
(function () {
    var form = document.getElementById('ticket-form');
    var url = @json(route('tickets.quote', $attraction));
    var timer;
    function quote() {
        clearTimeout(timer);
        timer = setTimeout(function () {
            var params = new URLSearchParams(new FormData(form)); params.delete('_token');
            if (!params.get('date')) { document.getElementById('add-btn').disabled = true; return; }
            fetch(url + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    var err = document.getElementById('quote-error');
                    document.getElementById('quote-total').textContent = d.ok ? d.total : '—';
                    document.getElementById('quote-summary').textContent = d.ok ? d.summary : 'Pick a date';
                    err.hidden = d.ok; err.textContent = d.ok ? '' : d.error;
                    document.getElementById('add-btn').disabled = !d.ok;
                });
        }, 200);
    }
    form.addEventListener('change', quote);
    form.addEventListener('input', quote);
    quote();
})();
</script>
@endsection
