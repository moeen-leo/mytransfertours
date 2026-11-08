@php
    $t = $item->transfer;
    $time = $item->service_time ? substr((string) $item->service_time, 0, 5) : null;
    $showPrice = $showPrice ?? true;
    $when = $item->product_type->getLabel().' · '.$item->service_date->format('l j F Y').($time ? ' · '.$time : '');
    $pax = null;
    if ($t) {
        $parts = [$t->adults.' adult'.($t->adults == 1 ? '' : 's')];
        if ($t->children) { $parts[] = $t->children.' child'.($t->children == 1 ? '' : 'ren'); }
        if ($t->infants) { $parts[] = $t->infants.' infant'.($t->infants == 1 ? '' : 's'); }
        $pax = implode(', ', $parts).' · '.$t->bags.' large bag'.($t->bags == 1 ? '' : 's');
        $driver = collect([$t->driver_name, $t->driver_phone, $t->vehicle_plate])->filter()->join(' · ');
    }
@endphp
**{{ $item->title }}**

{{ $when }}

@if ($t)
- Pick-up: {{ $t->pickup_location_name }}{{ $t->pickup_address ? ' ('.$t->pickup_address.')' : '' }}
- Drop-off: {{ $t->dropoff_location_name }}{{ $t->dropoff_address ? ' ('.$t->dropoff_address.')' : '' }}
- Vehicle: {{ $t->vehicle_type_name }}
- Passengers: {{ $pax }}
@if ($t->flight_number)
- Flight: {{ $t->flight_number }}
@endif
@if ($t->sign_name)
- Name on sign: {{ $t->sign_name }}
@endif
@if ($t->driver_notes)
- Notes: {{ $t->driver_notes }}
@endif
@if ($driver)
- Driver: {{ $driver }}
@endif
@endif
@foreach ($item->extras as $x)
- Add-on: {{ $x->quantity }} × {{ $x->name }}
@endforeach
@if ($showPrice)
- Price: {{ \App\Support\Money::formatIn((float) $item->selling_total, $item->booking->currency) }}
@endif
