<?php

namespace App\Console\Commands;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Services\StripeService;
use Illuminate\Console\Command;

/**
 * Fills in Stripe's fee on card bookings where it wasn't known yet when the payment
 * completed. Profit on the dashboard subtracts this fee.
 */
class SyncStripeFees extends Command
{
    protected $signature = 'app:sync-stripe-fees';

    protected $description = 'Fill in missing Stripe fees on paid card bookings';

    public function handle(StripeService $stripe): int
    {
        if (blank(config('services.stripe.secret'))) {
            return self::SUCCESS;
        }

        $bookings = Booking::query()
            ->where('payment_method', PaymentMethod::Card)
            ->whereIn('payment_status', [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded])
            ->whereNull('stripe_fee')
            ->whereNotNull('stripe_payment_intent_id')
            ->where('paid_at', '>=', now()->subDays(60))
            ->limit(20)
            ->get();

        foreach ($bookings as $booking) {
            $fee = $stripe->feeForIntent($booking->stripe_payment_intent_id);
            if ($fee !== null) {
                $booking->forceFill(['stripe_fee' => $fee])->save();
                $this->line("{$booking->reference}: Stripe fee {$fee}");
            }
        }

        return self::SUCCESS;
    }
}
