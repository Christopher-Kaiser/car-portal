<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    public function __construct()
    {
        Log::info('Kernel instantiated at ' . now()->toDateTimeString());
        parent::__construct(app(), app('events'));
    }

    protected function schedule(Schedule $schedule)
    {
        
    }

    protected $commands = [
        \App\Console\Commands\ManageShiftDetails::class,
    ];
}