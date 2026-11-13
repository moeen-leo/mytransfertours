<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Customer = 'customer';
    case Staff = 'staff';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::Customer => 'Customer (no admin access)',
            self::Staff => 'Staff (catalogue & bookings)',
            self::Admin => 'Admin (everything, incl. team)',
        };
    }
}
