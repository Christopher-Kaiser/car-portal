<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\Shift;
use App\Models\Trip;
use App\Models\Driver;
use App\Models\CarRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DriverController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('entry')->withErrors(['error' => 'Unauthorized']);
        }

        if ($user->role !== 'driver') {
            return redirect()->route('entry')->withErrors(['error' => 'Only drivers can access this dashboard']);
        }

        $driver = Driver::where('user_id', $user->id)->first();
        if (!$driver) {
            return redirect()->route('entry')->withErrors(['error' => 'No driver profile found for this user']);
        }

        $shift = Shift::where('driver_id', $driver->id)
                      ->whereDate('shift_start', now()->toDateString())
                      ->first();

        $shifts = Shift::where('driver_id', $driver->id)
                       ->with('car')
                       ->orderBy('shift_start', 'desc')
                       ->get()
                       ->map(function ($shift) {
                           return [
                               'id' => $shift->id,
                               'driver_id' => $shift->driver_id,
                               'car_id' => $shift->car_id,
                               'shift_start' => $shift->shift_start->format('Y-m-d H:i:s'),
                               'shift_end' => $shift->shift_end->format('Y-m-d H:i:s'),
                               'car' => $shift->car ? $shift->car->toArray() : null,
                           ];
                       });

        $car = $shift ? Car::find($shift->car_id) : null;

        $trips = Trip::where('driver_id', $driver->id)
                     ->with('carRequest')
                     ->orderBy('started_at', 'desc')
                     ->get();

        // Include all requests (Assigned or Cancelled) not yet turned into trips
        $assignedRequests = CarRequest::where('driver_id', $driver->id)
                                      ->whereDoesntHave('trip')
                                      ->get();

        Log::info('Driver Dashboard Data', [
            'user_id' => $user->id,
            'driver_id' => $driver->id,
            'shift' => $shift ? $shift->toArray() : null,
            'shifts' => $shifts->toArray(),
            'car' => $car ? $car->toArray() : null,
            'trips' => $trips->toArray(),
            'assigned_requests' => $assignedRequests->toArray(),
        ]);

        return view('driver', [
            'driver' => $driver,
            'shift' => $shift,
            'shifts' => $shifts,
            'car' => $car,
            'trips' => $trips,
            'assignedRequests' => $assignedRequests,
        ]);
    }
}