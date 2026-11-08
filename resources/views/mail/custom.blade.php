<x-mail::message>
{!! nl2br(e($body)) !!}

<small>Booking {{ $booking->reference }}</small>

{{ $company['name'] }}
</x-mail::message>
