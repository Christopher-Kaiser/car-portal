<?php

namespace App\Http\Controllers;

use App\Models\CarRequest;
use App\Models\GeneralUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CarRequestController extends Controller
{
    public function store(Request $request)
    {
        Log::info('Ride request submission started', ['user_id' => Auth::id()]);

        $user = Auth::user();
        if (!$user || $user->role !== 'user') {
            Log::warning('Unauthorized access to ride request', ['user_id' => Auth::id()]);
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $generalUser = GeneralUser::where('user_id', $user->id)->first();
        if (!$generalUser) {
            Log::error('No general user profile found', ['user_id' => $user->id]);
            return response()->json(['success' => false, 'error' => 'No user profile found'], 404);
        }

        $validated = $request->validate([
            'pickup_point' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'destination_lat' => 'required|numeric|between:-90,90',
            'destination_lng' => 'required|numeric|between:-180,180',
            'passengers' => 'required|integer|min:1|max:10',
            'request_date' => 'required|date',
        ]);

        try {
            $carRequest = CarRequest::create([
                'general_user_id' => $generalUser->id,
                'pickup_location' => $validated['pickup_point'],
                'dropoff_location' => $validated['destination'],
                'pickup_latitude' => $validated['pickup_lat'],
                'pickup_longitude' => $validated['pickup_lng'],
                'dropoff_latitude' => $validated['destination_lat'],
                'dropoff_longitude' => $validated['destination_lng'],
                'no_of_passengers' => $validated['passengers'],
                'status' => 'Pending',
                'assigned_at' => null,
                'driver_id' => null,
                'requested_at' => $validated['request_date'],
            ]);

            Log::info('Car request created successfully', [
                'request_id' => $carRequest->id,
                'general_user_id' => $generalUser->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ride requested successfully!',
                'request' => $carRequest,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create car request', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to request ride due to a server error',
            ], 500);
        }
    }
}