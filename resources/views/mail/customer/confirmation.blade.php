<x-mail::message>
# Thank you, {{ $booking->lead_first_name }}!

Your booking **{{ $booking->reference }}** is confirmed. We've passed the details to our local partners, and we'll email you again once they confirm.

@foreach ($booking->items as $item)
@include('mail.partials.item', ['item' => $item])

@endforeach
@if ((float) $booking->discount_total > 0)
Promo {{ $booking->coupon_code }}: − {{ \App\Support\Money::formatIn((float) $booking->discount_total, $booking->currency) }}

@endif
**Total: {{ \App\Support\Money::formatIn((float) $booking->total, $booking->currency) }}**

@if ($booking->payment_method === \App\Enums\PaymentMethod::Credit)
Charged to your account. Invoice {{ $booking->invoice_number }} is due on {{ $booking->payment_due_at?->format('j F Y') }}.
@elseif ($booking->payment_status === \App\Enums\PaymentStatus::Paid)
Paid by card.
@endif

<x-mail::button :url="$booking->manageUrl()" color="success">
View or change my booking
</x-mail::button>

@if (! empty($hasVoucher))
**Your voucher is attached (PDF).** Show it to your driver or guide, printed or on your phone.

@endif
Keep this email: the button above is your private link to your booking. From there you can update your flight number, hotel or notes, request a date change, and print your invoice.

Questions? Just reply to this email{{ $company['phone'] ? ' or message us on '.$company['phone'] : '' }}.

{{ $company['name'] }}
</x-mail::message>
