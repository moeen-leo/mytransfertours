<?php

namespace App\Enums;

enum ProductType: string
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
}
