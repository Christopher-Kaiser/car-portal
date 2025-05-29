<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Driver;
use App\Models\Shift;
use App\Models\CarRequest;
use App\Models\Trip;
use App\Models\Car;
use App\Models\ShiftLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'admin') {
            return redirect()->route('entry')->withErrors(['error' => 'Unauthorized']);
        }

        $today = now()->toDateString();
        $drivers = Driver::with('user')->get()->map(function ($driver) use ($today) {
            $shift = Shift::where('driver_id', $driver->id)
                ->whereDate('shift_start', $today)
                ->first();
            
            return [
                'id' => $driver->id,
                'name' => $driver->name ?? 'Unnamed Driver',
                'status' => $driver->status,
                'has_shift_today' => $shift ? true : false,
                'shift' => $shift ? [
                    'id' => $shift->id,
                    'shift_start' => $shift->shift_start->format('Y-m-d H:i:s'),
                    'shift_end' => $shift->shift_end->format('Y-m-d H:i:s'),
                    'car_id' => $shift->car_id,
                    'is_active' => $shift->is_active,
                ] : null,
            ];
        })->sortBy('id')->values()->toArray();

        // Run the manage shifts command to ensure everything is up-to-date
        Artisan::call('shifts:manage');

        $cars = Car::where('status', 'available')->get()->map(function ($car) {
            return [
                'id' => $car->id,
                'brand' => $car->brand,
                'model' => $car->model,
                'license_plate' => $car->license_plate,
            ];
        })->sortBy('id')->values()->toArray();

        $shifts = Shift::with('car')->get()->map(function ($shift) {
            return [
                'id' => $shift->id,
                'driver_id' => $shift->driver_id,
                'car_id' => $shift->car_id,
                'shift_start' => $shift->shift_start->format('Y-m-d H:i:s'),
                'shift_end' => $shift->shift_end->format('Y-m-d H:i:s'),
                'car' => $shift->car ? $shift->car->toArray() : null,
            ];
        })->toArray();

        $trips = Trip::with('carRequest')->get()->toArray();
        $assignedRequests = CarRequest::whereDoesntHave('trip')->get()->toArray();

        Log::info('Current time:', ['now' => now()->toDateTimeString()]);
        Log::info('Raw cars table state:', DB::table('cars')->get()->toArray());
        Log::info('Dashboard Available Cars:', $cars);

        $todayShifts = Shift::whereDate('shift_start', now()->toDateString())->pluck('id');

        $carRequests = CarRequest::with('trip', 'driver.user')
            ->whereDate('requested_at', now()->toDateString())
            ->get()
            ->map(function ($request) {
                return [
                    'id' => $request->id,
                    'trip_id' => $request->trip ? $request->trip->id : null,
                    'driver_name' => $request->driver ? ($request->driver->name ?? 'Unnamed') : 'Unassigned',
                    'pickup_point' => $request->pickup_location,
                    'destination' => $request->dropoff_location,
                    'status' => $request->trip ? $request->trip->status : $request->status,
                ];
            });

        $tripsToday = Trip::whereIn('shift_id', $todayShifts)
            ->with('carRequest', 'driver.user')
            ->get()
            ->map(function ($trip) {
                return [
                    'id' => $trip->id,
                    'trip_id' => $trip->id,
                    'driver_name' => $trip->driver ? ($trip->driver->name ?? 'Unnamed') : 'Unassigned',
                    'pickup_point' => $trip->car_request ? $trip->car_request->pickup_location : $trip->pickup_location,
                    'destination' => $trip->car_request ? $trip->car_request->dropoff_location : $trip->dropoff_location,
                    'status' => $trip->status,
                ];
            });

        $todayItems = $carRequests->merge($tripsToday)->sortByDesc(function ($item) {
            return $item['trip_id'] ? $item['trip_id'] : $item['id'];
        });

        return view('admin', [
            'drivers' => $drivers,
            'cars' => $cars,
            'todayItems' => $todayItems,
            'shifts' => $shifts,
            'trips' => $trips,
            'assignedRequests' => $assignedRequests,
        ]);
    }

    private function getDriverStatus($driverId)
    {
        $driver = Driver::find($driverId);
        return $driver ? $driver->status : 'off_duty';
    }

    public function assignShift(Request $request)
    {
        Log::info('AssignShift Request Data:', $request->all());

        try {
            $data = $request->validate([
                'driver_id' => 'required|exists:drivers,id',
                'shift_start' => 'nullable|date',
                'shift_end' => 'nullable|date|after:shift_start',
                'car_id' => 'nullable|exists:cars,id',
                'off_duty' => 'boolean',
            ]);

            $driverId = $data['driver_id'];
            $driver = Driver::find($driverId);
            $newStatus = 'off_duty';

            $shiftStartDate = $data['shift_start'] ? now()->parse($data['shift_start'])->startOfDay() : null;
            $existingShift = Shift::where('driver_id', $driverId)
                ->whereDate('shift_start', $shiftStartDate)
                ->first();

            if (!$data['off_duty']) {
                $car = Car::find($data['car_id']);
                if (!$car || $car->status !== 'available') {
                    Log::warning("Car {$data['car_id']} not available or not found: " . ($car ? $car->status : 'null'));
                    return response()->json([
                        'success' => false,
                        'error' => 'Selected car is not available.',
                    ], 400);
                }

                if ($existingShift) {
                    $existingShift->update([
                        'shift_start' => $data['shift_start'],
                        'shift_end' => $data['shift_end'],
                        'car_id' => $data['car_id'],
                        'is_active' => true,
                    ]);
                    $shift = $existingShift;
                    Log::info("Updated existing shift {$shift->id} for driver {$driverId}");
                } else {
                    $shift = Shift::create([
                        'driver_id' => $driverId,
                        'shift_start' => $data['shift_start'],
                        'shift_end' => $data['shift_end'],
                        'car_id' => $data['car_id'],
                        'is_active' => true,
                    ]);
                    Log::info("Created new shift {$shift->id} for driver {$driverId}");
                }

                $car->update(['status' => 'in_use']);
                Log::info("Car {$car->id} marked in_use for shift {$shift->id}");

                if ($shift->shift_start <= now() && $shift->shift_end >= now()) {
                    $ongoingTrip = Trip::where('driver_id', $driverId)
                        ->where('shift_id', $shift->id)
                        ->where('status', 'ongoing')
                        ->exists();
                    $newStatus = $ongoingTrip ? 'on_trip' : 'available';
                } else {
                    $newStatus = 'off_duty';
                }
                $driver->update(['status' => $newStatus]);
                Log::info("Driver {$driverId} status updated to {$newStatus} for shift {$shift->id}");
            } else {
                if ($existingShift) {
                    $car = Car::find($existingShift->car_id);
                    if ($car) {
                        $car->update(['status' => 'available']);
                        Log::info("Car {$car->id} freed from shift {$existingShift->id} due to off-duty");
                    }
                    
                    $existingShift->update(['is_active' => false]);
                    Log::info("Shift {$existingShift->id} for driver {$driverId} marked as inactive");
                }
                $driver->update(['status' => 'off_duty']);
                Log::info("Driver {$driverId} status set to off_duty");
            }

            // Run shifts:manage command to ensure everything is up-to-date
            Artisan::call('shifts:manage');

            $availableCars = Car::where('status', 'available')->get()->map(function ($car) {
                return [
                    'id' => $car->id,
                    'brand' => $car->brand,
                    'model' => $car->model,
                    'license_plate' => $car->license_plate,
                ];
            })->sortBy('id')->values()->toArray();

            Log::info('Current time:', ['now' => now()->toDateTimeString()]);
            Log::info('Raw cars table state after update:', DB::table('cars')->get()->toArray());
            Log::info('AssignShift Available Cars:', $availableCars);

            return response()->json([
                'success' => true,
                'driver_id' => $driverId,
                'status' => $newStatus,
                'shift_id' => !$data['off_duty'] ? $shift->id : null,
                'available_cars' => $availableCars,
            ]);
        } catch (\Exception $e) {
            Log::error('AssignShift Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'error' => 'Server error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function assignDriver(Request $request)
    {
        try {
            $data = $request->validate([
                'car_request_id' => 'required|integer|exists:car_requests,id',
                'driver_id' => 'required|integer|exists:drivers,id',
            ]);

            Log::info('AssignDriver: Request data received', $data);
            
            $carRequest = \App\Models\CarRequest::findOrFail($data['car_request_id']);
            $driver = \App\Models\Driver::findOrFail($data['driver_id']);
            
            // Check if driver is available
            if ($driver->status !== 'available') {
                Log::warning('AssignDriver: Driver not available', [
                    'driver_id' => $driver->id,
                    'current_status' => $driver->status
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Driver is not available for assignment.',
                ]);
            }
            
            // Update the car request with the assigned driver and current timestamp
            $carRequest->update([
                'driver_id' => $data['driver_id'],
                'status' => 'Assigned',
                'assigned_at' => now(),
            ]);
            
            Log::info('AssignDriver: Successfully assigned driver to request', [
                'car_request_id' => $carRequest->id,
                'driver_id' => $driver->id,
                'driver_status' => $driver->status,
                'request_status' => 'Assigned',
                'assigned_at' => $carRequest->assigned_at
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Driver assigned successfully',
                'car_request' => $carRequest,
            ]);
            
        } catch (\Exception $e) {
            Log::error('AssignDriver Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Server error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }
}