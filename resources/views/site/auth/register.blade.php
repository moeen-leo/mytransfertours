@extends('layouts.site', ['title' => 'Create account'])

@section('content')
<div class="wrap page" style="max-width: 520px">
    <form method="post" action="{{ route('register') }}" class="card pad stack" style="gap: 16px">
        @csrf
        <h1 style="font-size: 30px">Create your account</h1>
        <p class="muted small">See all your bookings and invoices in one place.</p>
        @if ($errors->any())<div class="alert error" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
        @include('site.auth._google', ['or' => 'sign up with email'])
        <label class="field"><span class="label">Full name</span><input name="name" value="{{ old('name') }}" autocomplete="name" required autofocus></label>
        <label class="field"><span class="label">Email</span><input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></label>
        <label class="field"><span class="label">Mobile / WhatsApp (optional)</span><input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel"></label>
        <div class="grid-2">
            <label class="field"><span class="label">Password</span><input type="password" name="password" autocomplete="new-password" minlength="10" required></label>
            <label class="field"><span class="label">Repeat password</span><input type="password" name="password_confirmation" autocomplete="new-password" required></label>
        </div>
        <p class="tiny muted">At least 10 characters.</p>
        <button class="btn block">Create account</button>
        <p class="small muted" style="text-align: center">Already have one? <a href="{{ route('login') }}">Sign in</a></p>
    </form>
</div>
@endsection
