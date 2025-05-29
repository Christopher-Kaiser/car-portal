<?php

namespace App\Http\Controllers;

use App\Models\RideShare;
use App\Models\Trip;
use App\Models\CarRequest;
use App\Services\GoogleMapsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Driver;

class RideShareController extends Controller
{
    protected $mapsService;
    protected $maxDetourDistance = 5000; // 5km
    protected $maxDetourDuration = 900; // 15 minutes
    protected $maxPassengerCapacity = 4;

    public function __construct(GoogleMapsService $mapsService)
    {
        $this->mapsService = $mapsService;
    }

    public function findRideOptions(Request $request)
    {
        $request->validate([
            'pickup_lat' => 'required|numeric',
            'pickup_lng' => 'required|numeric',
            'destination_lat' => 'required|numeric',
            'destination_lng' => 'required|numeric',
            'pickup_location' => 'required|string',
            'destination' => 'required|string',
            'passenger_count' => 'required|integer|min:1|max:' . $this->maxPassengerCapacity
        ]);

        $pickupCoords = [
            'lat' => $request->pickup_lat,
            'lng' => $request->pickup_lng
        ];

        $dropoffCoords = [
            'lat' => $request->destination_lat,
            'lng' => $request->destination_lng
        ];

        // Find available direct ride drivers
        $availableDrivers = Driver::where('status', 'available')
            ->where('is_online', true)
            ->get()
            ->map(function ($driver) use ($pickupCoords) {
                $distance = $this->mapsService->calculateDistance(
                    $driver->current_location['lat'],
                    $driver->current_location['lng'],
                    $pickupCoords['lat'],
                    $pickupCoords['lng']
                );
                // Assuming average speed of 30 km/h (8.33 m/s)
                $estimatedArrival = $distance / 8.33;
                return [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'type' => 'direct',
                    'estimated_arrival' => $estimatedArrival,
                    'distance' => $distance
                ];
            });

        // Find matching ongoing trips
        $activeTrips = Trip::where('status', 'in_progress')
            ->whereHas('driver', function ($query) {
                $query->where('status', 'available');
            })
            ->get();

        $matchingTrips = $activeTrips->filter(function ($trip) use ($request, $pickupCoords, $dropoffCoords) {
            // Get the original route
            $originalRoute = $this->mapsService->calculateRoute(
                $trip->pickup_coordinates,
                $trip->dropoff_coordinates
            );

            if (!$originalRoute) {
                return false;
            }

            // Check if pickup and dropoff points are near the route
            $pickupNearRoute = $this->mapsService->isPointNearRoute($pickupCoords, $originalRoute['polyline']);
            $dropoffNearRoute = $this->mapsService->isPointNearRoute($dropoffCoords, $originalRoute['polyline']);

            if (!$pickupNearRoute || !$dropoffNearRoute) {
                return false;
            }

            // Calculate detour
            $detour = $this->mapsService->calculateDetour(
                [
                    'origin' => $trip->pickup_coordinates,
                    'destination' => $trip->dropoff_coordinates,
                    'distance' => $originalRoute['distance'],
                    'duration' => $originalRoute['duration']
                ],
                $pickupCoords,
                $dropoffCoords
            );

            if (!$detour) {
                return false;
            }

            // Check if detour is within acceptable limits
            if ($detour['distance'] > $this->maxDetourDistance || 
                $detour['duration'] > $this->maxDetourDuration) {
                return false;
            }

            // Check passenger capacity
            $currentPassengers = $trip->rideShares()
                ->where('status', 'accepted')
                ->sum('passenger_count');

            if (($currentPassengers + $request->passenger_count) > $this->maxPassengerCapacity) {
                return false;
            }

            return true;
        })->map(function ($trip) use ($pickupCoords, $dropoffCoords) {
            $detour = $this->mapsService->calculateDetour(
                [
                    'origin' => $trip->pickup_coordinates,
                    'destination' => $trip->dropoff_coordinates
                ],
                $pickupCoords,
                $dropoffCoords
            );

            return [
                'id' => $trip->id,
                'driver_name' => $trip->driver->name,
                'type' => 'share',
                'estimated_arrival' => $trip->estimated_arrival,
                'detour_distance' => $detour['distance'],
                'detour_duration' => $detour['duration'],
                'available_seats' => $this->maxPassengerCapacity - $trip->rideShares()
                    ->where('status', 'accepted')
                    ->sum('passenger_count')
            ];
        });

        return response()->json([
            'direct_rides' => $availableDrivers,
            'ride_shares' => $matchingTrips,
            'has_options' => $availableDrivers->isNotEmpty() || $matchingTrips->isNotEmpty()
        ]);
    }

