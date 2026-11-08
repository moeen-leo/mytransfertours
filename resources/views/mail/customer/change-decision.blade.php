<x-mail::message>
@if ($request->status === 'approved')
# Your change is confirmed

Hi {{ $booking->lead_first_name }}, we've updated booking **{{ $booking->reference }}**.

@if ($request->item)
@include('mail.partials.item', ['item' => $request->item->fresh(['transfer', 'extras', 'booking']), 'showPrice' => false])
@endif
@else
# About your change request

Hi {{ $booking->lead_first_name }}, unfortunately we couldn't make the change you asked for on booking **{{ $booking->reference }}**. Your original booking stays as it was.
@endif

@if ($request->admin_note)
**Note from our team:** {{ $request->admin_note }}
@endif

<x-mail::button :url="$booking->manageUrl()" color="success">
View my booking
</x-mail::button>

{{ $company['name'] }}
</x-mail::message>
