<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel, HasColor
{
    case Card = 'card';       // Stripe Checkout
    case Credit = 'credit';   // customer's credit account, settled later

    public function getLabel(): string
    {
        return match ($this) {
            self::Card => 'Card (Stripe)',
            self::Credit => 'On account',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Card => 'info',
            self::Credit => 'primary',
        };
    }
}
