<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\CarRequest;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TripController extends Controller
{
    public function startTrip(Request $request, $requestId)
    {
        Log::info('Start Trip called', ['request_id' => $requestId, 'user_id' => Auth::id()]);
        $user = Auth::user();
        if (!$user || $user->role !== 'driver') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $driver = $user->driver;
        if (!$driver) {
            Log::error('No driver profile found for user', ['user_id' => $user->id]);
            return response()->json(['error' => 'No driver profile found'], 404);
        }

        $carRequest = CarRequest::where('id', $requestId)
                                ->where('status', 'Assigned')
                                ->where('driver_id', $driver->id)
                                ->first();

        if (!$carRequest) {
            Log::warning('Car request not found, not assigned to you, or not assignable', ['request_id' => $requestId, 'driver_id' => $driver->id]);
            return response()->json(['error' => 'Car request not found, not assigned to you, or not assignable'], 404);
        }

        $shift = $driver->shifts()->whereDate('shift_start', now()->toDateString())->first();
        if (!$shift) {
            Log::warning('No active shift found for today', ['driver_id' => $driver->id]);
            return response()->json(['error' => 'No active shift found for today'], 400);
        }

        try {
            $trip = Trip::create([
                'driver_id' => $driver->id,
                'shift_id' => $shift->id,
                'request_id' => $carRequest->id,
                'status' => 'Ongoing',
                'started_at' => now(),
            ]);

            $driver->update(['status' => 'on_trip']);
            Log::info("Driver {$driver->id} status updated to on_trip for trip {$trip->id}");

            $trip->load('carRequest');
        } catch (\Exception $e) {
            Log::error('Failed to create trip', ['error' => $e->getMessage(), 'request_id' => $requestId, 'driver_id' => $driver->id]);
            return response()->json(['error' => 'Failed to start trip due to a server error'], 500);
        }

        Log::info('Trip started', ['trip_id' => $trip->id, 'driver_id' => $driver->id]);
        return response()->json(['success' => true, 'trip' => $trip]);
    }

    public function endTrip(Request $request, $id)
    {
        Log::info('End Trip called', ['id' => $id, 'user_id' => Auth::id()]);
        $user = Auth::user();
        if (!$user || $user->role !== 'driver') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $driver = $user->driver;
        if (!$driver) {
            Log::error('No driver profile found for user', ['user_id' => $user->id]);
            return response()->json(['error' => 'No driver profile found'], 404);
        }

        $trip = Trip::where('id', $id)
                    ->where('driver_id', $driver->id)
                    ->where('status', 'Ongoing')
                    ->first();

        if (!$trip) {
            Log::warning('Trip not found or not editable', ['id' => $id, 'driver_id' => $driver->id]);
            return response()->json(['error' => 'Trip not found or not editable'], 404);
        }

        $trip->ended_at = now();
        $trip->status = 'Completed';
        $trip->save();

        $driver->update(['status' => 'available']);
        Log::info("Driver {$driver->id} status updated to available after trip {$trip->id} ended");

        Log::info('Trip ended', ['trip_id' => $trip->id, 'driver_id' => $driver->id]);
        return response()->json(['success' => true, 'trip' => $trip]);
    }

    public function cancelTrip(Request $request, $id)
    {
        Log::info('Cancel Trip called', ['id' => $id, 'user_id' => Auth::id()]);
        $user = Auth::user();
        if (!$user || $user->role !== 'driver') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $driver = $user->driver;
        if (!$driver) {
            Log::error('No driver profile found for user', ['user_id' => $user->id]);
            return response()->json(['error' => 'No driver profile found'], 404);
        }

        $trip = Trip::where('id', $id)
                    ->where('driver_id', $driver->id)
                    ->where('status', 'Ongoing')
                    ->first();

        if (!$trip) {
            Log::warning('Trip not found or not cancellable', ['id' => $id, 'driver_id' => $driver->id]);
            return response()->json(['error' => 'Trip not found or not cancellable'], 404);
        }

        $trip->status = 'Cancelled';
        $trip->ended_at = now();
        $trip->save();

        $driver->update(['status' => 'available']);
        Log::info("Driver {$driver->id} status updated to available after trip {$trip->id} cancelled");

        Log::info('Trip cancelled', ['trip_id' => $trip->id, 'driver_id' => $driver->id]);
        return response()->json(['success' => true, 'trip' => $trip]);
    }

    public function cancelRequest(Request $request, $requestId)
    {
        Log::info('Cancel Request called', ['request_id' => $requestId, 'user_id' => Auth::id()]);
        $user = Auth::user();
        if (!$user || $user->role !== 'driver') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $driver = $user->driver;
        if (!$driver) {
            Log::error('No driver profile found for user', ['user_id' => $user->id]);
            return response()->json(['error' => 'No driver profile found'], 404);
        }

        $carRequest = CarRequest::where('id', $requestId)
                                ->where('status', 'Assigned')
                                ->where('driver_id', $driver->id)
                                ->first();

        if (!$carRequest) {
            Log::warning('Car request not found, not assigned to you, or not cancellable', ['request_id' => $requestId, 'driver_id' => $driver->id]);
            return response()->json(['error' => 'Car request not found, not assigned to you, or not cancellable'], 404);
        }

        $carRequest->status = 'Cancelled';
        $carRequest->save();

        Log::info('Car request cancelled', ['request_id' => $carRequest->id, 'driver_id' => $driver->id]);
        return response()->json(['success' => true, 'request' => $carRequest]);
    }
}