<?php

namespace Database\Seeders;

use App\Models\Attraction;
use App\Models\City;
use App\Models\Extra;
use App\Models\Location;
use App\Models\Supplier;
use App\Models\Tour;
use App\Models\TransferRoute;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo catalogue for testing and demos: transfers, tours and attraction tickets in Malaysia.
 * Safe to run again (updates instead of duplicating). Prices are examples only.
 * Supplier emails go to ADMIN_EMAIL, so you receive the one-click confirm emails yourself.
 *
 * Needs MalaysiaStarterSeeder first (cities, locations, vehicles).
 */
class SampleCatalogueSeeder extends Seeder
{
    public const SUPPLIER_PREFIX = 'Sample · ';

    public function run(): void
    {
        $this->call(MalaysiaStarterSeeder::class);

        DB::transaction(function () {
            $suppliers = $this->suppliers();
            $this->transfers($suppliers['transport']);
            $this->tours($suppliers);
            $this->attractions($suppliers['tickets']);
            $this->extras();
        });
    }

    /** Turns every sample product off again (bookings that used them keep working). */
    public static function deactivate(): int
    {
        $supplierIds = Supplier::query()->where('name', 'like', self::SUPPLIER_PREFIX.'%')->pluck('id');

        return TransferRoute::query()->whereIn('supplier_id', $supplierIds)->update(['is_active' => false])
            + Tour::query()->whereIn('supplier_id', $supplierIds)->update(['is_active' => false])
            + Attraction::query()->whereIn('supplier_id', $supplierIds)->update(['is_active' => false]);
    }

    private function suppliers(): array
    {
        $email = config('admin.email') ?: 'sample-supplier@example.com';
        $rows = [
            'transport' => ['KL Airport Cars', 'Ahmad Razak', '+60 12-345 6701'],
            'tours' => ['Malaysia Day Tours', 'Siti Aminah', '+60 12-345 6702'],
            'islands' => ['Langkawi Island Trips', 'Lim Wei Jie', '+60 12-345 6703'],
            'tickets' => ['Attraction Tickets MY', 'Priya Nair', '+60 12-345 6704'],
        ];

        $out = [];
        foreach ($rows as $key => [$name, $contact, $phone]) {
            $out[$key] = Supplier::updateOrCreate(['name' => self::SUPPLIER_PREFIX.$name], [
                'contact_name' => $contact, 'email' => $email, 'phone' => $phone, 'whatsapp' => $phone,
                'payment_terms' => 'Monthly, 30 days', 'notes' => 'Sample supplier from "Load sample catalogue".', 'is_active' => true,
            ])->id;
        }

        return $out;
    }

    private function transfers(int $supplierId): void
    {
        $loc = Location::query()->pluck('id', 'short_name');
        $veh = VehicleType::query()->pluck('id', 'slug');

        // from, to, km, minutes, sedan price (selling, MYR). Other vehicles are priced from it.
        $routes = [
            ['KLIA T1', 'KL City', 58, 60, 95],
            ['KLIA T2', 'KL City', 60, 65, 95],
            ['KLIA T1', 'Bukit Bintang', 57, 60, 95],
            ['KLIA T2', 'Bukit Bintang', 59, 65, 95],
            ['KLIA T1', 'KL Sentral', 52, 55, 90],
            ['KLIA T1', 'Genting', 110, 100, 190],
            ['KLIA T2', 'Genting', 112, 105, 190],
            ['KL City', 'Genting', 55, 60, 140],
            ['KL City', 'Port Klang', 50, 60, 120],
            ['Subang', 'KL City', 25, 35, 70],
            ['LGK Airport', 'Pantai Cenang', 6, 10, 35],
            ['PEN Airport', 'George Town', 18, 30, 55],
            ['PEN Airport', 'Batu Ferringhi', 33, 50, 80],
        ];
        // vehicle => [selling multiplier, cost as share of selling]
        $vehicles = ['standard-sedan' => [1.0, 0.72], 'family-mpv' => [1.35, 0.72], 'van' => [1.9, 0.74], 'executive' => [2.3, 0.70]];

        foreach ($routes as [$from, $to, $km, $min, $sedan]) {
            if (! isset($loc[$from], $loc[$to])) {
                continue;
            }
            $route = TransferRoute::updateOrCreate(
                ['from_location_id' => $loc[$from], 'to_location_id' => $loc[$to]],
                ['supplier_id' => $supplierId, 'distance_km' => $km, 'duration_minutes' => $min, 'is_bidirectional' => true,
                    'free_waiting_minutes' => str_contains($from, 'Airport') || str_starts_with($from, 'KLIA') || $from === 'Subang' ? 60 : 15,
                    'cancellation_hours' => 24, 'is_active' => true],
            );
            foreach ($vehicles as $slug => [$mult, $costShare]) {
                if (! isset($veh[$slug])) {
                    continue;
                }
                $sell = round($sedan * $mult / 5) * 5;
                $route->prices()->updateOrCreate(['vehicle_type_id' => $veh[$slug]], [
                    'cost_price' => round($sell * $costShare), 'selling_price' => $sell, 'currency' => 'MYR', 'is_active' => true,
                ]);
            }
        }
    }

