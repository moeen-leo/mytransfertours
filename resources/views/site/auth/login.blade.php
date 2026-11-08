@extends('layouts.site', ['title' => 'Sign in'])

@section('content')
<div class="wrap page" style="max-width: 480px">
    <form method="post" action="{{ route('login') }}" class="card pad stack" style="gap: 16px">
        @csrf
        <h1 style="font-size: 30px">Sign in</h1>
        @if ($errors->any())<div class="alert error" role="alert">{{ $errors->first() }}</div>@endif
        <label class="field"><span class="label">Email</span><input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus></label>
        <label class="field"><span class="label">Password</span><input type="password" name="password" autocomplete="current-password" required></label>
        <label class="check"><input type="checkbox" name="remember" value="1" checked> Keep me signed in</label>
        <button class="btn block">Sign in</button>
        <p class="small muted" style="text-align: center">New here? <a href="{{ route('register') }}">Create an account</a></p>
    </form>
</div>
@endsection
