@extends('layouts.site', ['title' => $page->title, 'description' => $page->meta_description])

@section('content')
<div class="wrap page" style="max-width: 820px">
    <p class="label" style="margin: 0">{{ config('app.name', 'MyTransferTours') }}</p>
    <h1 style="font-size: clamp(30px, 5vw, 42px); margin: 6px 0 4px">{{ $page->title }}</h1>
    @if (in_array($page->slug, ['terms', 'privacy-policy', 'cancellation-policy'], true))
        <p class="small muted">Last updated {{ $page->updated_at->timezone('Asia/Kuala_Lumpur')->format('j F Y') }}</p>
    @endif

    @if ($page->slug === 'contact-us')
        <div class="contact-cards">
            @if ($company['email'])
                <a class="card pad contact-card" href="mailto:{{ $company['email'] }}">
                    <span class="label">Email</span><b>{{ $company['email'] }}</b><span class="small muted">We usually reply within a few hours.</span>
                </a>
            @endif
            @if ($company['phone'])
                @php($wa = preg_replace('/\D+/', '', $company['phone']))
                <a class="card pad contact-card" href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener">
                    <span class="label">Phone / WhatsApp</span><b>{{ $company['phone'] }}</b><span class="small muted">Message us on WhatsApp.</span>
                </a>
            @endif
            <a class="card pad contact-card" href="{{ route('manage.findForm') }}">
                <span class="label">Your booking</span><b>Find my booking</b><span class="small muted">See or change it online.</span>
            </a>
        </div>
    @endif

    <article class="prose">{{ $page->html() }}</article>
</div>
@endsection
