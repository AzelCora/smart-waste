<?php

declare(strict_types=1);

namespace App\Services;

class RouteOptimizer
{
    // Only include bins at or above this fill level
    private const THRESHOLD_PERCENT = 30.0;

    /**
     * @param array $bins  Each bin: ['bin_id', 'lat', 'lng', 'capacity_percent', ...]
     * @param float $depotLat
     * @param float $depotLng
     * @return array  Ordered route: depot → bins → depot, with total distance (km)
     */
    public function optimize(array $bins, float $depotLat, float $depotLng): array
    {
        $candidates = array_values(array_filter(
            $bins,
            fn($b) => ($b['capacity_percent'] ?? 0) >= self::THRESHOLD_PERCENT
        ));

        if (empty($candidates)) {
            return ['depot' => ['lat' => $depotLat, 'lng' => $depotLng], 'route' => [], 'total_km' => 0];
        }

        $route = $this->nearestNeighbour($candidates, $depotLat, $depotLng);

        $totalKm = $this->routeDistance($route, $depotLat, $depotLng);

        return [
            'depot'    => ['lat' => $depotLat, 'lng' => $depotLng],
            'route'    => $route,
            'total_km' => round($totalKm, 2),
        ];
    }

    private function nearestNeighbour(array $bins, float $fromLat, float $fromLng): array
    {
        $unvisited = $bins;
        $route = [];

        $curLat = $fromLat;
        $curLng = $fromLng;

        while (!empty($unvisited)) {
            $nearest = null;
            $nearestDist = PHP_FLOAT_MAX;
            $nearestIdx = 0;

            foreach ($unvisited as $i => $bin) {
                $d = $this->haversine($curLat, $curLng, $bin['lat'], $bin['lng']);
                if ($d < $nearestDist) {
                    $nearestDist = $d;
                    $nearest = $bin;
                    $nearestIdx = $i;
                }
            }

            $route[] = $nearest;
            $curLat = $nearest['lat'];
            $curLng = $nearest['lng'];
            array_splice($unvisited, $nearestIdx, 1);
        }

        return $route;
    }

    private function routeDistance(array $route, float $depotLat, float $depotLng): float
    {
        $total = 0.0;
        $prevLat = $depotLat;
        $prevLng = $depotLng;

        foreach ($route as $bin) {
            $total += $this->haversine($prevLat, $prevLng, $bin['lat'], $bin['lng']);
            $prevLat = $bin['lat'];
            $prevLng = $bin['lng'];
        }

        // Return to depot
        $total += $this->haversine($prevLat, $prevLng, $depotLat, $depotLng);

        return $total;
    }

    /** Haversine distance in km */
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * asin(sqrt($a));
    }
}
