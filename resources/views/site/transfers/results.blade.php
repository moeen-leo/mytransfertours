@extends('layouts.site', ['title' => 'Transfers'])

@php use App\Support\Money; @endphp

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
        <label class="field"><span class="label">Adults</span><input type="number" name="adults" value="{{ $search['adults'] }}" min="1" max="50"></label>
        <label class="field"><span class="label">Children</span><input type="number" name="children" value="{{ $search['children'] }}" min="0" max="50"></label>
        <label class="field"><span class="label">Bags</span><input type="number" name="bags" value="{{ $search['bags'] }}" min="0" max="60"></label>
        <button class="btn coral" style="min-height: 58px">Update</button>
    </form>
</div>

<div class="wrap page stack" style="gap: 16px">
    <div class="steps"><b>1. Choose a vehicle</b> › 2. Trip details › 3. My trip › 4. Checkout</div>
    <div class="row between" style="align-items: end">
        <div>
            <h1 style="font-size: 32px">{{ $from->short_name ?: $from->name }} → {{ $to->short_name ?: $to->name }}</h1>
            <p class="muted small" style="margin-top: 6px">{{ $date->format('D j M Y') }} · {{ $search['time'] }} · {{ $pax }} {{ \Illuminate\Support\Str::plural('passenger', $pax) }} · {{ $search['bags'] }} {{ \Illuminate\Support\Str::plural('bag', (int) $search['bags']) }}</p>
        </div>
        @if ($route)
            <p class="small muted">@if ($route->distance_km)≈ {{ rtrim(rtrim(number_format((float) $route->distance_km, 1), '0'), '.') }} km · @endif @if ($route->duration_minutes)~{{ $route->duration_minutes }} min · @endif price is per vehicle</p>
        @endif
    </div>

    @if ($errors->any())
        <div class="alert error" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    @if ($tooSoon)
        <div class="alert error" role="alert">Transfers must be booked at least {{ \App\Services\TransferQuoter::MIN_HOURS_BEFORE }} hours before pick-up. Please choose a later time, or contact us for urgent rides.</div>
    @endif

    @if (! $route)
        <div class="card pad stack">
            <h2 style="font-size: 22px">We don't run this route yet</h2>
            <p class="muted">Try a nearby pick-up or drop-off point, or contact us and we'll arrange it for you.</p>
        </div>
    @elseif ($options->isEmpty())
        <div class="card pad"><p>No vehicles are available on this date. Please try another date.</p></div>
    @else
        @foreach ($options as $opt)
            @php($v = $opt['vehicle'])
            <div class="card vehicle {{ $opt['fits'] ? '' : 'unfit' }}">
                <div class="pic">
                    @if ($v->image)
                        <img src="{{ route('media', ['path' => $v->image]) }}" alt="{{ $v->name }}" loading="lazy">
                    @else
                        <svg width="76" height="34" viewBox="0 0 76 34" aria-hidden="true"><path d="M6 24 L10 13 Q12 8 18 8 H50 Q56 8 60 13 L68 18 Q72 19 72 24 V26 H6 Z" fill="#0F4C3A"/><circle cx="20" cy="27" r="5" fill="#10231C"/><circle cx="56" cy="27" r="5" fill="#10231C"/></svg>
                    @endif
                </div>
                <div class="stack" style="gap: 6px">
                    <div class="row" style="gap: 8px"><b style="font-size: 18px">{{ $v->name }}</b>@if ($loop->first && $opt['fits'])<span class="chip">Best value</span>@endif</div>
                    <span class="small muted">{{ $v->example_models ? $v->example_models.' · ' : '' }}up to {{ $v->max_pax }} passengers · {{ $v->max_bags }} large bags</span>
                    @if (! empty($v->features))
                        <span class="small" style="color: var(--green)">@foreach ($v->features as $f)✓ {{ $f }}&nbsp;&nbsp;@endforeach</span>
                    @endif
                    @if (! $opt['fits'])<span class="error">Too small for {{ $pax }} passengers / {{ $search['bags'] }} bags</span>@endif
                </div>
                <div class="stack" style="align-items: flex-end; gap: 8px">
                    <span class="price">{{ Money::format($opt['sell']) }}</span>
                    @if ($opt['fits'] && ! $tooSoon)
                        <a class="btn small" href="{{ route('transfers.details', array_merge($search, ['vehicle_type_id' => $v->id])) }}">Select</a>
                    @endif
                </div>
            </div>
        @endforeach
        <p class="small muted">Includes tolls and taxes. Free waiting {{ $route->free_waiting_minutes }} min. Free cancellation until {{ $route->cancellation_hours }} hours before pick-up.</p>
    @endif
</div>
@endsection
