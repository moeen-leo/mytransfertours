<x-mail::message>
# {{ $booking->reference }}

{{ $message }}

@if ($item)
@include('mail.partials.item', ['item' => $item])
@endif

- Customer: {{ $booking->lead_name }} · {{ $booking->email }}{{ $booking->phone ? ' · '.$booking->phone : '' }}

<x-mail::button :url="$adminUrl">
Open in admin
</x-mail::button>
</x-mail::message>
