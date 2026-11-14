@php
    use App\Enums\PaymentMethod;
    use App\Enums\PaymentStatus;
    $money = fn ($amount) => $booking->currency.' '.number_format((float) $amount, 2);
    $isCredit = $booking->payment_method === PaymentMethod::Credit;
    $isPaid = $booking->payment_status === PaymentStatus::Paid;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Invoice {{ $booking->invoice_number }}</title>
    <style>
        :root { --ink: #10231C; --muted: #4E5A54; --line: #E4DED3; --green: #0F4C3A; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #F3F1EC; color: var(--ink); font: 14px/1.5 Inter, system-ui, -apple-system, sans-serif; }
        .toolbar { max-width: 820px; margin: 24px auto 0; display: flex; justify-content: flex-end; gap: 8px; padding: 0 16px; }
        .toolbar button { min-height: 40px; padding: 0 16px; border-radius: 10px; border: 0; background: var(--green); color: #fff; font: 600 14px Inter, system-ui, sans-serif; cursor: pointer; }
        .sheet { max-width: 820px; margin: 16px auto 48px; background: #fff; padding: 48px; border-radius: 12px; }
        .top { display: flex; justify-content: space-between; gap: 24px; flex-wrap: wrap; }
        .brand { font-size: 22px; font-weight: 700; color: var(--green); letter-spacing: -0.02em; }
        h1 { margin: 0; font-size: 28px; letter-spacing: -0.02em; }
        .muted { color: var(--muted); }
        .meta { display: grid; grid-template-columns: auto auto; gap: 2px 16px; font-size: 13px; }
        .meta dt { color: var(--muted); } .meta dd { margin: 0; font-weight: 600; text-align: right; }
        .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin: 36px 0 28px; }
        .label { font-size: 11px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); padding: 10px 8px; border-bottom: 2px solid var(--ink); }
        td { padding: 12px 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .totals { margin-left: auto; width: 320px; margin-top: 16px; }
        .totals div { display: flex; justify-content: space-between; padding: 6px 8px; }
        .totals .grand { border-top: 2px solid var(--ink); margin-top: 6px; padding-top: 10px; font-size: 18px; font-weight: 700; }
        .stamp { display: inline-block; padding: 4px 12px; border-radius: 999px; font-weight: 700; font-size: 12px; letter-spacing: .05em; text-transform: uppercase; }
        .stamp.paid { background: #E3EEE8; color: var(--green); }
        .stamp.due { background: #FDF1D6; color: #7A5A0E; }
        .note { margin-top: 32px; padding: 16px; background: #F7F4EE; border-radius: 10px; font-size: 13px; white-space: pre-line; }
        footer { margin-top: 36px; font-size: 12px; color: var(--muted); text-align: center; }
        @media (max-width: 640px) { .sheet { padding: 24px; } .parties { grid-template-columns: 1fr; } .totals { width: 100%; } }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; padding: 0; max-width: none; border-radius: 0; }
            @page { margin: 18mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <main class="sheet">
        <div class="top">
            <div>
                <div class="brand">{{ $company['name'] }}</div>
                @if ($company['address'])<div class="muted" style="white-space: pre-line">{{ $company['address'] }}</div>@endif
                @if ($company['registration'])<div class="muted">Reg. no. {{ $company['registration'] }}</div>@endif
                <div class="muted">{{ collect([$company['email'], $company['phone']])->filter()->join(' · ') }}</div>
            </div>
            <div style="text-align: right">
                <h1>Invoice</h1>
                <dl class="meta">
                    <dt>Invoice no.</dt><dd>{{ $booking->invoice_number }}</dd>
                    <dt>Date</dt><dd>{{ ($booking->invoiced_at ?? $booking->created_at)->format('j M Y') }}</dd>
                    <dt>Booking</dt><dd>{{ $booking->reference }}</dd>
                    @if ($isCredit && $booking->payment_due_at)
                        <dt>Due</dt><dd>{{ $booking->payment_due_at->format('j M Y') }}</dd>
                    @endif
                </dl>
                <div style="margin-top: 10px">
                    @if ($isPaid)
                        <span class="stamp paid">Paid{{ $booking->paid_at ? ' · '.$booking->paid_at->format('j M Y') : '' }}</span>
                    @elseif ($isCredit)
                        <span class="stamp due">Payment due{{ $booking->isOverdue() ? ' · overdue' : '' }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="parties">
            <div>
                <div class="label">Bill to</div>
                <strong>{{ $booking->billing_company ?: $booking->lead_name }}</strong>
                @if ($booking->billing_company)<div>Attn: {{ $booking->lead_name }}</div>@endif
                @if ($booking->billing_address)<div style="white-space: pre-line">{{ $booking->billing_address }}</div>@endif
                @if ($booking->billing_tax_number)<div>Tax no. {{ $booking->billing_tax_number }}</div>@endif
                <div class="muted">{{ $booking->email }}</div>
            </div>
            <div>
                <div class="label">Payment</div>
                <div>{{ $booking->payment_method->getLabel() }}</div>
                @if ($booking->settlement_reference)<div class="muted">Ref. {{ $booking->settlement_reference }}</div>@endif
            </div>
        </div>

        <table>
            <thead>
                <tr><th>Service</th><th>Date</th><th class="num">Amount</th></tr>
            </thead>
            <tbody>
                @foreach ($booking->items as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->title }}</strong>
                            <div class="muted" style="font-size: 13px">
                                {{ $item->product_type->getLabel() }} · {{ $item->reference }}
                                @if ($item->transfer)
                                    · {{ $item->transfer->vehicle_type_name }} · {{ $item->transfer->adults + $item->transfer->children + $item->transfer->infants }} pax
                                @elseif ($item->tour)
                                    · {{ $item->tour->option === 'private' ? 'Private'.($item->tour->vehicle_type_name ? ' · '.$item->tour->vehicle_type_name : '') : 'Seat-in-coach' }} · {{ $item->tour->adults + $item->tour->children }} pax
                                @elseif ($item->tickets->isNotEmpty())
                                    · {{ $item->tickets->map(fn ($t) => $t->quantity.' × '.$t->ticket_type_name)->join(', ') }}
                                @endif
                                @foreach ($item->extras as $extra)
                                    · {{ $extra->quantity }} × {{ $extra->name }}
                                @endforeach
                            </div>
                        </td>
                        <td style="white-space: nowrap">{{ $item->service_date->format('j M Y') }}@if ($item->service_time)<br><span class="muted">{{ substr((string) $item->service_time, 0, 5) }}</span>@endif</td>
                        <td class="num">{{ $money($item->selling_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div><span>Subtotal</span><span>{{ $money($booking->subtotal) }}</span></div>
            @if ((float) $booking->discount_total > 0)
                <div><span>Discount{{ $booking->coupon_code ? ' ('.$booking->coupon_code.')' : '' }}</span><span>− {{ $money($booking->discount_total) }}</span></div>
            @endif
            <div class="grand"><span>Total</span><span>{{ $money($booking->total) }}</span></div>
        </div>

        @if ($isCredit && ! $isPaid && $company['bank'])
            <div class="note"><strong>Please pay by {{ $booking->payment_due_at?->format('j M Y') }} to:</strong>
{{ $company['bank'] }}
Quote {{ $booking->invoice_number }} as the payment reference.</div>
        @endif

        <footer>Thank you for booking with {{ $company['name'] }}.</footer>
    </main>
</body>
</html>
