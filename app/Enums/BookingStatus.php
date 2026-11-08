<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BookingStatus: string implements HasLabel
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

    public function getLabel(): string
    {
        return $this->label();
    }
}
