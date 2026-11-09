@extends('layouts.site', ['title' => 'My trip'])

@php use App\Support\Money; @endphp

@section('content')
<div class="wrap page">
    <div class="steps" style="margin-bottom: 16px">1. Choose › 2. Details › <b>3. My trip</b> › 4. Checkout</div>
    <div class="layout">
        <section class="main">
            <h1 style="font-size: 34px">My trip</h1>

            @if ($errors->any())
                <div class="alert error" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            @endif

            @if ($lines === [])
                <div class="card pad stack">
                    <p>Your trip is empty.</p>
                    <a href="{{ route('home') }}#search" class="btn" style="align-self: flex-start">Find a transfer</a>
                </div>
            @else
                <div class="card pad">
                    @foreach ($lines as $line)
                        @php
                            $item = $line['item'];
                            $q = $line['quote'];
                            $p = $item->payload;
                        @endphp
                        <div class="trip-item">
                            <div>
                                <div style="font-weight: 700">{{ $item->service_date->format('D j') }}</div>
                                <div class="small muted">{{ $item->service_date->format('M Y') }}</div>
                                <div class="small muted">{{ substr((string) $item->service_time, 0, 5) }}</div>
                            </div>
                            <div class="stack" style="gap: 4px">
                                <span class="chip {{ ['tour' => 'tour', 'attraction' => 'ticket'][$item->product_type->value] ?? '' }}" style="align-self: flex-start">{{ $item->product_type->getLabel() }}</span>
                                @if ($q)
                                    <b>{{ $q['title'] }}</b>
                                    <span class="small muted">{{ $q['summary'] }}</span>
                                    @foreach ($q['extras'] as $x)<span class="small muted">+ {{ $x['qty'] }} × {{ $x['extra']->name }}</span>@endforeach
                                    @if (! empty($p['pickup_address']))<span class="small muted">From: {{ $p['pickup_address'] }}</span>@endif
                                    @if (! empty($p['dropoff_address']))<span class="small muted">To: {{ $p['dropoff_address'] }}</span>@endif
                                    @if (abs($q['sell'] - (float) $item->quoted_price) >= 0.01)
                                        <span class="small" style="color: var(--coral)">Price updated since you added it.</span>
                                    @endif
                                @else
                                    <b>No longer available</b>
                                    <span class="error">{{ $line['error'] }}</span>
                                @endif
                                <form method="post" action="{{ route('trip.remove', $item->id) }}">@csrf @method('DELETE')<button class="link-btn small">Remove</button></form>
                            </div>
                            <b class="price" style="font-size: 20px">{{ $q ? Money::format($q['sell']) : '—' }}</b>
                        </div>
                    @endforeach
                </div>
                <p class="small"><a href="{{ route('transfers.search') }}">+ Transfer</a> · <a href="{{ route('tours.index') }}">+ Tour</a> · <a href="{{ route('tickets.index') }}">+ Attraction tickets</a></p>
            @endif
        </section>

        @if ($lines !== [])
            <aside class="side">
                <div class="card summary">
                    <span class="label">Summary</span>
                    <div class="sum-row"><span>{{ count($lines) }} {{ \Illuminate\Support\Str::plural('item', count($lines)) }}</span><span>{{ Money::format($total) }}</span></div>
                    <div class="total-box"><b>Total</b><span class="price">{{ Money::format($total) }}</span></div>
                    <p class="tiny muted">Paid once, in {{ $siteCurrency->code }}. Change currency at the top of the page.</p>
                    <a href="{{ route('checkout') }}" class="btn coral block">Checkout</a>
                </div>
            </aside>
        @endif
    </div>
</div>
@endsection