    public function requestRide(Request $request)
    {
        $request->validate([
            'pickup_lat' => 'required|numeric',
            'pickup_lng' => 'required|numeric',
            'destination_lat' => 'required|numeric',
            'destination_lng' => 'required|numeric',
            'pickup_location' => 'required|string',
            'destination' => 'required|string',
            'passenger_count' => 'required|integer|min:1|max:' . $this->maxPassengerCapacity,
            'trip_id' => 'nullable|exists:trips,id',
            'driver_id' => 'nullable|exists:drivers,id',
            'notes' => 'nullable|string'
        ]);

        // Create the car request
        $carRequest = CarRequest::create([
            'user_id' => Auth::id(),
            'pickup_location' => $request->pickup_location,
            'dropoff_location' => $request->destination,
            'pickup_lat' => $request->pickup_lat,
            'pickup_lng' => $request->pickup_lng,
            'destination_lat' => $request->destination_lat,
            'destination_lng' => $request->destination_lng,
            'passenger_count' => $request->passenger_count,
            'status' => 'pending',
            'notes' => $request->notes
        ]);

        // If this is a ride share request
        if ($request->trip_id) {
            $trip = Trip::findOrFail($request->trip_id);
            
            $pickupCoords = [
                'lat' => $request->pickup_lat,
                'lng' => $request->pickup_lng
            ];

            $dropoffCoords = [
                'lat' => $request->destination_lat,
                'lng' => $request->destination_lng
            ];

            $originalRoute = $this->mapsService->calculateRoute(
                $trip->pickup_coordinates,
                $trip->dropoff_coordinates
            );

            $detour = $this->mapsService->calculateDetour(
                [
                    'origin' => $trip->pickup_coordinates,
                    'destination' => $trip->dropoff_coordinates,
                    'distance' => $originalRoute['distance'],
                    'duration' => $originalRoute['duration']
                ],
                $pickupCoords,
                $dropoffCoords
            );

            // Create the ride share record
            $rideShare = RideShare::create([
                'trip_id' => $trip->id,
                'request_id' => $carRequest->id,
                'status' => 'pending',
                'pickup_location' => $request->pickup_location,
                'dropoff_location' => $request->destination,
                'pickup_coordinates' => $pickupCoords,
                'dropoff_coordinates' => $dropoffCoords,
                'passenger_count' => $request->passenger_count,
                'detour_distance' => $detour['distance'],
                'detour_duration' => $detour['duration'],
                'estimated_pickup_time' => now()->addSeconds($detour['duration']),
                'estimated_dropoff_time' => now()->addSeconds($detour['duration'] * 2),
                'notes' => $request->notes
            ]);

            return response()->json([
                'message' => 'Ride share request sent successfully',
                'request' => $carRequest,
                'ride_share' => $rideShare
            ]);
        }

        // If this is a direct ride request
        if ($request->driver_id) {
            $carRequest->update([
                'driver_id' => $request->driver_id,
                'status' => 'assigned'
            ]);

            return response()->json([
                'message' => 'Direct ride request sent successfully',
                'request' => $carRequest
            ]);
        }

        return response()->json([
            'message' => 'Ride request sent successfully',
            'request' => $carRequest
        ]);
    }

    public function respondToRequest(Request $request, RideShare $rideShare)
    {
        $request->validate([
            'status' => 'required|in:accepted,rejected'
        ]);

        $rideShare->update([
            'status' => $request->status
        ]);

        if ($request->status === 'accepted') {
            $rideShare->request->update(['status' => 'accepted']);
        } else {
            $rideShare->request->update(['status' => 'rejected']);
        }

        return response()->json([
            'message' => 'Ride share request ' . $request->status,
            'ride_share' => $rideShare
        ]);
    }
} 