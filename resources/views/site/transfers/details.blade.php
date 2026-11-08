@extends('layouts.site', ['title' => 'Trip details'])

@php
    use App\Support\Money;
    $from = $quote['from']; $to = $quote['to']; $v = $quote['vehicle']; $p = $quote['payload'];
@endphp

@section('content')
<div class="wrap page">
    <div class="steps" style="margin-bottom: 16px">1. Choose a vehicle › <b>2. Trip details</b> › 3. My trip › 4. Checkout</div>

    <form method="post" action="{{ route('transfers.add') }}" class="layout">
        @csrf
        @foreach (['from_id', 'to_id', 'date', 'time', 'adults', 'children', 'infants', 'bags', 'vehicle_type_id'] as $k)
            <input type="hidden" name="{{ $k }}" value="{{ $p[$k] }}">
        @endforeach

        <section class="main">
            <h1 style="font-size: 32px">{{ $quote['title'] }}</h1>
            <p class="muted">{{ $quote['date']->format('D j M Y') }} · {{ $quote['time'] }} · {{ $v->name }} · {{ $p['adults'] + $p['children'] + $p['infants'] }} passengers</p>

            @if ($errors->any())
                <div class="alert error" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            @endif

            <div class="card pad stack">
                <h2 style="font-size: 20px">Pick-up &amp; drop-off</h2>
                @if ($from->requires_flight_number)
                    <label class="field @error('flight_number') has-error @enderror"><span class="label">Flight number</span>
                        <input name="flight_number" value="{{ old('flight_number') }}" placeholder="e.g. MH 123" autocomplete="off" required>
                    </label>
                    <p class="tiny muted">Your driver tracks the flight and waits if it's late.</p>
                @endif
                @if ($from->requires_address)
                    <label class="field @error('pickup_address') has-error @enderror"><span class="label">Pick-up hotel or address ({{ $from->short_name ?: $from->name }})</span>
                        <input name="pickup_address" value="{{ old('pickup_address') }}" placeholder="Hotel name or full address" required>
                    </label>
                @endif
                @if ($to->requires_address)
                    <label class="field @error('dropoff_address') has-error @enderror"><span class="label">Drop-off hotel or address ({{ $to->short_name ?: $to->name }})</span>
                        <input name="dropoff_address" value="{{ old('dropoff_address') }}" placeholder="Hotel name or full address" required>
                    </label>
                @endif
                <label class="field"><span class="label">Name on the pick-up sign (optional)</span>
                    <input name="sign_name" value="{{ old('sign_name') }}" placeholder="Defaults to the lead traveller's name" maxlength="60">
                </label>
                <label class="field"><span class="label">Notes for the driver (optional)</span>
                    <textarea name="driver_notes" rows="2" maxlength="500" placeholder="Anything we should know?">{{ old('driver_notes') }}</textarea>
                </label>
            </div>

            @if ($extras->isNotEmpty())
                <div class="card pad stack">
                    <h2 style="font-size: 20px">Add-ons</h2>
                    @foreach ($extras as $extra)
                        <div class="row between">
                            <div><b>{{ $extra->name }}</b> <span class="muted small">· {{ Money::format((float) $extra->selling_price) }} each</span>@if ($extra->description)<br><span class="small muted">{{ $extra->description }}</span>@endif</div>
                            <label class="stepper"><span class="sr-only">{{ $extra->name }} quantity</span>
                                <input type="number" name="extras[{{ $extra->id }}]" min="0" max="{{ $extra->max_quantity }}" value="{{ old('extras.'.$extra->id, 0) }}">
                            </label>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <aside class="side">
            <div class="card summary">
                <span class="label">Your transfer</span>
                @if ($v->image)<div class="ph" style="height: 140px; padding: 0"><img src="{{ route('media', ['path' => $v->image]) }}" alt="{{ $v->name }}"></div>@endif
                <div class="sum-row"><span>{{ $v->name }}</span><span>{{ Money::format($quote['vehicle_sell']) }}</span></div>
                <div class="sum-row muted"><span>Add-ons</span><span>chosen above</span></div>
                <div class="total-box"><b>From</b><span class="price">{{ Money::format($quote['vehicle_sell']) }}</span></div>
                <p class="tiny muted">Price per vehicle, including tolls &amp; taxes. Free cancellation until {{ $quote['route']->cancellation_hours }} hours before pick-up.</p>
                <button class="btn coral block">Add to my trip</button>
                <a href="{{ route('transfers.search', $search) }}" class="small" style="text-align: center">← Change vehicle</a>
            </div>
        </aside>
    </form>
</div>
@endsection
