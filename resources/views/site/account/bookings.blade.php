@extends('layouts.site', ['title' => 'My bookings'])

@php use App\Support\Money; @endphp

@section('content')
<div class="wrap page stack" style="gap: 18px">
    <div class="row between" style="align-items: end">
        <h1 style="font-size: 34px">My bookings</h1>
        <span class="muted small">{{ $user->email }}</span>
    </div>

    @if ($user->credit_enabled)
        <div class="card pad row between">
            <div><span class="label">Credit account</span><p><b>{{ $user->company_name ?: $user->name }}</b> · {{ $user->credit_terms_days }}-day terms</p></div>
            <div class="row" style="gap: 28px">
                <div><span class="label">Outstanding</span><p class="price" style="font-size: 22px">{{ Money::formatIn($outstanding, $user->preferred_currency) }}</p></div>
                <div><span class="label">Available</span><p class="price" style="font-size: 22px">{{ $available === null ? 'No limit' : Money::formatIn($available, $user->preferred_currency) }}</p></div>
            </div>
        </div>
    @endif

    @if ($bookings->isEmpty())
        <div class="card pad stack"><p>No bookings yet.</p><a href="{{ route('home') }}#search" class="btn" style="align-self: flex-start">Find a transfer</a></div>
    @else
        <div class="card table-box">
            <table class="list">
                <thead><tr><th>Booking</th><th>First service</th><th>Total</th><th>Status</th><th>Payment</th><th></th></tr></thead>
                <tbody>
                    @foreach ($bookings as $b)
                        <tr>
                            <td><b>{{ $b->reference }}</b><br><span class="tiny muted">{{ $b->items->pluck('title')->join(', ') }}</span></td>
                            <td>{{ $b->items->min('service_date')?->format('j M Y') ?? '—' }}</td>
                            <td>{{ Money::formatIn((float) $b->total, $b->currency) }}</td>
                            <td><span class="chip">{{ $b->status->getLabel() }}</span></td>
                            <td>{{ $b->payment_method->getLabel() }}<br><span class="tiny {{ $b->isOverdue() ? 'error' : 'muted' }}">{{ $b->payment_status->getLabel() }}{{ $b->payment_status === \App\Enums\PaymentStatus::Unpaid && $b->payment_due_at ? ' · due '.$b->payment_due_at->format('j M') : '' }}</span></td>
                            <td>@if ($b->invoice_number)<a href="{{ route('invoices.show', $b) }}" target="_blank" rel="noopener">Invoice</a>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $bookings->links() }}
    @endif
</div>
@endsection
