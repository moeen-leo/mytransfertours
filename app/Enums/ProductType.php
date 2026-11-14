<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProductType: string implements HasLabel
{
    case Transfer = 'transfer';
    case Tour = 'tour';
    case Attraction = 'attraction';

    public function label(): string
    {
        return match ($this) {
            self::Transfer => 'Transfer',
            self::Tour => 'Tour',
            self::Attraction => 'Attraction ticket',
        };
    }

    /** Letter used in item references, e.g. MTT-10482-T1. */
    public function referenceLetter(): string
    {
        return match ($this) {
            self::Transfer => 'T',
            self::Tour => 'R',
            self::Attraction => 'A',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
