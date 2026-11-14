@extends('layouts.site', ['title' => 'Find my booking'])

@section('content')
<div class="wrap page" style="max-width: 520px">
    <form method="post" action="{{ route('manage.find') }}" class="card pad stack" style="gap: 16px">
        @csrf
        <h1 style="font-size: 30px">Find my booking</h1>
        <p class="muted small">Enter the reference from your confirmation email (it starts with MTT-) and the email address you booked with.</p>
        @if ($errors->any())<div class="alert error" role="alert">{{ $errors->first() }}</div>@endif
        <label class="field"><span class="label">Booking reference</span><input name="reference" value="{{ old('reference') }}" placeholder="MTT-10004" autocomplete="off" required autofocus style="text-transform: uppercase"></label>
        <label class="field"><span class="label">Email</span><input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></label>
        <button class="btn block">Show my booking</button>
        <p class="small muted" style="text-align: center">Have an account? <a href="{{ route('login') }}">Sign in</a> to see all your bookings.</p>
    </form>
</div>
@endsection
