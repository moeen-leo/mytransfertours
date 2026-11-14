<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Data for the route map on the transfer results page: the two points and,
 * when an OpenRouteService key is set, the road route between them.
 * Routes are cached for 30 days per pair of locations, so the outside service
 * is asked once per route, not once per visitor.
 */
class RouteMap
{
    /** @return array{from: array{0: float, 1: float}, to: array{0: float, 1: float}, line: list<array{0: float, 1: float}>|null}|null */
    public function for(Location $from, Location $to): ?array
    {
        if (! $this->hasPoint($from) || ! $this->hasPoint($to)) {
            return null;
        }

        $a = [(float) $from->lat, (float) $from->lng];
        $b = [(float) $to->lat, (float) $to->lng];

        return ['from' => $a, 'to' => $b, 'line' => $this->roadLine($a, $b)];
    }

    private function hasPoint(Location $l): bool
    {
        return $l->lat !== null && $l->lng !== null && (float) $l->lat !== 0.0;
    }

    /** Road route as [lat, lng] points, or null (no key, or the service failed: the map then draws a straight line). */
    private function roadLine(array $a, array $b): ?array
    {
        $key = (string) config('services.openrouteservice.key');
        if ($key === '') {
            return null;
        }

        $cacheKey = 'route-line:'.md5(implode(',', [...$a, ...$b]));

        $line = Cache::remember($cacheKey, now()->addDays(30), function () use ($a, $b, $key) {
            try {
                $res = Http::timeout(4)->acceptJson()->get('https://api.openrouteservice.org/v2/directions/driving-car', [
                    'api_key' => $key,
                    'start' => $a[1].','.$a[0],
                    'end' => $b[1].','.$b[0],
                ]);
                $coords = $res->successful() ? $res->json('features.0.geometry.coordinates') : null;
                if (! is_array($coords) || count($coords) < 2) {
                    Log::warning('Route map: no route from OpenRouteService', ['status' => $res->status()]);

                    return false; // cached too, so a failing route isn't retried on every visit
                }

                // GeoJSON is [lng, lat]; thin long routes so the page stays small.
                $step = max(1, intdiv(count($coords), 400));
                $points = [];
                foreach ($coords as $i => $c) {
                    if ($i % $step === 0 || $i === count($coords) - 1) {
                        $points[] = [round($c[1], 5), round($c[0], 5)];
                    }
                }

                return $points;
            } catch (\Throwable $e) {
                Log::warning('Route map: OpenRouteService failed', ['error' => $e->getMessage()]);

                return false;
            }
        });

        return $line ?: null;
    }
}