    private function tours(array $suppliers): void
    {
        $city = City::query()->pluck('id', 'slug');
        $veh = VehicleType::query()->pluck('id', 'slug');

        $tours = [
            [
                'slug' => 'batu-caves-kl-city-tour', 'city' => 'kuala-lumpur', 'supplier' => 'tours',
                'title' => 'Batu Caves & Kuala Lumpur city highlights',
                'summary' => 'Climb the rainbow steps of Batu Caves, then see Merdeka Square, the King\'s Palace and the Petronas Twin Towers in one easy half day.',
                'description' => 'Your guide picks you up from your hotel and drives you to Batu Caves, the limestone temple caves guarded by a giant golden statue. After the caves you head back into the city for photo stops at the King\'s Palace, the National Monument, Merdeka Square and the Petronas Twin Towers, with stories about Malaysia\'s history along the way.',
                'minutes' => 300, 'sic' => ['08:30', '14:00'], 'private' => ['08:00', '09:00', '13:00', '14:00'],
                'highlights' => ['272 rainbow steps of Batu Caves', 'Photo stop at the Petronas Twin Towers', 'Merdeka Square and the King\'s Palace', 'Hotel pick-up and drop-off'],
                'itinerary' => [['time' => '08:30', 'text' => 'Hotel pick-up in central Kuala Lumpur'], ['time' => '09:15', 'text' => 'Batu Caves temple and rainbow steps'], ['time' => '10:45', 'text' => 'King\'s Palace (Istana Negara) and National Monument'], ['time' => '11:45', 'text' => 'Merdeka Square and Sultan Abdul Samad Building'], ['time' => '12:45', 'text' => 'Petronas Twin Towers photo stop, then hotel drop-off']],
                'inclusions' => ['Hotel pick-up and drop-off in central KL', 'English-speaking guide', 'Air-conditioned transport', 'Bottled water'],
                'exclusions' => ['Meals', 'Tips'],
                'sicPrices' => [120, 85, 90, 62], 'privatePrices' => ['standard-sedan' => [380, 270], 'family-mpv' => [460, 330], 'van' => [620, 450]],
            ],
            [
                'slug' => 'genting-highlands-day-trip', 'city' => 'kuala-lumpur', 'supplier' => 'tours',
                'title' => 'Genting Highlands day trip with cable car & Batu Caves',
                'summary' => 'A cool-climate day out: Batu Caves on the way, the Awana SkyWay cable car up the mountain and free time at the Chin Swee Temple and SkyAvenue.',
                'description' => 'Escape the city heat for a day in the hills. After a stop at Batu Caves you drive up to Genting Highlands and ride the Awana SkyWay cable car through the rainforest canopy. Visit the hillside Chin Swee Caves Temple, then enjoy free time at SkyAvenue for shopping, food and the casino before returning to Kuala Lumpur.',
                'minutes' => 540, 'sic' => ['08:30'], 'private' => ['08:00', '09:00'],
                'highlights' => ['Awana SkyWay cable car (return)', 'Chin Swee Caves Temple', 'Free time at SkyAvenue', 'Batu Caves stop on the way'],
                'itinerary' => [['time' => '08:30', 'text' => 'Hotel pick-up'], ['time' => '09:15', 'text' => 'Batu Caves'], ['time' => '11:00', 'text' => 'Awana SkyWay cable car up to Genting'], ['time' => '11:30', 'text' => 'Chin Swee Caves Temple'], ['time' => '13:00', 'text' => 'Free time at SkyAvenue (lunch on your own)'], ['time' => '17:30', 'text' => 'Back at your hotel']],
                'inclusions' => ['Hotel pick-up and drop-off', 'Return cable car ticket', 'Driver-guide'],
                'exclusions' => ['Lunch', 'Theme park tickets'],
                'sicPrices' => [230, 165, 180, 130], 'privatePrices' => ['standard-sedan' => [650, 470], 'family-mpv' => [780, 560], 'van' => [1050, 770]],
            ],
            [
                'slug' => 'malacca-heritage-day-tour', 'city' => 'kuala-lumpur', 'supplier' => 'tours',
                'title' => 'Malacca heritage day tour from Kuala Lumpur',
                'summary' => 'Walk through 600 years of history in the UNESCO town of Malacca: Dutch Square, A Famosa, St Paul\'s Hill and Jonker Street, with a Nyonya lunch.',
                'description' => 'Malacca was once the busiest port in Southeast Asia, ruled in turn by sultans, the Portuguese, the Dutch and the British. Your guide walks you through Dutch Square and Christ Church, up St Paul\'s Hill to the ruined church, past the A Famosa gate and along the river, finishing with free time on Jonker Street. Lunch is a classic Nyonya (Peranakan) meal.',
                'minutes' => 600, 'sic' => ['08:00'], 'private' => ['07:30', '08:00', '09:00'],
                'highlights' => ['UNESCO World Heritage old town', 'Nyonya lunch included', 'Jonker Street', 'St Paul\'s Hill and A Famosa'],
                'itinerary' => [['time' => '08:00', 'text' => 'Hotel pick-up in Kuala Lumpur'], ['time' => '10:15', 'text' => 'Dutch Square and Christ Church'], ['time' => '11:00', 'text' => 'St Paul\'s Hill and A Famosa'], ['time' => '12:30', 'text' => 'Nyonya lunch'], ['time' => '14:00', 'text' => 'Jonker Street free time'], ['time' => '18:00', 'text' => 'Back in Kuala Lumpur']],
                'inclusions' => ['Hotel pick-up and drop-off', 'Guide', 'Nyonya lunch', 'Bottled water'],
                'exclusions' => ['Trishaw ride', 'Tips'],
                'sicPrices' => [260, 185, 200, 140], 'privatePrices' => ['standard-sedan' => [720, 520], 'family-mpv' => [860, 620], 'van' => [1150, 840]],
            ],
            [
                'slug' => 'kuala-selangor-fireflies-evening-tour', 'city' => 'kuala-lumpur', 'supplier' => 'tours',
                'title' => 'Kuala Selangor fireflies & silver leaf monkeys evening tour',
                'summary' => 'Feed silver leaf monkeys on Bukit Melawati, enjoy a seafood dinner by the river and glide past mangroves lit up by thousands of fireflies.',
                'description' => 'Leave the city in the afternoon for the fishing town of Kuala Selangor. Meet the gentle silver leaf monkeys on Bukit Melawati hill, watch the sunset over the Straits of Malacca and have a Chinese-style seafood dinner. After dark, a quiet rowing boat takes you along the Selangor River where the mangrove trees twinkle with fireflies.',
                'minutes' => 420, 'sic' => ['15:00'], 'private' => ['14:30', '15:00'],
                'highlights' => ['Firefly boat ride', 'Silver leaf monkeys', 'Seafood dinner included', 'Sunset over the Straits'],
                'itinerary' => [['time' => '15:00', 'text' => 'Hotel pick-up'], ['time' => '16:30', 'text' => 'Bukit Melawati and silver leaf monkeys'], ['time' => '18:30', 'text' => 'Seafood dinner'], ['time' => '20:00', 'text' => 'Firefly boat ride'], ['time' => '22:00', 'text' => 'Back at your hotel']],
                'inclusions' => ['Hotel pick-up and drop-off', 'Seafood dinner', 'Firefly boat ride', 'Guide'],
                'exclusions' => ['Drinks with dinner', 'Tips'],
                'sicPrices' => [210, 150, 160, 115], 'privatePrices' => ['standard-sedan' => [560, 400], 'family-mpv' => [680, 490], 'van' => [900, 650]],
            ],
            [
                'slug' => 'langkawi-island-hopping', 'city' => 'langkawi', 'supplier' => 'islands',
                'title' => 'Langkawi island hopping: Pregnant Maiden Lake & eagle feeding',
                'summary' => 'A half day by speedboat: swim in the freshwater Pregnant Maiden Lake, watch eagles swoop for fish and relax on Beras Basah island\'s white beach.',
                'description' => 'Board a speedboat for three of Langkawi\'s best islands. On Dayang Bunting you walk through the forest to the Lake of the Pregnant Maiden for a swim. Next, watch brahminy kites and sea eagles dive for fish, and finish on the white sand of Pulau Beras Basah for swimming and sunbathing.',
                'minutes' => 240, 'sic' => ['09:00', '14:00'], 'private' => ['09:00', '14:00'],
                'highlights' => ['Lake of the Pregnant Maiden', 'Eagle feeding', 'Beach time on Beras Basah', 'Hotel transfer in Pantai Cenang'],
                'itinerary' => [['time' => '09:00', 'text' => 'Pick-up and jetty check-in'], ['time' => '09:45', 'text' => 'Dayang Bunting and the lake'], ['time' => '11:00', 'text' => 'Eagle feeding'], ['time' => '11:30', 'text' => 'Beras Basah beach'], ['time' => '13:00', 'text' => 'Back at the jetty and hotel']],
                'inclusions' => ['Speedboat and life jackets', 'Hotel transfer in the Pantai Cenang area', 'Marine park fees'],
                'exclusions' => ['Lunch', 'Towels'],
                'sicPrices' => [75, 50, 55, 38], 'privatePrices' => ['family-mpv' => [480, 340], 'van' => [620, 450]],
            ],
            [
                'slug' => 'penang-hill-george-town-heritage', 'city' => 'penang', 'supplier' => 'tours',
                'title' => 'Penang Hill & George Town heritage tour',
                'summary' => 'Ride the funicular up Penang Hill, visit Kek Lok Si Temple and finish with the street art, clan houses and food of UNESCO-listed George Town.',
                'description' => 'Start with the Penang Hill funicular for cool air and views over the island, then visit Kek Lok Si, one of the largest Buddhist temples in Southeast Asia. In the afternoon, explore George Town: the famous street art murals, the Khoo Kongsi clan house, Armenian Street and the Clan Jetties on stilts over the sea.',
                'minutes' => 420, 'sic' => ['09:00'], 'private' => ['08:30', '09:00', '10:00'],
                'highlights' => ['Penang Hill funicular', 'Kek Lok Si Temple', 'George Town street art', 'Clan Jetties'],
                'itinerary' => [['time' => '09:00', 'text' => 'Hotel pick-up'], ['time' => '09:45', 'text' => 'Penang Hill funicular'], ['time' => '11:30', 'text' => 'Kek Lok Si Temple'], ['time' => '13:00', 'text' => 'Lunch break (own expense)'], ['time' => '14:00', 'text' => 'George Town street art and Khoo Kongsi'], ['time' => '16:00', 'text' => 'Clan Jetties, then drop-off']],
                'inclusions' => ['Hotel pick-up and drop-off in George Town and Batu Ferringhi', 'Funicular ticket', 'Guide'],
                'exclusions' => ['Lunch', 'Temple donations'],
                'sicPrices' => [190, 135, 140, 100], 'privatePrices' => ['standard-sedan' => [520, 370], 'family-mpv' => [620, 450]],
            ],
        ];

        foreach ($tours as $i => $t) {
            if (! isset($city[$t['city']])) {
                continue;
            }
            $tour = Tour::withTrashed()->updateOrCreate(['slug' => $t['slug']], [
                'city_id' => $city[$t['city']], 'supplier_id' => $suppliers[$t['supplier']], 'title' => $t['title'],
                'summary' => $t['summary'], 'description' => $t['description'], 'highlights' => $t['highlights'],
                'itinerary' => $t['itinerary'], 'inclusions' => $t['inclusions'], 'exclusions' => $t['exclusions'],
                'duration_minutes' => $t['minutes'], 'has_sic' => true, 'has_private' => true,
                'sic_departure_times' => $t['sic'], 'private_start_times' => $t['private'],
                'child_age_from' => 3, 'child_age_to' => 11, 'min_pax' => 1, 'cancellation_hours' => 24,
                'is_active' => true, 'sort' => $i + 1, 'deleted_at' => null,
            ]);

            [$adultSell, $adultCost, $childSell, $childCost] = $t['sicPrices'];
            $tour->sicPrices()->updateOrCreate(['pax_type' => 'adult'], ['selling_price' => $adultSell, 'cost_price' => $adultCost, 'currency' => 'MYR']);
            $tour->sicPrices()->updateOrCreate(['pax_type' => 'child'], ['selling_price' => $childSell, 'cost_price' => $childCost, 'currency' => 'MYR']);

            foreach ($t['privatePrices'] as $slug => [$sell, $cost]) {
                if (isset($veh[$slug])) {
                    $tour->privatePrices()->updateOrCreate(['vehicle_type_id' => $veh[$slug]], ['selling_price' => $sell, 'cost_price' => $cost, 'currency' => 'MYR']);
                }
            }
        }
    }

