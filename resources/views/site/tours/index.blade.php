@extends('layouts.site', \App\Support\PageCopy::seo('tours'))

@php use App\Support\Money; @endphp

@section('content')
<div class="wrap page stack" style="gap: 20px">
    <div class="row between" style="align-items: end">
        @php $copy = \App\Support\PageCopy::for('tours'); @endphp
        <div>@if ($copy['label'] !== '')<span class="label">{{ $copy['label'] }}</span>@endif@if ($copy['heading'] !== '')<h1 style="font-size: 38px">{{ \App\Support\PageCopy::heading('tours') }}</h1>@endif@if ($copy['subheading'] !== '')<p class="muted" style="margin-top: 6px">{{ $copy['subheading'] }}</p>@endif</div>
        @if ($cities->count() > 1)
            <div class="row">
                <a class="tab {{ $city ? '' : 'on' }}" href="{{ route('tours.index') }}">All</a>
                @foreach ($cities as $c)<a class="tab {{ $city === $c->slug ? 'on' : '' }}" href="{{ route('tours.index', ['city' => $c->slug]) }}">{{ $c->name }}</a>@endforeach
            </div>
        @endif
    </div>

    @if ($tours->isEmpty())
        <div class="card pad">Tours are being added. Please check back soon.</div>
    @else
        <div class="tiles">
            @foreach ($tours as $tour)
                @php
                    $sic = $tour->has_sic ? $tour->sicPrices->where('pax_type', 'adult')->min('selling_price') : null;
                    $priv = $tour->has_private ? $tour->privatePrices->min('selling_price') : null;
                @endphp
                <a class="card tile" href="{{ route('tours.show', $tour) }}" style="overflow: hidden">
                    <div class="ph" style="height: 200px; padding: 0; border-radius: 0">@if (! empty($tour->images))<img src="{{ route('media', ['path' => $tour->images[0]]) }}" alt="{{ $tour->title }}" loading="lazy">@else<span style="padding: 12px">{{ $tour->title }}</span>@endif</div>
                    <div class="stack" style="gap: 6px; padding: 16px 18px 20px">
                        <span class="small muted">{{ $tour->city?->name }} · {{ intdiv($tour->duration_minutes, 60) }}h{{ $tour->duration_minutes % 60 ? ' '.($tour->duration_minutes % 60).'m' : '' }}</span>
                        <b style="font-size: 18px">{{ $tour->title }}</b>
                        @if ($tour->summary)<span class="small muted">{{ \Illuminate\Support\Str::limit($tour->summary, 110) }}</span>@endif
                        <span class="small">
                            @if ($sic)from <b>{{ Money::format((float) $sic) }}</b> / person @endif
                            @if ($sic && $priv) · @endif
                            @if ($priv)private from <b>{{ Money::format((float) $priv) }}</b> / vehicle @endif
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
