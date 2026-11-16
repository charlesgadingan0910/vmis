<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Driver-app API limits. Login/password calls are strict (per IP);
        // everything else is generous per device — a live trip pings about
        // 6 times a minute, so 120/min leaves wide headroom for normal use.
        RateLimiter::for('api-login', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('vmis-api', fn (Request $request) => Limit::perMinute(120)->by(
            $request->bearerToken() ? sha1($request->bearerToken()) : $request->ip()
        ));
    }
}
