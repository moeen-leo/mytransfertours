<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PendingPayment = 'pending_payment';
    case PaymentFailed = 'payment_failed';
    case Paid = 'paid';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    public function isPaid(): bool
    {
        return in_array($this, [self::Paid, self::Confirmed, self::Completed, self::PartiallyRefunded], true);
    }
}
