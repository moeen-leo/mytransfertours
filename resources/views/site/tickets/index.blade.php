@extends('layouts.site', ['title' => 'Attraction tickets in Malaysia', 'description' => 'Skip the queue with attraction tickets for Malaysia\'s top sights. Choose your date, pay once and get your tickets by email.'])

@php use App\Support\Money; @endphp

@section('content')
<div class="wrap page stack" style="gap: 20px">
    <div class="row between" style="align-items: end">
        <div><span class="label">Attraction tickets</span><h1 style="font-size: 38px">Skip the ticket queue</h1><p class="muted" style="margin-top: 6px">Pick a date and how many tickets. Your e-ticket arrives by email.</p></div>
        @if ($cities->count() > 1)
            <div class="row">
                <a class="tab {{ $city ? '' : 'on' }}" href="{{ route('tickets.index') }}">All</a>
                @foreach ($cities as $c)<a class="tab {{ $city === $c->slug ? 'on' : '' }}" href="{{ route('tickets.index', ['city' => $c->slug]) }}">{{ $c->name }}</a>@endforeach
            </div>
        @endif
    </div>

    @if ($attractions->isEmpty())
        <div class="card pad">Attraction tickets are being added. Please check back soon.</div>
    @else
        <div class="tiles">
            @foreach ($attractions as $a)
                @php($from = $a->ticketTypes->where('is_active', true)->min('selling_price'))
                <a class="card tile" href="{{ route('tickets.show', $a) }}" style="overflow: hidden">
                    <div class="ph" style="height: 200px; padding: 0; border-radius: 0">@if (! empty($a->images))<img src="{{ route('media', ['path' => $a->images[0]]) }}" alt="{{ $a->title }}" loading="lazy">@else<span style="padding: 12px">{{ $a->title }}</span>@endif</div>
                    <div class="stack" style="gap: 6px; padding: 16px 18px 20px">
                        <span class="small muted">{{ $a->city?->name }}</span>
                        <b style="font-size: 18px">{{ $a->title }}</b>
                        @if ($a->summary)<span class="small muted">{{ \Illuminate\Support\Str::limit($a->summary, 110) }}</span>@endif
                        @if ($from)<span class="small">from <b>{{ Money::format((float) $from) }}</b> / person</span>@endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
