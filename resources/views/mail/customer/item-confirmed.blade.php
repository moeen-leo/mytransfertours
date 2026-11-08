<x-mail::message>
# All set for {{ $item->service_date->format('l j F') }}

Good news, {{ $booking->lead_first_name }}: our local partner has confirmed this part of your booking **{{ $booking->reference }}**.

@include('mail.partials.item', ['item' => $item, 'showPrice' => false])

@if ($item->transfer && $item->transfer->driver_name)
Your driver will meet you with a sign showing **{{ $item->transfer->sign_name }}**.
@elseif ($item->transfer)
Driver details will follow closer to the date.
@endif

<x-mail::button :url="$booking->manageUrl()" color="success">
View my booking
</x-mail::button>

{{ $company['name'] }}
</x-mail::message>
