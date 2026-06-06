<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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
        Vite::prefetch(concurrency: 3);
        \Illuminate\Support\Facades\URL::forceRootUrl(config('app.url'));
        RateLimiter::for('internal', function (Request $request) {
            return Limit::perMinute(120)->by(
                $request->header('X-Internal-Token') ?: $request->ip()
            );
        });
    }
}
