<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LocationType: string implements HasLabel
{
    case Airport = 'airport';
    case CityArea = 'city_area';
    case HotelZone = 'hotel_zone';
    case Port = 'port';
    case Station = 'station';
    case Attraction = 'attraction';
    case Other = 'other';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
