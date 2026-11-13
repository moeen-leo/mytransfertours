<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Map coordinates for the Malaysia starter locations, so the transfer results
 * page can show the route map. Only fills locations that have none yet:
 * coordinates typed in the admin are never overwritten.
 */
return new class extends Migration
{
    private const COORDINATES = [
        'KL International Airport (KLIA Terminal 1)' => [2.7456, 101.7072],
        'KL International Airport (KLIA Terminal 2)' => [2.7440, 101.6860],
        'Sultan Abdul Aziz Shah Airport (Subang)' => [3.1306, 101.5490],
        'Kuala Lumpur City Centre (hotel)' => [3.1579, 101.7123],
        'Bukit Bintang (hotel)' => [3.1466, 101.7113],
        'KL Sentral Station' => [3.1340, 101.6865],
        'Port Klang Cruise Terminal' => [2.9990, 101.3920],
        'Genting Highlands (hotel)' => [3.4236, 101.7934],
        'Langkawi International Airport' => [6.3297, 99.7287],
        'Pantai Cenang (hotel)' => [6.2920, 99.7280],
        'Penang International Airport' => [5.2971, 100.2770],
        'George Town (hotel)' => [5.4141, 100.3288],
        'Batu Ferringhi (hotel)' => [5.4717, 100.2465],
    ];

    public function up(): void
    {
        foreach (self::COORDINATES as $name => [$lat, $lng]) {
            DB::table('locations')
                ->where('name', $name)
                ->where(fn ($q) => $q->whereNull('lat')->orWhereNull('lng'))
                ->update(['lat' => $lat, 'lng' => $lng]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the coordinates are harmless and may have been edited since.
    }
};
