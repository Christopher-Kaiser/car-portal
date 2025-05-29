<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Shift;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Car;
use Illuminate\Support\Facades\Log;

class ManageShiftDetails extends Command
{
    protected $signature = 'shifts:manage';
    protected $description = 'Manage shift details, car status, and driver status';

    public function handle()
    {
        $now = now();
        Log::info('ManageShiftDetails - Starting command execution', [
            'now' => $now->toDateTimeString(),
            'timezone' => $now->timezone->getName()
        ]);

        // Log current state of shifts, drivers, and cars
        $this->logCurrentState();

        // Handle current shifts - Set drivers to 'available' or 'on_trip'
        $this->handleCurrentShifts($now);

        // Handle future shifts - Set drivers to 'off_duty'
        $this->handleFutureShifts($now);

        // Handle expired shifts - Mark shifts as inactive, release cars, set drivers to 'off_duty'
        $this->handleExpiredShifts($now);

        // Log final state
        $this->logFinalState();

        // Use Log instead of $this->info to avoid console output issues
        Log::info('ManageShiftDetails - Shift management completed successfully');
        
        return 0; // Return successful exit code
    }

    private function logCurrentState()
    {
        $allShifts = Shift::all();
        Log::info('ManageShiftDetails - Current shifts state:', [
            'total_shifts' => $allShifts->count(),
            'active_shifts' => $allShifts->where('is_active', true)->count(),
            'shifts' => $allShifts->map(function($shift) {
                return [
                    'id' => $shift->id,
                    'driver_id' => $shift->driver_id,
                    'car_id' => $shift->car_id,
                    'start' => $shift->shift_start,
                    'end' => $shift->shift_end,
                    'is_active' => $shift->is_active
                ];
            })->toArray()
        ]);

        $allDrivers = Driver::all();
        Log::info('ManageShiftDetails - Current drivers state:', [
            'total_drivers' => $allDrivers->count(),
            'drivers' => $allDrivers->map(function($driver) {
                return [
                    'id' => $driver->id,
                    'status' => $driver->status
                ];
            })->toArray()
        ]);

        $allCars = Car::all();
        Log::info('ManageShiftDetails - Current cars state:', [
            'total_cars' => $allCars->count(),
            'cars' => $allCars->map(function($car) {
                return [
                    'id' => $car->id,
                    'status' => $car->status
                ];
            })->toArray()
        ]);
    }

    private function handleCurrentShifts($now)
    {
        $currentShifts = Shift::where('is_active', true)
                             ->where('shift_start', '<=', $now)
                             ->where('shift_end', '>=', $now)
                             ->get();

        Log::info('ManageShiftDetails - Found current shifts:', [
            'count' => $currentShifts->count(),
            'shifts' => $currentShifts->map(function($shift) {
                return [
                    'id' => $shift->id,
                    'driver_id' => $shift->driver_id,
                    'car_id' => $shift->car_id,
                    'start' => $shift->shift_start,
                    'end' => $shift->shift_end
                ];
            })->toArray()
        ]);

        foreach ($currentShifts as $shift) {
            $driver = Driver::find($shift->driver_id);
            if (!$driver) continue;

            // Don't update status if driver is already 'on_trip'
            if ($driver->status === 'on_trip') {
                Log::info("Driver {$driver->id} is on a trip, status preserved in current shift", [
                    'shift_id' => $shift->id,
                    'status' => $driver->status
                ]);
            } else {
                $ongoingTrip = Trip::where('driver_id', $shift->driver_id)
                                 ->where('shift_id', $shift->id)
                                 ->where('status', 'ongoing')
                                 ->exists();

                $newStatus = $ongoingTrip ? 'on_trip' : 'available';
                if ($driver->status !== $newStatus) {
                    $oldStatus = $driver->status;
                    $driver->update(['status' => $newStatus]);
                    Log::info("Driver status updated for current shift", [
                        'driver_id' => $driver->id,
                        'shift_id' => $shift->id,
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'has_ongoing_trip' => $ongoingTrip
                    ]);
                }
            }

            // Ensure car is marked as in_use
            $car = Car::find($shift->car_id);
            if ($car && $car->status !== 'in_use') {
                $oldStatus = $car->status;
                $car->update(['status' => 'in_use']);
                Log::info("Car status updated for current shift", [
                    'car_id' => $car->id,
                    'shift_id' => $shift->id,
                    'old_status' => $oldStatus,
                    'new_status' => 'in_use'
                ]);
            }
        }
    }

