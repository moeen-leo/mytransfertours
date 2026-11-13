<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Location;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;

/**
 * Starter reference data: Malaysia, launch cities, common pick-up / drop-off
 * points and vehicle classes. Safe to run more than once. Prices, suppliers
 * and products are entered by the admin, not seeded.
 */
class MalaysiaStarterSeeder extends Seeder
{
    public function run(): void
    {
        $my = Country::updateOrCreate(['code' => 'MY'], [
            'name' => 'Malaysia', 'currency' => 'MYR', 'phone_code' => '+60', 'is_active' => true,
        ]);

        $cities = [
            'kuala-lumpur' => ['Kuala Lumpur', 1],
            'langkawi' => ['Langkawi', 2],
            'penang' => ['Penang', 3],
            'genting-highlands' => ['Genting Highlands', 4],
            'malacca' => ['Malacca', 5],
        ];

        foreach ($cities as $slug => [$name, $sort]) {
            City::updateOrCreate(['slug' => $slug], [
                'country_id' => $my->id, 'name' => $name, 'timezone' => 'Asia/Kuala_Lumpur', 'sort' => $sort,
            ]);
        }

        $kl = City::where('slug', 'kuala-lumpur')->value('id');
        $lgk = City::where('slug', 'langkawi')->value('id');
        $pen = City::where('slug', 'penang')->value('id');
        $gen = City::where('slug', 'genting-highlands')->value('id');

        $locations = [
            [$kl, 'KL International Airport (KLIA Terminal 1)', 'KLIA T1', 'airport', 'KUL', true, false],
            [$kl, 'KL International Airport (KLIA Terminal 2)', 'KLIA T2', 'airport', 'KUL', true, false],
            [$kl, 'Sultan Abdul Aziz Shah Airport (Subang)', 'Subang', 'airport', 'SZB', true, false],
            [$kl, 'Kuala Lumpur City Centre (hotel)', 'KL City', 'city_area', null, false, true],
            [$kl, 'Bukit Bintang (hotel)', 'Bukit Bintang', 'city_area', null, false, true],
            [$kl, 'KL Sentral Station', 'KL Sentral', 'station', null, false, false],
            [$kl, 'Port Klang Cruise Terminal', 'Port Klang', 'port', null, false, false],
            [$gen, 'Genting Highlands (hotel)', 'Genting', 'city_area', null, false, true],
            [$lgk, 'Langkawi International Airport', 'LGK Airport', 'airport', 'LGK', true, false],
            [$lgk, 'Pantai Cenang (hotel)', 'Pantai Cenang', 'city_area', null, false, true],
            [$pen, 'Penang International Airport', 'PEN Airport', 'airport', 'PEN', true, false],
            [$pen, 'George Town (hotel)', 'George Town', 'city_area', null, false, true],
            [$pen, 'Batu Ferringhi (hotel)', 'Batu Ferringhi', 'city_area', null, false, true],
        ];

        foreach ($locations as $i => [$cityId, $name, $short, $type, $iata, $needsFlight, $needsAddress]) {
            Location::updateOrCreate(['name' => $name], [
                'city_id' => $cityId,
                'short_name' => $short,
                'type' => $type,
                'iata_code' => $iata,
                'requires_flight_number' => $needsFlight,
                'requires_address' => $needsAddress,
                'sort' => $i + 1,
            ]);
        }

        $vehicles = [
            ['standard-sedan', 'Standard sedan', 'Toyota Vios or similar', 3, 2, ['Meet & greet', 'Flight tracking']],
            ['family-mpv', 'Family MPV', 'Toyota Innova or similar', 6, 4, ['Meet & greet', 'Child seat available']],
            ['van', 'Van', 'Toyota Hiace or similar', 10, 8, ['Groups & golf bags']],
            ['executive', 'Executive', 'Mercedes E-Class or similar', 3, 2, ['Suited chauffeur', 'Water & Wi-Fi']],
        ];

        foreach ($vehicles as $i => [$slug, $name, $models, $pax, $bags, $features]) {
            VehicleType::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'example_models' => $models, 'max_pax' => $pax,
                'max_bags' => $bags, 'features' => $features, 'sort' => $i + 1,
            ]);
        }
    }
}
