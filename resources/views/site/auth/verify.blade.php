@extends('layouts.site', ['title' => 'Confirm your email'])

@section('content')
<div class="wrap page" style="max-width: 520px">
    <div class="card pad stack" style="gap: 16px">
        <h1 style="font-size: 28px">Confirm your email</h1>
        <p class="muted">We've sent a link to <b>{{ auth('web')->user()->email }}</b>. Click it to confirm your address; bookings you made as a guest with this email will then appear in your account.</p>
        <form method="post" action="{{ route('verification.send') }}">@csrf<button class="btn">Send the link again</button></form>
        <a href="{{ route('account.bookings') }}" class="small">Go to my bookings</a>
    </div>
</div>
@endsection