    private function handleFutureShifts($now)
    {
        $futureShifts = Shift::where('is_active', true)
                            ->where('shift_start', '>', $now)
                            ->get();

        Log::info('ManageShiftDetails - Found future shifts:', [
            'count' => $futureShifts->count(),
            'shifts' => $futureShifts->map(function($shift) {
                return [
                    'id' => $shift->id,
                    'driver_id' => $shift->driver_id,
                    'car_id' => $shift->car_id,
                    'start' => $shift->shift_start,
                    'end' => $shift->shift_end
                ];
            })->toArray()
        ]);

        foreach ($futureShifts as $shift) {
            $driver = Driver::find($shift->driver_id);
            
            // Don't update status if driver is already 'on_trip'
            if ($driver && $driver->status === 'on_trip') {
                Log::info("Driver {$driver->id} is on a trip, status preserved in future shift", [
                    'shift_id' => $shift->id,
                    'status' => $driver->status
                ]);
                continue;
            }
            
            if ($driver && $driver->status !== 'off_duty') {
                // Check if driver has any current shifts
                $hasCurrentShifts = Shift::where('driver_id', $shift->driver_id)
                    ->where('id', '!=', $shift->id)
                    ->where('is_active', true)
                    ->where('shift_start', '<=', $now)
                    ->where('shift_end', '>=', $now)
                    ->exists();
                
                // Only set to off_duty if they don't have current shifts
                if (!$hasCurrentShifts) {
                    $oldStatus = $driver->status;
                    $driver->update(['status' => 'off_duty']);
                    Log::info("Driver status updated for future shift", [
                        'driver_id' => $driver->id,
                        'shift_id' => $shift->id,
                        'old_status' => $oldStatus,
                        'new_status' => 'off_duty'
                    ]);
                }
            }
        }
    }

    private function handleExpiredShifts($now)
    {
        $expiredShifts = Shift::where('is_active', true)
                             ->where('shift_end', '<', $now)
                             ->get();

        Log::info('ManageShiftDetails - Found expired shifts:', [
            'count' => $expiredShifts->count(),
            'shifts' => $expiredShifts->map(function($shift) {
                return [
                    'id' => $shift->id,
                    'driver_id' => $shift->driver_id,
                    'car_id' => $shift->car_id,
                    'start' => $shift->shift_start,
                    'end' => $shift->shift_end
                ];
            })->toArray()
        ]);

        foreach ($expiredShifts as $shift) {
            // Check if car has any other active shifts
            $activeShiftsForCar = Shift::where('car_id', $shift->car_id)
                ->where('id', '!=', $shift->id)
                ->where('is_active', true)
                ->where(function ($query) use ($now) {
                    $query->where('shift_end', '>', $now)
                        ->orWhere(function ($q) use ($now) {
                            $q->where('shift_start', '<=', $now)
                                ->where('shift_end', '>=', $now);
                        });
                })
                ->exists();

            Log::info("Checking active shifts for car", [
                'car_id' => $shift->car_id,
                'has_active_shifts' => $activeShiftsForCar
            ]);

            // If car is not assigned to any other active shifts, mark it as available
            if (!$activeShiftsForCar) {
                $car = Car::find($shift->car_id);
                if ($car && $car->status !== 'available') {
                    $oldStatus = $car->status;
                    $car->update(['status' => 'available']);
                    Log::info("Car status updated for expired shift", [
                        'car_id' => $car->id,
                        'shift_id' => $shift->id,
                        'old_status' => $oldStatus,
                        'new_status' => 'available'
                    ]);
                }
            }

            // Check if driver has any other active shifts
            $activeShiftsForDriver = Shift::where('driver_id', $shift->driver_id)
                ->where('id', '!=', $shift->id)
                ->where('is_active', true)
                ->where(function ($query) use ($now) {
                    $query->where('shift_end', '>', $now)
                        ->orWhere(function ($q) use ($now) {
                            $q->where('shift_start', '<=', $now)
                                ->where('shift_end', '>=', $now);
                        });
                })
                ->exists();

            Log::info("Checking active shifts for driver", [
                'driver_id' => $shift->driver_id,
                'has_active_shifts' => $activeShiftsForDriver
            ]);

            // If driver has no other active shifts, set status to off_duty
            if (!$activeShiftsForDriver) {
                $driver = Driver::find($shift->driver_id);
                
                // Don't update status if driver is already 'on_trip'
                if ($driver && $driver->status === 'on_trip') {
                    Log::info("Driver {$driver->id} is on a trip, status preserved in expired shift", [
                        'shift_id' => $shift->id,
                        'status' => $driver->status
                    ]);
                    continue;
                }
                
                if ($driver && $driver->status !== 'off_duty') {
                    $oldStatus = $driver->status;
                    $driver->update(['status' => 'off_duty']);
                    Log::info("Driver status updated for expired shift", [
                        'driver_id' => $driver->id,
                        'shift_id' => $shift->id,
                        'old_status' => $oldStatus,
                        'new_status' => 'off_duty'
                    ]);
                }
            }

            // Mark the shift as inactive
            $shift->update(['is_active' => false]);
            Log::info("Shift marked as inactive", [
                'shift_id' => $shift->id,
                'driver_id' => $shift->driver_id,
                'car_id' => $shift->car_id
            ]);
        }
    }

    private function logFinalState()
    {
        $finalDrivers = Driver::all();
        Log::info('ManageShiftDetails - Final drivers state:', [
            'drivers' => $finalDrivers->map(function($driver) {
                return [
                    'id' => $driver->id,
                    'status' => $driver->status
                ];
            })->toArray()
        ]);

        $finalCars = Car::all();
        Log::info('ManageShiftDetails - Final cars state:', [
            'cars' => $finalCars->map(function($car) {
                return [
                    'id' => $car->id,
                    'status' => $car->status
                ];
            })->toArray()
        ]);
    }
} 