<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\StripeEvent;
use App\Services\StripeService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * POST /stripe/webhook. Stripe tells us about payments here. This is the source of
 * truth for "paid": the customer may close the tab before returning to our site.
 *
 * Every event is checked against STRIPE_WEBHOOK_SECRET and recorded once in
 * stripe_events, so Stripe's automatic retries never process an event twice.
 */
class StripeWebhookController extends Controller
{
    public function __construct(private StripeService $stripe) {}

    public function __invoke(Request $request): Response
    {
        $secret = (string) config('services.stripe.webhook_secret');
        if ($secret === '') {
            Log::warning('Stripe webhook received but STRIPE_WEBHOOK_SECRET is not set.');

            return response('Webhook secret not configured', 500);
        }

        try {
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret);
        } catch (SignatureVerificationException|\UnexpectedValueException) {
            return response('Invalid signature', 400);
        }

        try {
            $record = StripeEvent::query()->create([
                'event_id' => $event->id,
                'type' => $event->type,
                'payload' => $event->toArray(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return response('Already processed', 200);
        }

        try {
            $this->handle($event);
            $record->forceFill(['processed_at' => now()])->save();
        } catch (\Throwable $e) {
            report($e);
            $record->forceFill(['error' => $e->getMessage()])->save();
            $record->delete();   // let Stripe's retry process it again

            return response('Error', 500);
        }

        return response('OK', 200);
    }

    private function handle(\Stripe\Event $event): void
    {
        $object = $event->data->object;

        match ($event->type) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $object instanceof Session
                ? $this->stripe->markPaidFromSession($this->freshSession($object))
                : null,
            'checkout.session.expired', 'checkout.session.async_payment_failed' => $this->abandon($object),
            default => null,
        };
    }

    /** Re-fetch so we have the payment intent and fee (webhook payloads are slim). Falls back to the payload. */
    private function freshSession(Session $session): Session
    {
        try {
            return $this->stripe->retrieveSession($session->id);
        } catch (\Throwable) {
            return $session;
        }
    }

    private function abandon(object $session): void
    {
        $booking = Booking::query()->where('stripe_checkout_session_id', $session->id ?? '')->first();
        if ($booking) {
            $this->stripe->markAbandoned($booking, 'Stripe checkout expired or the payment failed');
        }
    }
}
