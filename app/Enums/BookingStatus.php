<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where the TRIP stands. Money is tracked separately in PaymentStatus,
 * because a credit-account booking is booked before it is paid.
 */
enum BookingStatus: string implements HasLabel, HasColor
{
    case PendingPayment = 'pending_payment';   // at Stripe, not finished
    case PaymentFailed = 'payment_failed';
    case Booked = 'booked';                     // paid by card or confirmed on account; suppliers emailed
    case Confirmed = 'confirmed';               // every supplier has confirmed
    case Completed = 'completed';               // service date has passed
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::PendingPayment => 'Awaiting payment',
            self::PaymentFailed => 'Payment failed',
            self::Booked => 'Booked',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function label(): string
    {
        return $this->getLabel();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PendingPayment => 'gray',
            self::PaymentFailed, self::Cancelled => 'danger',
            self::Booked => 'warning',
            self::Confirmed, self::Completed => 'success',
        };
    }

    /** Bookings that are live: suppliers have been told and the customer has a reservation. */
    public function isActive(): bool
    {
        return in_array($this, [self::Booked, self::Confirmed, self::Completed], true);
    }
}
