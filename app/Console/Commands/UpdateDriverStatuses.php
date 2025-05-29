<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Shift;
use App\Models\Driver;
use App\Models\Trip;
use Illuminate\Support\Facades\Log;

class UpdateDriverStatuses extends Command
{
    protected $signature = 'drivers:update-statuses';
    protected $description = 'Update driver statuses based on shift times';

    public function handle()
    {
        $now = now();
        Log::info('UpdateDriverStatuses - Starting command execution', [
            'now' => $now->toDateTimeString(),
            'timezone' => $now->timezone->getName()
        ]);

        // Log current state of shifts and drivers
        $allShifts = Shift::all();
        Log::info('UpdateDriverStatuses - Current shifts state:', [
            'total_shifts' => $allShifts->count(),
            'active_shifts' => $allShifts->where('is_active', true)->count(),
            'shifts' => $allShifts->map(function($shift) {
                return [
                    'id' => $shift->id,
                    'driver_id' => $shift->driver_id,
                    'start' => $shift->shift_start,
                    'end' => $shift->shift_end,
                    'is_active' => $shift->is_active
                ];
            })->toArray()
        ]);

        $allDrivers = Driver::all();
        Log::info('UpdateDriverStatuses - Current drivers state:', [
            'total_drivers' => $allDrivers->count(),
            'drivers' => $allDrivers->map(function($driver) {
                return [
                    'id' => $driver->id,
                    'status' => $driver->status
                ];
            })->toArray()
        ]);

        // Handle current shifts
        $currentShifts = Shift::where('is_active', true)
                             ->where('shift_start', '<=', $now)
                             ->where('shift_end', '>=', $now)
                       ->get();

        Log::info('UpdateDriverStatuses - Found current shifts:', [
            'count' => $currentShifts->count(),
            'shifts' => $currentShifts->map(function($shift) {
                return [
                    'id' => $shift->id,
                    'driver_id' => $shift->driver_id,
                    'start' => $shift->shift_start,
                    'end' => $shift->shift_end
                ];
            })->toArray()
        ]);

        foreach ($currentShifts as $shift) {
            $driver = Driver::find($shift->driver_id);
            if (!$driver) continue;

            // Check if driver has an ongoing trip
            $ongoingTrip = Trip::where('driver_id', $shift->driver_id)
                               ->where('shift_id', $shift->id)
                               ->where('status', 'ongoing')
                               ->exists();

            // Don't update status if driver is already 'on_trip'
            if ($driver->status === 'on_trip') {
                Log::info("Driver {$driver->id} is on a trip, status preserved", [
                    'shift_id' => $shift->id,
                    'status' => $driver->status
                ]);
                continue;
            }

            $newStatus = $ongoingTrip ? 'on_trip' : 'available';
            if ($driver->status !== $newStatus) {
                $oldStatus = $driver->status;
                $driver->update(['status' => $newStatus]);
                Log::info("Driver {$driver->id} status updated", [
                    'shift_id' => $shift->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'has_ongoing_trip' => $ongoingTrip
                ]);
            }
        }

        // Handle future shifts
        $futureShifts = Shift::where('is_active', true)
                            ->where('shift_start', '>', $now)
                            ->get();

        Log::info('UpdateDriverStatuses - Found future shifts:', [
            'count' => $futureShifts->count(),
            'shifts' => $futureShifts->map(function($shift) {
                return [
                    'id' => $shift->id,
                    'driver_id' => $shift->driver_id,
                    'start' => $shift->shift_start,
                    'end' => $shift->shift_end
                ];
            })->toArray()
        ]);

        foreach ($futureShifts as $shift) {
            $driver = Driver::find($shift->driver_id);
            
            // Don't update status if driver is already 'on_trip'
            if ($driver && $driver->status === 'on_trip') {
                Log::info("Driver {$driver->id} is on a trip, status preserved for future shift", [
                    'shift_id' => $shift->id,
                    'status' => $driver->status
                ]);
                continue;
            }
            
            if ($driver && $driver->status !== 'off_duty') {
                $oldStatus = $driver->status;
                $driver->update(['status' => 'off_duty']);
                Log::info("Driver {$driver->id} status updated for future shift", [
                    'shift_id' => $shift->id,
                    'old_status' => $oldStatus,
                    'new_status' => 'off_duty'
                ]);
            }
        }

        // Handle expired shifts
        $expiredShifts = Shift::where('is_active', true)
                             ->where('shift_end', '<', $now)
                             ->get();

        Log::info('UpdateDriverStatuses - Found expired shifts:', [
            'count' => $expiredShifts->count(),
            'shifts' => $expiredShifts->map(function($shift) {
                return [
                    'id' => $shift->id,
                    'driver_id' => $shift->driver_id,
                    'start' => $shift->shift_start,
                    'end' => $shift->shift_end
                ];
            })->toArray()
        ]);

        foreach ($expiredShifts as $shift) {
            // Mark shift as inactive
            $shift->update(['is_active' => false]);
            Log::info("Shift marked as inactive", [
                'shift_id' => $shift->id,
                'driver_id' => $shift->driver_id
            ]);

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

            if (!$activeShiftsForDriver) {
                $driver = Driver::find($shift->driver_id);
                
                // Don't update status if driver is already 'on_trip'
                if ($driver && $driver->status === 'on_trip') {
                    Log::info("Driver {$driver->id} is on a trip, status preserved for expired shift", [
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
        }

        // Log final state
        $finalDrivers = Driver::all();
        Log::info('UpdateDriverStatuses - Final drivers state:', [
            'drivers' => $finalDrivers->map(function($driver) {
                return [
                    'id' => $driver->id,
                    'status' => $driver->status
                ];
            })->toArray()
        ]);
    }
}