    private function attractions(int $supplierId): void
    {
        $city = City::query()->pluck('id', 'slug');

        $attractions = [
            [
                'slug' => 'petronas-twin-towers-skybridge', 'city' => 'kuala-lumpur', 'title' => 'Petronas Twin Towers Skybridge & Observation Deck',
                'summary' => 'Walk the double-decker Skybridge on level 41 and look out over Kuala Lumpur from the observation deck on level 86.',
                'description' => 'Your visit starts with a high-speed lift to the Skybridge linking the two towers, then continues to the observation deck near the top for views across the city and the KLCC Park.',
                'good' => ['Choose your date; the time slot is confirmed by email', 'Arrive 15 minutes early at the Concourse level', 'Bring the passport or ID you booked with', 'Closed on Mondays'],
                'closed' => [1], 'refundable' => false, 'method' => 'supplier_eticket',
                'types' => [['Adult', '13+', false, 98, 80], ['Child', '3–12', false, 50, 40], ['Malaysian adult (MyKad)', '13+', true, 35, 28]],
            ],
            [
                'slug' => 'kl-tower-sky-deck', 'city' => 'kuala-lumpur', 'title' => 'KL Tower Observation Deck & Sky Deck',
                'summary' => 'Kuala Lumpur\'s classic viewpoint: the indoor observation deck at 276 m and the open-air Sky Deck above it.',
                'description' => 'Ride up Menara Kuala Lumpur, the tallest telecommunications tower in Southeast Asia. Enjoy 360° views from the observation deck, then step out onto the open-air Sky Deck for photos above the city.',
                'good' => ['Valid any time on the date you choose', 'Sky Deck may close in bad weather', 'Show the e-ticket on your phone'],
                'closed' => [], 'refundable' => true, 'method' => 'instant_voucher',
                'types' => [['Adult', '13+', false, 110, 92], ['Child', '4–12', false, 60, 50]],
            ],
            [
                'slug' => 'aquaria-klcc', 'city' => 'kuala-lumpur', 'title' => 'Aquaria KLCC',
                'summary' => 'Walk through a 90-metre underwater tunnel with sharks, rays and turtles right in the city centre.',
                'description' => 'Aquaria KLCC is home to thousands of sea and land creatures. Highlights include the underwater tunnel, feeding shows and the rainforest zone. It is next to the Kuala Lumpur Convention Centre, a short walk from the Petronas Towers.',
                'good' => ['Valid any time on the date you choose', 'Feeding show times are posted at the entrance', 'Free entry for children under 3'],
                'closed' => [], 'refundable' => true, 'method' => 'instant_voucher',
                'types' => [['Adult', '13+', false, 75, 62], ['Child', '3–12', false, 62, 51], ['Senior', '60+', false, 62, 51]],
            ],
            [
                'slug' => 'kl-bird-park', 'city' => 'kuala-lumpur', 'title' => 'KL Bird Park',
                'summary' => 'One of the world\'s largest free-flight walk-in aviaries, with hornbills, peacocks and flamingos in the Perdana Botanical Gardens.',
                'description' => 'Wander through landscaped aviaries where around 3,000 birds fly freely overhead. Do not miss the hornbill park, the daily bird show and feeding times.',
                'good' => ['Valid any time on the date you choose', 'Bird show daily at 12:30 and 15:30', 'Wear comfortable shoes'],
                'closed' => [], 'refundable' => true, 'method' => 'instant_voucher',
                'types' => [['Adult', '12+', false, 85, 70], ['Child', '3–11', false, 70, 58]],
            ],
            [
                'slug' => 'langkawi-skycab-skybridge', 'city' => 'langkawi', 'title' => 'Langkawi SkyCab & SkyBridge',
                'summary' => 'Ride one of the steepest cable cars in the world to the top of Mount Mat Cincang and walk the curved SkyBridge above the rainforest.',
                'description' => 'The SkyCab climbs over ancient rainforest to the summit of Mount Mat Cincang, with views over the Andaman Sea and southern Thailand on clear days. At the top, the SkyGlide takes you down to the famous curved SkyBridge.',
                'good' => ['Valid on the date you choose', 'Weather can pause the cable car; your ticket stays valid that day', 'SkyGlide to the SkyBridge included'],
                'closed' => [], 'refundable' => false, 'method' => 'supplier_eticket',
                'types' => [['Adult', '13+', false, 135, 112], ['Child', '3–12', false, 110, 91]],
            ],
        ];

        foreach ($attractions as $i => $a) {
            if (! isset($city[$a['city']])) {
                continue;
            }
            $attraction = Attraction::withTrashed()->updateOrCreate(['slug' => $a['slug']], [
                'city_id' => $city[$a['city']], 'supplier_id' => $supplierId, 'title' => $a['title'], 'summary' => $a['summary'],
                'description' => $a['description'], 'good_to_know' => $a['good'], 'closed_weekdays' => $a['closed'],
                'is_refundable' => $a['refundable'], 'cancellation_hours' => $a['refundable'] ? 24 : 0,
                'delivery_method' => $a['method'], 'is_active' => true, 'sort' => $i + 1, 'deleted_at' => null,
            ]);
            foreach ($a['types'] as $j => [$name, $age, $needsId, $sell, $cost]) {
                $attraction->ticketTypes()->updateOrCreate(['name' => $name], [
                    'age_label' => $age, 'requires_id' => $needsId, 'selling_price' => $sell, 'cost_price' => $cost,
                    'currency' => 'MYR', 'is_active' => true, 'sort' => $j + 1,
                ]);
            }
        }
    }

    private function extras(): void
    {
        $extras = [
            ['transfer', 'Child seat', 'For children up to 4 years', 10, 20, 2],
            ['transfer', 'Booster seat', 'For children 4–8 years', 8, 15, 2],
            ['transfer', 'Extra stop on the way', 'Up to 30 minutes, on the route', 25, 40, 1],
            ['tour', 'Hotel pick-up outside the city centre', 'Hotels up to 20 km from the centre', 30, 50, 1],
        ];
        foreach ($extras as $i => [$type, $name, $desc, $cost, $sell, $max]) {
            Extra::updateOrCreate(['product_type' => $type, 'name' => $name], [
                'description' => $desc, 'cost_price' => $cost, 'selling_price' => $sell, 'currency' => 'MYR',
                'max_quantity' => $max, 'is_active' => true, 'sort' => $i + 1,
            ]);
        }
    }
}
