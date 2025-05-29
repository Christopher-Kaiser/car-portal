<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Console\Kernel as ConsoleKernel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        $this->app->singleton('console.kernel', function ($app) {
            return new ConsoleKernel($app, $app['events']);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        //
    }
}