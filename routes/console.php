<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\ManageShiftDetails;

// Register the shift management command
Artisan::command('shifts:manage', function () {
    $command = app(ManageShiftDetails::class);
    $command->handle();
})->purpose('Manage shift details, car status, and driver status');

// Schedule the command
Schedule::command('shifts:manage')->everyMinute();