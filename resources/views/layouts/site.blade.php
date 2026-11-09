<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' · ' : '' }}MyTransferTours</title>
    <meta name="description" content="{{ $description ?? 'Airport transfers, tours and attraction tickets in Malaysia, in one trip and one payment.' }}">
    @if (! app()->isProduction())<meta name="robots" content="noindex">@endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ filemtime(public_path('css/site.css')) }}">
</head>
<body>
@php($dark = $darkHeader ?? false)
<header class="site-header {{ $dark ? '' : 'light' }}">
    <div class="wrap">
        <a href="{{ route('home') }}" class="logo">
            <svg width="32" height="32" viewBox="0 0 34 34" aria-hidden="true"><rect width="34" height="34" rx="10" fill="{{ $dark ? '#F2B84B' : '#0F4C3A' }}"/><path d="M10 23 L23 10 M14 10 H23 V19" stroke="{{ $dark ? '#0F4C3A' : '#F2B84B' }}" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
            mytransfertours
        </a>
        <nav class="site-nav" aria-label="Main">
            <a href="{{ route('transfers.search') }}">Transfers</a>
            <a href="{{ route('tours.index') }}">Tours</a>
            <a href="{{ route('tickets.index') }}">Attraction tickets</a>
        </nav>
        <div class="header-actions">
            <form method="post" action="{{ route('currency.set') }}" class="currency-form">
                @csrf
                <label for="currency-switch" class="sr-only">Currency</label>
                <select id="currency-switch" name="currency" onchange="this.form.submit()">
                    @foreach ($siteCurrencies as $c)
                        <option value="{{ $c->code }}" @selected($c->code === $siteCurrency->code)>{{ $c->code }}</option>
                    @endforeach
                </select>
                <noscript><button class="btn small ghost">Set</button></noscript>
            </form>
            @auth('web')
                @php($me = auth('web')->user())
                <details class="account-menu">
                    <summary class="btn small ghost" aria-label="Account menu">
                        @if ($me->avatar)
                            <img src="{{ $me->avatar }}" alt="" class="avatar" referrerpolicy="no-referrer">
                        @else
                            <span class="avatar initials" aria-hidden="true">{{ mb_strtoupper(mb_substr($me->name, 0, 1)) }}</span>
                        @endif
                        <span class="account-name">{{ \Illuminate\Support\Str::before($me->name, ' ') ?: $me->name }}</span>
                        <svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true"><path d="M2.5 4.5 6 8l3.5-3.5" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </summary>
                    <div class="account-dropdown" role="menu">
                        <div class="who"><b>{{ $me->name }}</b><span>{{ $me->email }}</span></div>
                        <a href="{{ route('account.bookings') }}" role="menuitem">My bookings</a>
                        <form method="post" action="{{ route('logout') }}">@csrf<button role="menuitem">Sign out</button></form>
                    </div>
                </details>
            @else
                <a href="{{ route('manage.findForm') }}" class="btn small ghost">Find my booking</a>
                <a href="{{ route('login') }}" class="btn small ghost">Sign in</a>
            @endauth
            <a href="{{ route('trip') }}" class="btn small {{ $dark ? 'sun' : '' }}">My trip · {{ $cartCount }}</a>
        </div>
    </div>
</header>

<main>
    @if (session('status'))
        <div class="wrap" style="padding-top: 16px"><div class="alert ok" role="status">{{ session('status') }}</div></div>
    @endif
    @yield('content')
</main>

<footer class="site-footer">
    <div class="wrap">
        <span class="logo" style="color: #fff; font-size: 18px">mytransfertours</span>
        <span>Malaysia first. More countries soon.</span>
        <span>
            @auth('web')
                <form method="post" action="{{ route('logout') }}" style="display: inline">@csrf<button class="link-btn" style="color: #C9D3CE">Sign out</button></form>
            @endauth
        </span>
    </div>
</footer>
<script>
    // Close the account menu when clicking elsewhere or pressing Escape.
    document.addEventListener('click', e => document.querySelectorAll('details.account-menu[open]').forEach(d => { if (!d.contains(e.target)) d.removeAttribute('open'); }));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('details.account-menu[open]').forEach(d => d.removeAttribute('open')); });
</script>
</body>
</html>
