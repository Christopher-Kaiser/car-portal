<?php
namespace App\Http\Middleware;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Support\Facades\Log;

class Authenticate extends Middleware
{
    protected function redirectTo($request)
    {
        Log::info('Auth redirect', ['expectsJson' => $request->expectsJson()]);
        if (!$request->expectsJson()) {
            return route('sign-in');
        }
    }
}