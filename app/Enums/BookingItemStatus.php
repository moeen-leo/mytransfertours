<?php

namespace App\Enums;

enum BookingItemStatus: string
{
    case Pending = 'pending';
    case SentToSupplier = 'sent_to_supplier';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
