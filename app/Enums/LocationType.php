<?php

namespace App\Enums;

enum LocationType: string
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
}
