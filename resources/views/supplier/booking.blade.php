<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $item->reference }} · Supplier</title>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ filemtime(public_path('css/site.css')) }}">
</head>
<body>
@php
    $t = $item->transfer;
    $statusLabel = $item->status->getLabel();
    $confirmed = $item->status === \App\Enums\BookingItemStatus::Confirmed;
    $declined = $item->status === \App\Enums\BookingItemStatus::Rejected;
@endphp
<header class="site-header light"><div class="wrap"><span class="logo">mytransfertours</span><span class="small muted">Supplier booking</span></div></header>

<main class="wrap page" style="max-width: 760px">
    <div class="stack" style="gap: 16px">
        @if (session('status'))<div class="alert ok" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="alert error" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

        <div class="card pad stack">
            <div class="row between">
                <h1 style="font-size: 28px">{{ $item->reference }}</h1>
                <span class="chip {{ $declined ? 'tour' : '' }}">{{ $statusLabel }}</span>
            </div>
            <p class="muted">For {{ $item->supplier_name }}</p>
            <table class="list">
                <tr><td class="muted">Service</td><td><b>{{ $item->title }}</b> · {{ $item->product_type->getLabel() }}</td></tr>
                <tr><td class="muted">Date &amp; time</td><td><b>{{ $item->service_date->format('l j F Y') }}{{ $item->service_time ? ' · '.substr((string) $item->service_time, 0, 5) : '' }}</b></td></tr>
                @if ($t)
                    <tr><td class="muted">Pick-up</td><td>{{ $t->pickup_location_name }}{{ $t->pickup_address ? ' · '.$t->pickup_address : '' }}</td></tr>
                    <tr><td class="muted">Drop-off</td><td>{{ $t->dropoff_location_name }}{{ $t->dropoff_address ? ' · '.$t->dropoff_address : '' }}</td></tr>
                    <tr><td class="muted">Vehicle</td><td>{{ $t->vehicle_type_name }}</td></tr>
                    <tr><td class="muted">Passengers</td><td>{{ $t->adults }} adults, {{ $t->children }} children, {{ $t->infants }} infants · {{ $t->bags }} bags</td></tr>
                    @if ($t->flight_number)<tr><td class="muted">Flight</td><td><b>{{ $t->flight_number }}</b></td></tr>@endif
                    <tr><td class="muted">Sign</td><td>{{ $t->sign_name }}</td></tr>
                    @if ($t->driver_notes)<tr><td class="muted">Notes</td><td>{{ $t->driver_notes }}</td></tr>@endif
                @endif
                @foreach ($item->extras as $x)<tr><td class="muted">Add-on</td><td>{{ $x->quantity }} × {{ $x->name }}</td></tr>@endforeach
                <tr><td class="muted">Lead passenger</td><td>{{ $item->booking->lead_name }}{{ $item->booking->phone ? ' · '.$item->booking->phone : '' }}</td></tr>
                <tr><td class="muted">Your rate (net)</td><td><b>MYR {{ number_format((float) $item->cost_total, 2) }}</b></td></tr>
            </table>
        </div>

        @if ($locked)
            <div class="alert info">This booking can no longer be changed here. Please reply to our email if anything is wrong.</div>
        @else
            <form method="post" action="{{ route('supplier.confirm', ['item' => $item->id, 'token' => $token]) }}" class="card pad stack">
                @csrf
                <h2 style="font-size: 20px">{{ $confirmed ? 'Update details' : 'Confirm this booking' }}</h2>
                @if ($t)
                    <p class="small muted">Driver details are optional now; you can come back to this page and add them later.</p>
                    <div class="grid-3">
                        <label class="field"><span class="label">Driver name</span><input name="driver_name" value="{{ old('driver_name', $t->driver_name) }}"></label>
                        <label class="field"><span class="label">Driver phone</span><input name="driver_phone" value="{{ old('driver_phone', $t->driver_phone) }}"></label>
                        <label class="field"><span class="label">Car plate</span><input name="vehicle_plate" value="{{ old('vehicle_plate', $t->vehicle_plate) }}"></label>
                    </div>
                @endif
                <div class="grid-2">
                    <label class="field"><span class="label">Your reference (optional)</span><input name="supplier_reference" value="{{ old('supplier_reference', $item->supplier_reference) }}"></label>
                    <label class="field"><span class="label">Note to us (optional)</span><input name="supplier_notes" value="{{ old('supplier_notes', $declined ? '' : $item->supplier_notes) }}"></label>
                </div>
                <button class="btn">{{ $confirmed ? 'Save details' : 'Confirm booking' }}</button>
            </form>

            @unless ($confirmed)
                <form method="post" action="{{ route('supplier.decline', ['item' => $item->id, 'token' => $token]) }}" class="card pad stack">
                    @csrf
                    <h2 style="font-size: 20px">Can't do it?</h2>
                    <label class="field"><span class="label">Reason</span><textarea name="reason" rows="2" required placeholder="e.g. No vehicles available that day">{{ old('reason') }}</textarea></label>
                    <button class="btn ghost">Decline booking</button>
                </form>
            @endunless
        @endif
    </div>
</main>
</body>
</html>
