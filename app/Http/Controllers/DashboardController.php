<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BinEvent;
use App\Services\RouteOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    // Depot: city maintenance depot (adjust to your real location)
    private const DEPOT_LAT = 41.1496;
    private const DEPOT_LNG = -8.6109;

    public function index(): View
    {
        return view('dashboard');
    }

    public function binStates(): JsonResponse
    {
        $binIds = BinEvent::distinct()->pluck('bin_id');

        $states = $binIds->map(function (string $binId) {
            $latest = BinEvent::where('bin_id', $binId)
                ->where('type', 'usage_event')
                ->orderBy('occurred_at', 'desc')
                ->first();

            if (!$latest) {
                $latest = BinEvent::where('bin_id', $binId)
                    ->orderBy('occurred_at', 'desc')
                    ->first();
            }

            if (!$latest) return null;

            $payload = $latest->payload ?? [];

            return [
                'bin_id'           => $binId,
                'lat'              => (float) $latest->location_y,
                'lng'              => (float) $latest->location_x,
                'current_weight'   => $payload['current_weight'] ?? null,
                'capacity_percent' => $payload['capacity_percent'] ?? null,
                'battery_level'    => $payload['battery_level'] ?? null,
                'lid_open'         => isset($payload['lid_closed']) ? !$payload['lid_closed'] : null,
            ];
        })->filter()->values()->toArray();

        return response()->json($states);
    }

    public function route(): JsonResponse
    {
        $bins = $this->binStates()->getData(true);

        $result = (new RouteOptimizer())->optimize($bins, self::DEPOT_LAT, self::DEPOT_LNG);

        return response()->json($result);
    }
}
