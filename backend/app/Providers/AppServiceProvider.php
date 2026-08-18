<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('guest-api', fn (Request $r) => [Limit::perMinute(20)->by($r->input('install_id', 'none')), Limit::perMinute(60)->by($r->ip())]);
        RateLimiter::for('account-api', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('site-api', fn (Request $r) => [Limit::perMinute(30)->by($r->bearerToken() ?: 'none'), Limit::perMinute(60)->by($r->ip())]);
    }
}
