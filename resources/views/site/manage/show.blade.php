@extends('layouts.site', ['title' => 'Booking '.$booking->reference])

@php
    use App\Support\Money;
    use App\Enums\BookingItemStatus;
    $statusText = [
        'pending' => 'Being sent to our partner',
        'sent_to_supplier' => 'Waiting for partner confirmation',
        'confirmed' => 'Confirmed',
        'rejected' => 'We\'re arranging an alternative',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];
    $pendingChange = $booking->changeRequests->where('status', 'pending');
@endphp

@section('content')
<div class="wrap page stack" style="gap: 18px; max-width: 920px">
    <div class="row between" style="align-items: end">
        <div>
            <span class="label">Booking</span>
            <h1 style="font-size: 34px">{{ $booking->reference }}</h1>
            <p class="muted small">{{ $booking->lead_name }} · {{ $booking->email }} · booked {{ $booking->created_at->format('j M Y') }}</p>
        </div>
        <div class="row">
            <span class="chip">{{ $booking->status->getLabel() }}</span>
            <span class="chip ticket">{{ $booking->payment_status->getLabel() }}</span>
            @if ($booking->invoice_number)<a class="btn small ghost" href="{{ route('manage.invoice', $booking->manage_token) }}" target="_blank" rel="noopener">Invoice</a>@endif
        </div>
    </div>

    @if ($errors->any())<div class="alert error" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

    @if ($booking->status === \App\Enums\BookingStatus::PendingPayment)
        <div class="alert info">We're still waiting for your payment to be confirmed.</div>
    @elseif ($booking->status === \App\Enums\BookingStatus::PaymentFailed)
        <div class="alert error">This booking wasn't paid, so it isn't active. You can book again any time.</div>
    @endif

    @foreach ($booking->items as $item)
        @php($t = $item->transfer)
        <div class="card pad stack" style="gap: 14px">
            <div class="row between">
                <div>
                    <span class="chip" style="margin-bottom: 6px">{{ $item->product_type->getLabel() }}</span>
                    <h2 style="font-size: 22px; margin-top: 6px">{{ $item->title }}</h2>
                    <p class="muted">{{ $item->service_date->format('l j F Y') }}{{ $item->service_time ? ' · '.substr((string) $item->service_time, 0, 5) : '' }} · {{ $item->reference }}</p>
                </div>
                <div style="text-align: right">
                    <b class="price" style="font-size: 20px">{{ Money::formatIn((float) $item->selling_total, $booking->currency) }}</b>
                    <p class="small {{ $item->status === BookingItemStatus::Confirmed ? '' : 'muted' }}" style="{{ $item->status === BookingItemStatus::Confirmed ? 'color: var(--green); font-weight: 600' : '' }}">{{ $statusText[$item->status->value] ?? $item->status->getLabel() }}</p>
                </div>
            </div>

            @if ($t)
                <table class="list">
                    <tr><td class="muted" style="width: 170px">Pick-up</td><td>{{ $t->pickup_location_name }}{{ $t->pickup_address ? ' · '.$t->pickup_address : '' }}</td></tr>
                    <tr><td class="muted">Drop-off</td><td>{{ $t->dropoff_location_name }}{{ $t->dropoff_address ? ' · '.$t->dropoff_address : '' }}</td></tr>
                    <tr><td class="muted">Vehicle</td><td>{{ $t->vehicle_type_name }} · {{ $t->adults + $t->children + $t->infants }} passengers · {{ $t->bags }} bags</td></tr>
                    @if ($t->flight_number)<tr><td class="muted">Flight</td><td>{{ $t->flight_number }}</td></tr>@endif
                    <tr><td class="muted">Name on sign</td><td>{{ $t->sign_name }}</td></tr>
                    @if ($t->driver_name)<tr><td class="muted">Your driver</td><td><b>{{ collect([$t->driver_name, $t->driver_phone, $t->vehicle_plate])->filter()->join(' · ') }}</b></td></tr>@endif
                    @foreach ($item->extras as $x)<tr><td class="muted">Add-on</td><td>{{ $x->quantity }} × {{ $x->name }}</td></tr>@endforeach
                </table>
            @endif

            @if ($editable[$item->id] ?? false)
                <details>
                    <summary class="btn small ghost" style="display: inline-flex">Update details</summary>
                    <form method="post" action="{{ route('manage.update', [$booking->manage_token, $item->id]) }}" class="stack" style="margin-top: 14px">
                        @csrf
                        <div class="grid-2">
                            <label class="field"><span class="label">Mobile / WhatsApp</span><input name="phone" value="{{ old('phone', $booking->phone) }}" required></label>
                            @if ($t)
                                <label class="field"><span class="label">Flight number</span><input name="flight_number" value="{{ old('flight_number', $t->flight_number) }}" placeholder="e.g. MH 123"></label>
                                @if ($t->pickupLocation?->requires_address || $t->pickup_address)
                                    <label class="field"><span class="label">Pick-up hotel / address</span><input name="pickup_address" value="{{ old('pickup_address', $t->pickup_address) }}"></label>
                                @endif
                                @if ($t->dropoffLocation?->requires_address || $t->dropoff_address)
                                    <label class="field"><span class="label">Drop-off hotel / address</span><input name="dropoff_address" value="{{ old('dropoff_address', $t->dropoff_address) }}"></label>
                                @endif
                                <label class="field"><span class="label">Name on sign</span><input name="sign_name" value="{{ old('sign_name', $t->sign_name) }}" maxlength="60"></label>
                                <label class="field span-2"><span class="label">Notes for the driver</span><textarea name="driver_notes" rows="2" maxlength="500">{{ old('driver_notes', $t->driver_notes) }}</textarea></label>
                            @endif
                        </div>
                        <button class="btn" style="align-self: flex-start">Save changes</button>
                    </form>
                </details>
            @elseif ($booking->status->isActive())
                <p class="small muted">Details can be changed online until {{ $cutoffHours }} hours before the start. After that, please contact us.</p>
            @endif
        </div>
    @endforeach

    @if ($booking->status->isActive())
        <div class="card pad stack">
            <h2 style="font-size: 20px">Need a different date or time?</h2>
            @if ($pendingChange->isNotEmpty())
                <div class="alert info">We've received your change request and will email you soon.</div>
            @else
                <p class="small muted">Send us a request and we'll check with our partner. A new date can change the price; we'll confirm before anything changes.</p>
                <form method="post" action="{{ route('manage.change', $booking->manage_token) }}" class="grid-3">
                    @csrf
                    <label class="field span-2"><span class="label">Which part</span>
                        <select name="booking_item_id">@foreach ($booking->items as $item)<option value="{{ $item->id }}">{{ $item->title }} · {{ $item->service_date->format('j M') }}</option>@endforeach</select>
                    </label>
                    <label class="field"><span class="label">New date</span><input type="date" name="requested_date" min="{{ now()->format('Y-m-d') }}" required></label>
                    <label class="field"><span class="label">New time (optional)</span><input type="time" name="requested_time"></label>
                    <label class="field span-2"><span class="label">Anything else? (optional)</span><input name="message" maxlength="1000"></label>
                    <button class="btn">Send request</button>
                </form>
            @endif
        </div>
    @endif

    <div class="card pad row between">
        <div><span class="label">Total</span><p class="price">{{ Money::formatIn((float) $booking->total, $booking->currency) }}</p></div>
        <p class="small muted">{{ $booking->payment_method->getLabel() }}{{ $booking->payment_due_at && $booking->payment_status === \App\Enums\PaymentStatus::Unpaid ? ' · due '.$booking->payment_due_at->format('j M Y') : '' }}</p>
    </div>
    <p class="small muted">Keep this page's link private: anyone with it can see this booking.</p>
</div>
@endsection
