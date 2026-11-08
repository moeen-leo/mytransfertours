<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

/**
 * Card payments through Stripe Checkout (Stripe's hosted, PCI-compliant page).
 * Our site never sees card details. Amounts are charged in the booking's currency.
 */
class StripeService
{
    /** Currencies Stripe charges without decimals (none of ours today, but kept for safety). */
    private const ZERO_DECIMAL = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

    public static function enabled(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    public function client(): StripeClient
    {
        return new StripeClient(['api_key' => (string) config('services.stripe.secret'), 'max_network_retries' => 2]);
    }

    public static function toMinor(float $amount, string $currency): int
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true)
            ? (int) round($amount)
            : (int) round($amount * 100);
    }

    public static function fromMinor(int $amount, string $currency): float
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true) ? (float) $amount : $amount / 100;
    }

    /** Creates a Checkout Session for a pending booking and returns the URL to send the customer to. */
    public function createCheckout(Booking $booking, string $successUrl, string $cancelUrl): string
    {
        $currency = strtolower($booking->currency);

        $lineItems = $booking->items->map(fn ($item) => [
            'quantity' => 1,
            'price_data' => [
                'currency' => $currency,
                'unit_amount' => self::toMinor((float) $item->selling_total, $booking->currency),
                'product_data' => [
                    'name' => $item->title,
                    'description' => $item->product_type->getLabel().' · '.$item->service_date->format('D j M Y').($item->service_time ? ' · '.substr((string) $item->service_time, 0, 5) : ''),
                ],
            ],
        ])->values()->all();

        $session = $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => $lineItems,
            'customer_email' => $booking->email,
            'client_reference_id' => $booking->reference,
            'metadata' => ['booking_id' => (string) $booking->id, 'booking_reference' => $booking->reference],
            'payment_intent_data' => [
                'description' => 'MyTransferTours booking '.$booking->reference,
                'metadata' => ['booking_id' => (string) $booking->id, 'booking_reference' => $booking->reference],
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'expires_at' => now()->addMinutes(45)->getTimestamp(),
            'locale' => 'auto',
        ], ['idempotency_key' => 'checkout-'.$booking->id.'-'.$booking->updated_at?->getTimestamp()]);

        $booking->forceFill(['stripe_checkout_session_id' => $session->id])->save();
        $booking->log('checkout_started', 'Sent to Stripe Checkout', ['session' => $session->id]);

        return (string) $session->url;
    }

    public function retrieveSession(string $sessionId): Session
    {
        return $this->client()->checkout->sessions->retrieve($sessionId, ['expand' => ['payment_intent.latest_charge.balance_transaction']]);
    }

    /**
     * Marks the booking paid from a completed Checkout Session. Safe to call more than once
     * (success page and webhook both call it). Returns true if this call changed the booking.
     */
    public function markPaidFromSession(Session $session): bool
    {
        if ($session->payment_status !== 'paid') {
            return false;
        }

        $booking = Booking::query()->find((int) ($session->metadata['booking_id'] ?? 0))
            ?? Booking::query()->where('stripe_checkout_session_id', $session->id)->first();

        if (! $booking) {
            Log::warning('Stripe session for unknown booking', ['session' => $session->id]);

            return false;
        }

        $changed = DB::transaction(function () use ($booking, $session) {
            $booking = Booking::query()->lockForUpdate()->find($booking->id);

            if ($booking->payment_status === PaymentStatus::Paid) {
                return false;
            }

            $intent = $session->payment_intent;
            $intentId = is_string($intent) ? $intent : $intent?->id;

            $booking->forceFill([
                'payment_method' => PaymentMethod::Card,
                'payment_status' => PaymentStatus::Paid,
                'status' => BookingStatus::Booked,
                'paid_at' => now(),
                'stripe_checkout_session_id' => $session->id,
                'stripe_payment_intent_id' => $intentId,
                'stripe_charge_currency' => strtoupper((string) $session->currency),
                'stripe_charge_amount' => self::fromMinor((int) $session->amount_total, (string) $session->currency),
                'stripe_fee' => $this->feeFromSession($session),
            ])->save();

            $booking->assignInvoiceNumber();
            $booking->log('paid', 'Paid by card: '.strtoupper((string) $session->currency).' '.number_format(self::fromMinor((int) $session->amount_total, (string) $session->currency), 2), ['payment_intent' => $intentId], null, null);

            return true;
        });

        if ($changed) {
            app(BookingMailer::class)->bookingPlaced($booking->fresh(['items']));
        }

        return $changed;
    }

    /** Stripe's fee in the account's settlement currency (MYR), if Stripe has reported it yet. */
    private function feeFromSession(Session $session): ?float
    {
        try {
            $intent = $session->payment_intent;
            if (is_string($intent)) {
                $intent = $this->client()->paymentIntents->retrieve($intent, ['expand' => ['latest_charge.balance_transaction']]);
            }
            $bt = $intent?->latest_charge?->balance_transaction ?? null;
            if (is_object($bt) && isset($bt->fee)) {
                return self::fromMinor((int) $bt->fee, (string) $bt->currency);
            }
        } catch (\Throwable $e) {
            Log::info('Could not read Stripe fee', ['session' => $session->id, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /** Marks an unfinished checkout as failed/abandoned (session expired or cancelled). */
    public function markAbandoned(Booking $booking, string $reason): void
    {
        if ($booking->status !== BookingStatus::PendingPayment) {
            return;
        }

        $booking->forceFill(['status' => BookingStatus::PaymentFailed])->save();
        $booking->log('payment_abandoned', $reason, [], null, null);
    }

    /** Refunds all or part of a card payment. Amount is in the booking currency. */
    public function refund(Booking $booking, float $amount, ?string $reason, ?int $byUserId): Refund
    {
        $refunded = (float) $booking->refunds()->where('status', 'succeeded')->sum('amount');
        $max = round((float) $booking->total - $refunded, 2);

        if ($amount <= 0 || $amount > $max + 0.001) {
            throw new \InvalidArgumentException('Refund must be between 0.01 and '.number_format($max, 2).' '.$booking->currency.'.');
        }
        if (! $booking->stripe_payment_intent_id) {
            throw new \InvalidArgumentException('This booking has no card payment to refund.');
        }

        $stripeRefund = $this->client()->refunds->create([
            'payment_intent' => $booking->stripe_payment_intent_id,
            'amount' => self::toMinor($amount, $booking->currency),
            'metadata' => ['booking_reference' => $booking->reference],
        ], ['idempotency_key' => 'refund-'.$booking->id.'-'.$booking->refunds()->count().'-'.self::toMinor($amount, $booking->currency)]);

        $refund = $booking->refunds()->create([
            'amount' => $amount,
            'currency' => $booking->currency,
            'reason' => $reason,
            'stripe_refund_id' => $stripeRefund->id,
            'status' => in_array($stripeRefund->status, ['succeeded', 'pending'], true) ? 'succeeded' : (string) $stripeRefund->status,
            'created_by' => $byUserId,
        ]);

        $totalRefunded = round($refunded + $amount, 2);
        $booking->forceFill([
            'payment_status' => $totalRefunded >= (float) $booking->total - 0.001 ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
        ])->save();

        $booking->log('refunded', 'Refunded '.$booking->currency.' '.number_format($amount, 2).($reason ? ': '.$reason : ''), ['refund' => $stripeRefund->id], null, $byUserId);

        return $refund;
    }
}
