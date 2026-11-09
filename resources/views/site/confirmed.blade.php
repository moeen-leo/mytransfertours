@extends('layouts.site', ['title' => 'Booking confirmed'])

@php use App\Support\Money; @endphp

@section('content')
<div class="wrap page" style="max-width: 760px">
    <div class="card pad stack" style="gap: 18px">
        @php($pending = $booking->status === \App\Enums\BookingStatus::PendingPayment)
        <span class="chip" style="align-self: flex-start">{{ $pending ? 'Confirming payment' : ($booking->payment_status === \App\Enums\PaymentStatus::Paid ? 'Paid' : 'Booked') }}</span>
        <h1 style="font-size: 34px">Thank you, {{ $booking->lead_first_name }}!</h1>
        @if ($pending)
            <div class="alert info">We're waiting for Stripe to confirm your payment. This usually takes a few seconds. <a href="{{ request()->fullUrl() }}">Refresh</a></div>
        @endif
        <p class="muted">Your booking reference is <b style="color: var(--ink)">{{ $booking->reference }}</b>. We're confirming the details with our local partners now, and you'll hear from us at {{ $booking->email }}.</p>

        <div>
            @foreach ($booking->items as $item)
                <div class="trip-item">
                    <div><b>{{ $item->service_date->format('D j M') }}</b><div class="small muted">{{ substr((string) $item->service_time, 0, 5) }}</div></div>
                    <div class="stack" style="gap: 4px">
                        <b>{{ $item->title }}</b>
                        @if ($item->transfer)<span class="small muted">{{ $item->transfer->vehicle_type_name }} · {{ $item->transfer->adults + $item->transfer->children + $item->transfer->infants }} passengers{{ $item->transfer->flight_number ? ' · flight '.$item->transfer->flight_number : '' }}</span>@endif
                        @if ($item->tour)<span class="small muted">{{ $item->tour->option === 'private' ? 'Private · '.$item->tour->vehicle_type_name : 'Seat-in-coach' }} · {{ $item->tour->adults + $item->tour->children }} people</span>@endif
                        @if ($item->tickets->isNotEmpty())<span class="small muted">{{ $item->tickets->map(fn ($t) => $t->quantity.' × '.$t->ticket_type_name)->join(', ') }}</span>@endif
                        <span class="small muted">{{ $item->reference }}</span>
                    </div>
                    <b>{{ Money::formatIn((float) $item->selling_total, $booking->currency) }}</b>
                </div>
            @endforeach
        </div>

        <div class="total-box"><b>Total</b><span class="price">{{ Money::formatIn((float) $booking->total, $booking->currency) }}</span></div>

        @if ($booking->payment_method === \App\Enums\PaymentMethod::Credit)
            <div class="alert info">Charged to your account. Invoice {{ $booking->invoice_number }} is due on {{ $booking->payment_due_at?->format('j M Y') }}.</div>
        @endif

        <div class="row">
            <a href="{{ $booking->manageUrl() }}" class="btn">Manage my booking</a>
            @auth('web')
                <a href="{{ route('account.bookings') }}" class="btn ghost">My bookings</a>
                @if ($booking->invoice_number)<a href="{{ route('invoices.show', $booking) }}" class="btn ghost" target="_blank" rel="noopener">View invoice</a>@endif
            @endauth
            <a href="{{ route('home') }}" class="btn ghost">Back to home</a>
        </div>
    </div>
</div>
@endsection
