<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        \App\Support\TenantIntegrity::boot();
        \Illuminate\Support\Facades\RateLimiter::for('public-view', fn(\Illuminate\Http\Request $r)=>\Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($r->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('public-booking', fn(\Illuminate\Http\Request $r)=>\Illuminate\Cache\RateLimiting\Limit::perMinute(6)->by($r->ip()));
    }
}
