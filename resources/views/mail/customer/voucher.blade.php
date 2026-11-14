<x-mail::message>
# Your booking is confirmed, {{ $booking->lead_first_name }}!

Good news: our local partners have confirmed booking **{{ $booking->reference }}**.

@if (! empty($hasVoucher))
**Your voucher is attached (PDF).** Show it to your driver or guide, printed or on your phone.
@else
You can download your voucher with the button below.
@endif

@foreach ($booking->items as $item)
- **{{ $item->title }}**: {{ $item->service_date->format('D j M Y') }}{{ $item->service_time ? ', '.substr((string) $item->service_time, 0, 5) : '' }}
@endforeach

<x-mail::button :url="route('manage.voucher', $booking->manage_token)" color="success">
Download voucher
</x-mail::button>

You can also view or change your booking here: [{{ $booking->reference }}]({{ $booking->manageUrl() }})

Questions? Just reply to this email{{ $company['phone'] ? ' or message us on '.$company['phone'] : '' }}.

{{ $company['name'] }}
</x-mail::message>
