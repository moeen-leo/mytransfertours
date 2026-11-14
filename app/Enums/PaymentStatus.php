<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasLabel, HasColor
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unpaid => 'warning',
            self::Paid => 'success',
            self::PartiallyRefunded, self::Refunded => 'gray',
        };
    }
}
