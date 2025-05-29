<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\SignInController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\CarRequestController;
use App\Http\Controllers\RideShareController;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return response()
        ->view('entry')
        ->header("Cache-Control", "no-cache, no-store, must-revalidate")
        ->header("Pragma", "no-cache")
        ->header("Expires", "0");
})->name('entry');

// Process sign-in form submission using your custom controller
Route::post('/sign-in', [SignInController::class, 'signin'])->name('sign-in');

// Dashboard routes for role-based redirection
Route::get('/admin-dashboard', function () {
    $admin = Auth::user(); // Get logged-in admin

    if (!$admin) {
        return redirect()->route('entry')->withErrors(['error' => 'Unauthorized']);
    }

    return app(AdminController::class)->dashboard();
})->middleware([\App\Http\Middleware\RoleMiddleware::class . ':admin'])->name('admin.dashboard');

Route::get('/driver-dashboard', function () {
    $driver = Auth::user(); // Get logged-in driver

    if (!$driver) {
        return redirect()->route('entry')->withErrors(['error' => 'Unauthorized']);
    }

    return app(DriverController::class)->dashboard();
})->middleware([\App\Http\Middleware\RoleMiddleware::class . ':driver'])->name('driver.dashboard');

Route::get('/user-dashboard', function () {
    $user = Auth::user(); // Get logged-in user

    if (!$user) {
        return redirect()->route('entry')->withErrors(['error' => 'Unauthorized']);
    }

    return app(UserController::class)->dashboard();
})->middleware([\App\Http\Middleware\RoleMiddleware::class . ':user'])->name('user.dashboard');

// Ride request route
Route::post('/car/request', [CarRequestController::class, 'store'])->name('car.request')->middleware('auth');

Route::post('/admin/assign-driver', [AdminController::class, 'assignDriver'])->name('admin.assign-driver');
Route::post('/admin/assign-shift', [AdminController::class, 'assignShift'])->name('admin.assign-shift');

Route::get('/driver-statuses', function () {
    return App\Models\Driver::all(['id', 'status']);
});

Route::get('/shift-states', function () {
    $today = now()->toDateString();
    $shifts = App\Models\Shift::with('driver')
        ->where('is_active', true)
        ->get()
        ->map(function ($shift) use ($today) {
            return [
                'driver_id' => $shift->driver_id,
                'shift_id' => $shift->id,
                'shift_end' => $shift->shift_end,
                'has_shift_today' => $shift->shift_start->toDateString() === $today
            ];
        });
    
    return response()->json($shifts);
});

Route::get('/driver-shift-logs/{driverId}', function ($driverId) {
    $logs = App\Models\ShiftLog::where('driver_id', $driverId)
        ->orderBy('shift_date', 'desc')
        ->get()
        ->map(function ($log) {
            return [
                'id' => $log->id,
                'driver_id' => $log->driver_id,
                'shift_date' => $log->shift_date->format('Y-m-d'),
                'on_duty' => $log->on_duty,
                'formatted_date' => $log->shift_date->format('F j, Y')
            ];
        });
    
    return response()->json($logs);
});

Route::middleware(['auth'])->group(function () {
    Route::post('/trips/start/{requestId}', [TripController::class, 'startTrip'])->name('trips.start');
    Route::post('/trips/{id}/end', [TripController::class, 'endTrip'])->name('trips.end');
    Route::post('/trips/{id}/cancel', [TripController::class, 'cancelTrip'])->name('trips.cancel');
    Route::post('/trips/cancel-request/{requestId}', [TripController::class, 'cancelRequest'])->name('trips.cancel-request');

    // Ride sharing routes
    Route::post('/ride-share/find-ride-options', [RideShareController::class, 'findRideOptions'])->name('ride-share.find-options');
    Route::post('/ride-share/request', [RideShareController::class, 'requestRide'])->name('ride-share.request');
    Route::post('/ride-share/{rideShare}/respond', [RideShareController::class, 'respondToRequest'])->name('ride-share.respond');
});