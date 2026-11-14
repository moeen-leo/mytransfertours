@php
    use App\Enums\BookingItemStatus;
    $people = function ($adults, $children = 0, $infants = 0) {
        $p = [$adults.' adult'.($adults == 1 ? '' : 's')];
        if ($children) { $p[] = $children.' child'.($children == 1 ? '' : 'ren'); }
        if ($infants) { $p[] = $infants.' infant'.($infants == 1 ? '' : 's'); }
        return implode(', ', $p);
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Voucher {{ $booking->reference }}</title>
<style>
    @page { margin: 26px 30px 40px; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #1d2b25; line-height: 1.45; margin: 0; }
    table { border-collapse: collapse; width: 100%; }
    td { vertical-align: top; }
    .brand { font-size: 19px; font-weight: bold; color: #0F4C3A; }
    .muted { color: #6b7a73; }
    .small { font-size: 9.5px; }
    .label { font-size: 8.5px; text-transform: uppercase; letter-spacing: .6px; color: #6b7a73; }
    .ref { font-size: 22px; font-weight: bold; color: #0F4C3A; letter-spacing: .5px; }
    .head { border-bottom: 3px solid #0F4C3A; padding-bottom: 10px; }
    .guest { background: #F3F6F4; border-radius: 6px; margin-top: 14px; }
    .guest td { padding: 9px 12px; }
    .item { border: 1px solid #DCE3DF; border-radius: 6px; margin-top: 14px; page-break-inside: avoid; }
    .item-head { background: #0F4C3A; color: #fff; padding: 8px 12px; border-radius: 5px 5px 0 0; }
    .item-head .title { font-size: 13px; font-weight: bold; }
    .item-body { padding: 10px 12px; }
    .photo { width: 170px; padding-right: 12px; }
    .photo img { width: 170px; border-radius: 4px; }
    .facts td { padding: 3px 0; border-bottom: 1px solid #EEF2F0; }
    .facts td.k { width: 128px; color: #6b7a73; }
    .chip { display: inline-block; padding: 1px 7px; border-radius: 9px; font-size: 8.5px; font-weight: bold; }
    .ok { background: #DDF2E6; color: #0F4C3A; }
    .wait { background: #FDF1D6; color: #8a5a00; }
    .box { background: #FFF8E8; border-left: 3px solid #F2B84B; padding: 7px 10px; margin-top: 8px; }
    ul { margin: 3px 0 0; padding-left: 14px; }
    li { margin: 1px 0; }
    .foot { margin-top: 18px; border-top: 1px solid #DCE3DF; padding-top: 10px; }
</style>
</head>
<body>

<table class="head">
    <tr>
        <td>
            <table style="width: auto"><tr>
                @if ($logo)<td style="padding-right: 8px"><img src="{{ $logo }}" width="30" height="30" alt=""></td>@endif
                <td style="vertical-align: middle"><span class="brand">mytransfertours</span></td>
            </tr></table>
        </td>
        <td style="text-align: right">
            <div class="label">Booking voucher</div>
            <div class="ref">{{ $booking->reference }}</div>
            <div class="small muted">Issued {{ now()->format('j M Y') }}</div>
        </td>
    </tr>
</table>

<table class="guest">
    <tr>
        <td style="width: 34%"><div class="label">Lead guest</div><b style="font-size: 12px">{{ $booking->lead_name }}</b></td>
        <td style="width: 38%"><div class="label">Booking email</div>{{ $booking->email }}@if ($booking->phone)<br><span class="muted">{{ $booking->phone }}</span>@endif</td>
        <td><div class="label">Booked on</div>{{ $booking->created_at->format('j M Y') }}<br><span class="muted">{{ count($items) }} {{ \Illuminate\Support\Str::plural('service', count($items)) }}</span></td>
    </tr>
</table>

@foreach ($items as $v)
    @php
        $item = $v['model'];
        $t = $item->transfer;
        $tr = $item->tour;
        $time = $item->service_time ? substr((string) $item->service_time, 0, 5) : null;
        $confirmed = in_array($item->status, [BookingItemStatus::Confirmed, BookingItemStatus::Completed], true);
    @endphp
    <div class="item">
        <div class="item-head">
            <table><tr>
                <td><span class="title">{{ $item->title }}</span><br><span style="font-size: 9px; color: #CFE0D8">{{ $item->product_type->getLabel() }} · {{ $item->reference }}</span></td>
                <td style="text-align: right; width: 150px">
                    <span style="font-size: 12px; font-weight: bold">{{ $item->service_date->format('D j M Y') }}</span>@if ($time)<br><span style="font-size: 11px">{{ $time }}</span>@endif
                </td>
            </tr></table>
        </div>
        <div class="item-body">
            <table><tr>
                @if ($v['image'])<td class="photo"><img src="{{ $v['image'] }}" alt=""></td>@endif
                <td>
                    <table class="facts">
                        <tr><td class="k">Status</td><td>
                            @if ($item->status === BookingItemStatus::Cancelled)<span class="chip wait">Cancelled</span>
                            @elseif ($confirmed)<span class="chip ok">Confirmed</span>@if ($item->supplier_reference) <span class="muted small">· partner ref {{ $item->supplier_reference }}</span>@endif
                            @else<span class="chip wait">Booked · awaiting partner confirmation</span>@endif
                        </td></tr>

                        @if ($t)
                            <tr><td class="k">Pick-up</td><td><b>{{ $t->pickup_location_name }}</b>@if ($t->pickup_address)<br>{{ $t->pickup_address }}@endif</td></tr>
                            <tr><td class="k">Drop-off</td><td><b>{{ $t->dropoff_location_name }}</b>@if ($t->dropoff_address)<br>{{ $t->dropoff_address }}@endif</td></tr>
                            @if ($t->flight_number)<tr><td class="k">Flight</td><td>{{ $t->flight_number }}@if ($t->flight_arrival_at) · arrives {{ $t->flight_arrival_at->format('D j M, H:i') }}@endif</td></tr>@endif
                            <tr><td class="k">Passengers</td><td>{{ $people($t->adults, $t->children, $t->infants) }}</td></tr>
                            <tr><td class="k">Large bags</td><td>{{ $t->bags }}</td></tr>
                            <tr><td class="k">Vehicle</td><td><b>{{ $t->vehicle_type_name }}</b>@if ($v['vehicle']?->example_models) <span class="muted">({{ $v['vehicle']->example_models }})</span>@endif</td></tr>
                            @if ($v['capacity'])<tr><td class="k">Vehicle capacity</td><td>{{ $v['capacity'] }}</td></tr>@endif
                            @if ($t->sign_name)<tr><td class="k">Name on sign</td><td>{{ $t->sign_name }}</td></tr>@endif
                            @if ($v['route']?->free_waiting_minutes)<tr><td class="k">Free waiting</td><td>{{ $v['route']->free_waiting_minutes }} minutes{{ $t->flight_number ? ' after the flight lands' : '' }}</td></tr>@endif
                            @if ($v['route']?->duration_minutes)<tr><td class="k">Journey time</td><td>About {{ \App\Services\VoucherPdf::duration($v['route']->duration_minutes) }}@if ($v['route']->distance_km) · {{ rtrim(rtrim((string) $v['route']->distance_km, '0'), '.') }} km @endif</td></tr>@endif
                            @php $driver = collect([$t->driver_name, $t->driver_phone, $t->vehicle_plate])->filter()->join(' · '); @endphp
                            @if ($driver)<tr><td class="k">Driver</td><td><b>{{ $driver }}</b></td></tr>@endif
                            @if ($t->driver_notes)<tr><td class="k">Notes</td><td>{{ $t->driver_notes }}</td></tr>@endif
                            @if ($t->is_return_leg)<tr><td class="k">Journey</td><td>Return trip</td></tr>@endif
                        @endif

                        @if ($tr)
                            <tr><td class="k">Option</td><td>{{ $tr->option === 'private' ? 'Private tour, your own vehicle' : 'Shared tour (seat-in-coach)' }}</td></tr>
                            <tr><td class="k">Guests</td><td>{{ $people($tr->adults, $tr->children) }}</td></tr>
                            @if ($tr->start_time && ! $time)<tr><td class="k">Start time</td><td>{{ substr((string) $tr->start_time, 0, 5) }}</td></tr>@endif
                            @if ($tr->pickup_hotel || $tr->pickup_address)<tr><td class="k">Pick-up</td><td><b>{{ $tr->pickup_hotel }}</b>@if ($tr->pickup_address)<br>{{ $tr->pickup_address }}@endif</td></tr>@endif
                            @if ($tr->vehicle_type_name)<tr><td class="k">Vehicle</td><td><b>{{ $tr->vehicle_type_name }}</b>@if ($v['vehicle']?->example_models) <span class="muted">({{ $v['vehicle']->example_models }})</span>@endif</td></tr>@endif
                            @if ($tr->option === 'private' && $v['capacity'])<tr><td class="k">Vehicle capacity</td><td>{{ $v['capacity'] }}</td></tr>@endif
                        @endif

                        @foreach ($v['details'] as $k => $val)<tr><td class="k">{{ $k }}</td><td>{{ $val }}</td></tr>@endforeach

                        @if ($item->tickets->isNotEmpty())
                            <tr><td class="k">Tickets</td><td>
                                @foreach ($item->tickets as $tk)
                                    <b>{{ $tk->quantity }} × {{ $tk->ticket_type_name }}</b>@if ($tk->ticketType?->age_label) <span class="muted">({{ $tk->ticketType->age_label }})</span>@endif @if ($tk->ticketType?->requires_id)<span class="muted">· bring ID</span>@endif<br>
                                @endforeach
                            </td></tr>
                        @endif

                        @if ($item->extras->isNotEmpty())
                            <tr><td class="k">Add-ons</td><td>@foreach ($item->extras as $x){{ $x->quantity }} × {{ $x->name }}<br>@endforeach</td></tr>
                        @endif
                    </table>
                </td>
            </tr></table>

            @if ($v['description'])<p style="margin: 9px 0 0">{{ $v['description'] }}</p>@endif

            @if ($v['tour'] && (! empty($v['tour']->inclusions) || ! empty($v['tour']->exclusions)))
                <table style="margin-top: 8px"><tr>
                    @if (! empty($v['tour']->inclusions))<td style="width: 50%; padding-right: 10px"><b>Included</b><ul>@foreach ($v['tour']->inclusions as $x)<li>{{ $x }}</li>@endforeach</ul></td>@endif
                    @if (! empty($v['tour']->exclusions))<td><b>Not included</b><ul>@foreach ($v['tour']->exclusions as $x)<li>{{ $x }}</li>@endforeach</ul></td>@endif
                </tr></table>
            @endif

            @if ($v['tour'] && ! empty($v['tour']->itinerary))
                <div style="margin-top: 8px"><b>Itinerary</b>
                    <table class="facts" style="margin-top: 3px">@foreach ($v['tour']->itinerary as $stop)<tr><td class="k" style="width: 50px">{{ $stop['time'] ?? '' }}</td><td>{{ $stop['text'] ?? '' }}</td></tr>@endforeach</table>
                </div>
            @endif

            @if ($v['attraction'] && ! empty($v['attraction']->good_to_know))
                <div style="margin-top: 8px"><b>Good to know</b><ul>@foreach ($v['attraction']->good_to_know as $x)<li>{{ $x }}</li>@endforeach</ul></div>
            @endif

            <div class="box"><b>Cancellation terms:</b> {{ $v['cancellation'] }}</div>
        </div>
    </div>
@endforeach

@if ($booking->customer_notes)
    <div class="box" style="margin-top: 14px"><b>Your notes:</b> {{ $booking->customer_notes }}</div>
@endif

<div class="foot small">
    <table><tr>
        <td style="width: 60%">
            <b>Show this voucher</b> to your driver or guide, printed or on your phone, with the booking reference <b>{{ $booking->reference }}</b>.
            To change or check your booking: <span style="color: #0F4C3A">{{ $booking->manageUrl() }}</span>
        </td>
        <td style="text-align: right">
            <b>{{ $company['name'] }}</b><br>
            @if ($company['email']){{ $company['email'] }}<br>@endif
            @if ($company['phone']){{ $company['phone'] }}@endif
        </td>
    </tr></table>
</div>

</body>
</html>
