@extends('layouts.site', ['title' => 'Checkout'])

@php
    use App\Support\Money;
    $names = explode(' ', (string) ($user?->name ?? ''), 2);
    $hasCredit = (bool) $creditCurrency;
    $creditFits = $hasCredit && ($creditAvailable === null || $creditTotal <= $creditAvailable);
    $defaultMethod = old('payment_method', $hasCredit && $creditFits ? 'credit' : ($cardEnabled ? 'card' : ''));
@endphp

@section('content')
<div class="wrap page">
    <div class="steps" style="margin-bottom: 16px">1. Choose › 2. Details › 3. My trip › <b>4. Checkout</b></div>

    <form method="post" action="{{ route('checkout.store') }}" class="layout">
        @csrf
        <section class="main">
            <h1 style="font-size: 34px">Checkout</h1>

            @if ($errors->any())
                <div class="alert error" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            @endif

            @guest
                <div class="card pad row between">
                    <div><b>Have an account?</b><p class="small muted">Sign in to see your bookings later and use your saved details.</p></div>
                    <div class="row"><a class="btn small ghost" href="{{ route('login') }}">Sign in</a><a class="btn small ghost" href="{{ route('register') }}">Create account</a></div>
                </div>
            @endguest

            <div class="card pad stack">
                <h2 style="font-size: 20px">Lead traveller</h2>
                <div class="grid-2">
                    <label class="field @error('first_name') has-error @enderror"><span class="label">First name</span><input name="first_name" value="{{ old('first_name', $names[0] ?? '') }}" autocomplete="given-name" required></label>
                    <label class="field @error('last_name') has-error @enderror"><span class="label">Last name</span><input name="last_name" value="{{ old('last_name', $names[1] ?? '') }}" autocomplete="family-name" required></label>
                    <label class="field @error('email') has-error @enderror"><span class="label">Email (booking confirmation goes here)</span><input type="email" name="email" value="{{ old('email', $user?->email) }}" autocomplete="email" required></label>
                    <label class="field @error('phone') has-error @enderror"><span class="label">Mobile / WhatsApp</span><input type="tel" name="phone" value="{{ old('phone', $user?->phone) }}" placeholder="+60 12 345 6789" autocomplete="tel" required></label>
                    <label class="field span-2"><span class="label">Notes for us (optional)</span><textarea name="notes" rows="2" maxlength="1000">{{ old('notes') }}</textarea></label>
                </div>
            </div>

            <div class="card pad stack">
                <h2 style="font-size: 20px">Payment</h2>

                @if ($hasCredit)
                    <label class="check card pad" style="border-color: {{ $creditFits ? 'var(--green)' : 'var(--line)' }}">
                        <input type="radio" name="payment_method" value="credit" @checked($defaultMethod === 'credit') @disabled(! $creditFits)>
                        <span class="stack" style="gap: 4px">
                            <b>Confirm on account</b>
                            <span class="small muted">
                                Total {{ $creditCurrency->format($creditTotal) }}, invoiced to {{ $user->company_name ?: $user->name }}, due in {{ $user->credit_terms_days }} days.
                                Available credit: {{ $creditAvailable === null ? 'no limit' : $creditCurrency->format($creditAvailable) }}.
                            </span>
                            @unless ($creditFits)<span class="error">This trip is more than your available credit.</span>@endunless
                        </span>
                    </label>
                @endif

                <label class="check card pad">
                    <input type="radio" name="payment_method" value="card" @checked($defaultMethod === 'card') @disabled(! $cardEnabled)>
                    <span class="stack" style="gap: 4px">
                        <b>Pay by card</b>
                        <span class="small muted">
                            @if ($cardEnabled)
                                Visa, Mastercard, Amex, Apple Pay or Google Pay on Stripe's secure page. You'll be charged {{ Money::format($total) }}.
                            @else
                                Card payment is being connected. Please contact us to book, or sign in with a credit account.
                            @endif
                        </span>
                    </span>
                </label>

                <label class="check @error('terms') error @enderror"><input type="checkbox" name="terms" value="1" @checked(old('terms')) required> <span>I agree to the booking terms and each item's cancellation policy.</span></label>
            </div>
        </section>

        <aside class="side">
            <div class="card summary">
                <span class="label">Your trip</span>
                @foreach ($lines as $line)
                    <div class="sum-row"><span>{{ $line['quote']['title'] }}<br><span class="tiny muted">{{ $line['quote']['date']->format('D j M') }}{{ $line['quote']['time'] ? ' · '.$line['quote']['time'] : '' }} · {{ $line['quote']['summary'] }}</span></span><span>{{ Money::format($line['quote']['sell']) }}</span></div>
                @endforeach
                <div class="total-box"><b>Total</b><span class="price">{{ Money::format($total) }}</span></div>
                @if ($hasCredit && $creditCurrency->code !== $siteCurrency->code)
                    <p class="tiny muted">On account, this is invoiced in your account currency: {{ $creditCurrency->format($creditTotal) }}.</p>
                @endif
                <button class="btn coral block big" @disabled(! $cardEnabled && ! $creditFits)>Confirm booking</button>
                <a href="{{ route('trip') }}" class="small" style="text-align: center">← Back to my trip</a>
            </div>
        </aside>
    </form>
</div>
@endsection
