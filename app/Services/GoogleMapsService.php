<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class GoogleMapsService
{
    protected $apiKey;
    protected $baseUrl = 'https://maps.googleapis.com/maps/api';

    public function __construct()
    {
        $this->apiKey = config('services.google.maps_api_key');
    }

    public function geocodeAddress(string $address)
    {
        $cacheKey = 'geocode_' . md5($address);
        
        return Cache::remember($cacheKey, now()->addHours(24), function () use ($address) {
            $response = Http::get("{$this->baseUrl}/geocode/json", [
                'address' => $address,
                'key' => $this->apiKey
            ]);

            if ($response->successful() && $response->json('status') === 'OK') {
                $location = $response->json('results.0.geometry.location');
                return [
                    'lat' => $location['lat'],
                    'lng' => $location['lng']
                ];
            }

            return null;
        });
    }

    public function calculateRoute(array $origin, array $destination, array $waypoints = [])
    {
        $params = [
            'origin' => "{$origin['lat']},{$origin['lng']}",
            'destination' => "{$destination['lat']},{$destination['lng']}",
            'key' => $this->apiKey,
            'mode' => 'driving',
            'departure_time' => 'now'
        ];

        if (!empty($waypoints)) {
            $params['waypoints'] = implode('|', array_map(function ($point) {
                return "{$point['lat']},{$point['lng']}";
            }, $waypoints));
        }

        $response = Http::get("{$this->baseUrl}/directions/json", $params);

        if ($response->successful() && $response->json('status') === 'OK') {
            $route = $response->json('routes.0');
            return [
                'distance' => $route['legs'][0]['distance']['value'], // in meters
                'duration' => $route['legs'][0]['duration']['value'], // in seconds
                'polyline' => $route['overview_polyline']['points']
            ];
        }

        return null;
    }

    public function calculateDetour(array $originalRoute, array $pickup, array $dropoff)
    {
        // Calculate route with new pickup and dropoff points
        $newRoute = $this->calculateRoute($originalRoute['origin'], $originalRoute['destination'], [
            $pickup,
            $dropoff
        ]);

        if (!$newRoute) {
            return null;
        }

        // Calculate detour metrics
        $detourDistance = $newRoute['distance'] - $originalRoute['distance'];
        $detourDuration = $newRoute['duration'] - $originalRoute['duration'];

        return [
            'distance' => $detourDistance,
            'duration' => $detourDuration,
            'new_route' => $newRoute
        ];
    }

    public function isPointNearRoute(array $point, string $polyline, float $maxDistance = 1000)
    {
        // Decode the polyline
        $routePoints = $this->decodePolyline($polyline);
        
        // Find the closest point on the route
        $minDistance = PHP_FLOAT_MAX;
        foreach ($routePoints as $routePoint) {
            $distance = $this->calculateDistance(
                $point['lat'],
                $point['lng'],
                $routePoint['lat'],
                $routePoint['lng']
            );
            $minDistance = min($minDistance, $distance);
        }

        return $minDistance <= $maxDistance;
    }

    protected function decodePolyline(string $polyline)
    {
        $points = [];
        $index = 0;
        $len = strlen($polyline);
        $lat = 0;
        $lng = 0;

        while ($index < $len) {
            $b = 0;
            $shift = 0;
            $result = 0;

            do {
                $b = ord($polyline[$index++]) - 63;
                $result |= ($b & 0x1F) << $shift;
                $shift += 5;
            } while ($b >= 0x20);

            $dlat = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lat += $dlat;

            $shift = 0;
            $result = 0;

            do {
                $b = ord($polyline[$index++]) - 63;
                $result |= ($b & 0x1F) << $shift;
                $shift += 5;
            } while ($b >= 0x20);

            $dlng = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lng += $dlng;

            $points[] = [
                'lat' => $lat * 1e-5,
                'lng' => $lng * 1e-5
            ];
        }

        return $points;
    }

    public function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // in meters

        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $latDelta = $lat2 - $lat1;
        $lonDelta = $lon2 - $lon1;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($lat1) * cos($lat2) * pow(sin($lonDelta / 2), 2)));
        
        return $angle * $earthRadius;
    }
} 