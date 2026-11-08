<x-mail::message>
# Booking {{ $item->reference }} has changed

Hi {{ $item->supplier_name }}, the customer has updated this booking. What changed:

@foreach ($changes as $label => $change)
- **{{ $label }}:** {{ $change['from'] ?: '—' }} → **{{ $change['to'] ?: '—' }}**
@endforeach

The booking now reads:

@include('mail.partials.item', ['item' => $item, 'showPrice' => false])

<x-mail::button :url="$link" color="success">
Open booking
</x-mail::button>

{{ $company['name'] }}
</x-mail::message>
