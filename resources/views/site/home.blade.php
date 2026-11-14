@extends('layouts.site', ['darkHeader' => true] + \App\Support\PageCopy::seo('home'))
@push('head')
<link rel="preload" as="image" href="{{ asset('img/hero-malaysia.jpg') }}" media="(min-width: 901px)">
<link rel="preload" as="image" href="{{ asset('img/hero-malaysia-sm.jpg') }}" media="(max-width: 900px)">
{{-- Keys are written '@'.'type' so Blade doesn't read them as its own @ commands. --}}
<script type="application/ld+json">{!! json_encode([
    '@'.'context' => 'https://schema.org',
    '@'.'graph' => [
        ['@'.'type' => 'TravelAgency', '@'.'id' => url('/').'#org', 'name' => 'MyTransferTours', 'url' => url('/'), 'logo' => asset('favicon.svg'), 'image' => asset('og-image.png'), 'areaServed' => 'Malaysia'],
        ['@'.'type' => 'WebSite', 'name' => 'MyTransferTours', 'url' => url('/'), 'publisher' => ['@'.'id' => url('/').'#org']],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush

@php
    use App\Support\Money;
    $minDate = now()->addDay()->format('Y-m-d');
    $first = fn ($images) => is_array($images) && count($images) ? route('media', ['path' => $images[0]]) : null;
@endphp

@section('content')
<section class="hero" style="--hero-img: url('{{ asset('img/hero-malaysia.jpg') }}'); --hero-img-sm: url('{{ asset('img/hero-malaysia-sm.jpg') }}')">
    <div class="wrap hero-grid">
        <div class="stack" style="gap: 22px">
            @php $copy = \App\Support\PageCopy::for('home'); @endphp
            @if ($copy['label'] !== '')<span class="chip" style="background: rgba(242,184,75,.18); color: #F7D58E; align-self: flex-start">{{ \App\Support\PageCopy::get('home', 'label') }}</span>@endif
            @if ($copy['heading'] !== '')<h1>{{ \App\Support\PageCopy::heading('home') }}</h1>@endif
            @if ($copy['subheading'] !== '')<p class="lead">{{ $copy['subheading'] }}</p>@endif
            <div class="ticks">
                <span>✓ Fixed price per vehicle</span>
                <span>✓ Secure payment</span>
                <span>✓ Prices in {{ $siteCurrencies->pluck('code')->join(' · ') }}</span>
            </div>
        </div>

        <div class="card search-card stack" id="search" style="gap: 16px">
            <div class="tabs" role="tablist" aria-label="What do you want to book">
                <button type="button" class="tab" role="tab" aria-selected="true" aria-controls="panel-transfer" id="tab-transfer">Transfer</button>
                <button type="button" class="tab" role="tab" aria-selected="false" aria-controls="panel-tour" id="tab-tour">Tour</button>
                <button type="button" class="tab" role="tab" aria-selected="false" aria-controls="panel-ticket" id="tab-ticket">Attraction ticket</button>
            </div>

            <form class="tab-panel stack" id="panel-transfer" role="tabpanel" aria-labelledby="tab-transfer" method="get" action="{{ route('transfers.search') }}">
                @if (count($locations) === 0)
                    <div class="alert info">Transfers are being added. Please check back soon.</div>
                @else
                    <div class="grid-2">
                        <label class="field span-2"><span class="label">Pick-up</span>
                            <select name="from_id" required>
                                <option value="">Choose pick-up</option>
                                @foreach ($locations as $city => $options)
                                    <optgroup label="{{ $city }}">
                                        @foreach ($options as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </label>
                        <label class="field span-2"><span class="label">Drop-off</span>
                            <select name="to_id" required>
                                <option value="">Choose drop-off</option>
                                @foreach ($locations as $city => $options)
                                    <optgroup label="{{ $city }}">
                                        @foreach ($options as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </label>
                        <label class="field"><span class="label">Pick-up date</span><input type="date" name="date" min="{{ $minDate }}" value="{{ $minDate }}" required></label>
                        <label class="field"><span class="label">Pick-up time</span><input type="time" name="time" value="14:00" step="300" required></label>
                        <label class="field"><span class="label">Adults</span><input type="number" name="adults" min="1" max="50" value="2" required></label>
                        <label class="field"><span class="label">Children</span><input type="number" name="children" min="0" max="50" value="0"></label>
                        <label class="field span-2"><span class="label">Large bags</span><input type="number" name="bags" min="0" max="60" value="2"></label>
                    </div>
                    <button class="btn coral big">See vehicles &amp; prices →</button>
                @endif
            </form>

            <div class="tab-panel stack" id="panel-tour" role="tabpanel" aria-labelledby="tab-tour" hidden>
                @forelse ($tours as $tour)
                    <a href="{{ route('tours.show', $tour) }}" class="row between" style="text-decoration: none; color: inherit; padding: 10px 0; border-bottom: 1px dashed var(--line)">
                        <span><b>{{ $tour->title }}</b><br><span class="small muted">{{ $tour->city?->name }} · {{ intdiv($tour->duration_minutes, 60) }}h</span></span>
                        <span class="small">→</span>
                    </a>
                @empty
                    <div class="alert info">Tours are being added. Please check back soon.</div>
                @endforelse
                <a href="{{ route('tours.index') }}" class="btn coral">See all tours →</a>
            </div>

            <div class="tab-panel stack" id="panel-ticket" role="tabpanel" aria-labelledby="tab-ticket" hidden>
                @forelse ($attractions as $a)
                    <a href="{{ route('tickets.show', $a) }}" class="row between" style="text-decoration: none; color: inherit; padding: 10px 0; border-bottom: 1px dashed var(--line)">
                        <span><b>{{ $a->title }}</b><br><span class="small muted">{{ $a->city?->name }}</span></span>
                        <span class="small">→</span>
                    </a>
                @empty
                    <div class="alert info">Attraction tickets are being added. Please check back soon.</div>
                @endforelse
                <a href="{{ route('tickets.index') }}" class="btn coral">See all attractions →</a>
            </div>
        </div>
    </div>
</section>

<section class="wrap products">
    <a href="{{ route('transfers.search') }}" class="card product-card">
        <div class="row between"><span class="chip">Transfers</span>@if ($fromPrices['transfer'] > 0)<span class="small muted">from <b style="color: var(--ink); font-size: 18px">{{ Money::format($fromPrices['transfer']) }}</b> / vehicle</span>@endif</div>
        <h3 style="font-size: 24px">Airport &amp; city rides</h3>
        <p class="muted">Sedan, MPV, van or executive. One price for the car, not per head. Your driver tracks your flight.</p>
    </a>
    <a href="{{ route('tours.index') }}" class="card product-card">
        <div class="row between"><span class="chip tour">Tours</span>@if ($fromPrices['tour'] > 0)<span class="small muted">from <b style="color: var(--ink); font-size: 18px">{{ Money::format($fromPrices['tour']) }}</b> / person</span>@endif</div>
        <h3 style="font-size: 24px">Seat-in-coach or private</h3>
        <p class="muted">Join a shared group for less, or take a private car on your own schedule.</p>
    </a>
    <a href="{{ route('tickets.index') }}" class="card product-card">
        <div class="row between"><span class="chip ticket">Tickets</span>@if ($fromPrices['attraction'] > 0)<span class="small muted">from <b style="color: var(--ink); font-size: 18px">{{ Money::format($fromPrices['attraction']) }}</b> / person</span>@endif</div>
        <h3 style="font-size: 24px">Skip the ticket queue</h3>
        <p class="muted">Pick a date and headcount. Your e-ticket arrives by email, ready to scan.</p>
    </a>
</section>

@if ($tours->isNotEmpty())
<section class="section wrap" id="tours">
    <div class="row between" style="margin-bottom: 24px; align-items: end"><h2>Tours</h2><a href="{{ route('tours.index') }}">All tours →</a></div>
    <div class="tiles">
        @foreach ($tours as $tour)
            <a class="tile" href="{{ route('tours.show', $tour) }}">
                <div class="ph" style="height: 200px; padding: 0">@if ($src = $first($tour->images))<img src="{{ $src }}" alt="{{ $tour->title }}" loading="lazy">@else<span style="padding: 12px">{{ $tour->title }}</span>@endif</div>
                <b style="font-size: 17px">{{ $tour->title }}</b>
                <span class="small muted">{{ $tour->city?->name }} · {{ intdiv($tour->duration_minutes, 60) }}h{{ $tour->duration_minutes % 60 ? ' '.($tour->duration_minutes % 60).'m' : '' }}</span>
            </a>
        @endforeach
    </div>
</section>
@endif

@if ($attractions->isNotEmpty())
<section class="section wrap" id="tickets" style="padding-top: 0">
    <div class="row between" style="margin-bottom: 24px; align-items: end"><h2>Attraction tickets</h2><a href="{{ route('tickets.index') }}">All attractions →</a></div>
    <div class="tiles">
        @foreach ($attractions as $a)
            @php($fromTicket = $a->ticketTypes->where('is_active', true)->min('selling_price'))
            <a class="tile" href="{{ route('tickets.show', $a) }}">
                <div class="ph" style="height: 200px; padding: 0">@if ($src = $first($a->images))<img src="{{ $src }}" alt="{{ $a->title }}" loading="lazy">@else<span style="padding: 12px">{{ $a->title }}</span>@endif</div>
                <b style="font-size: 17px">{{ $a->title }}</b>
                <span class="small muted">{{ $a->city?->name }}@if ($fromTicket) · from {{ Money::format((float) $fromTicket) }}@endif</span>
            </a>
        @endforeach
    </div>
</section>
@endif

<section class="wrap" style="padding: 24px 24px 72px">
    <div class="card pad trust">
        <div><b>Secure payment</b><p class="small muted" style="margin-top: 6px">Card payments are handled by Stripe. We never see or store your card number.</p></div>
        <div><b>Instant confirmation</b><p class="small muted" style="margin-top: 6px">Your booking reference arrives by email straight away.</p></div>
        <div><b>Clear cancellation terms</b><p class="small muted" style="margin-top: 6px">Shown on every product before you book.</p></div>
        <div><b>Pay in your currency</b><p class="small muted" style="margin-top: 6px">See prices in {{ $siteCurrencies->pluck('code')->join(', ') }}.</p></div>
    </div>
</section>

<script>
document.querySelectorAll('[role="tab"]').forEach(function (tab) {
    tab.addEventListener('click', function () {
        document.querySelectorAll('[role="tab"]').forEach(function (t) {
            t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
            document.getElementById(t.getAttribute('aria-controls')).hidden = t !== tab;
        });
    });
});
</script>
@endsection
