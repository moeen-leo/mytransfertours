<x-mail::message>
@if ($reminder)
# Reminder: please confirm this booking
@else
# New booking
@endif

Hi {{ $item->supplier_name }}, please confirm the booking below{{ $item->transfer ? ' and add the driver details when you have them' : '' }}.

**Reference: {{ $item->reference }}**

@include('mail.partials.item', ['item' => $item, 'showPrice' => false])

- Lead passenger: {{ $booking->lead_name }}{{ $booking->phone ? ' · '.$booking->phone : '' }}
- Your rate (net): MYR {{ number_format((float) $item->cost_total, 2) }}

<x-mail::button :url="$link" color="success">
Confirm or decline
</x-mail::button>

One click, no login needed. The same link lets you add or update driver details later.

Questions? Reply to this email{{ $company['phone'] ? ' or WhatsApp '.$company['phone'] : '' }}.

{{ $company['name'] }}
</x-mail::message>
