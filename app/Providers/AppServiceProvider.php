<?php

namespace App\Providers;

use App\Models\Product;
use App\Observers\ProductObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Product::observe(ProductObserver::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('store', function (Request $request) {
            return Limit::perMinute(120)->by('store:'.$request->ip());
        });

        RateLimiter::for('store-order', function (Request $request) {
            return Limit::perMinute(10)->by('store-order:'.$request->ip());
        });

        RateLimiter::for('store-lookup', function (Request $request) {
            return Limit::perMinute(60)->by('store-lookup:'.$request->ip());
        });

        RateLimiter::for('cashier-lookup', function (Request $request) {
            return Limit::perMinute(60)->by('cashier-lookup:'.($request->user()?->id ?: $request->ip()));
        });
    }
}
