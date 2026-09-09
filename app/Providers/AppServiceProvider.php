<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;

use App\Observers\OrderObserver;
use App\Observers\PaymentObserver;
use App\Observers\ProductObserver;

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
        if (config('app.url')) {
            URL::forceRootUrl(config('app.url'));
        }

        /*
         * Only force HTTPS in production.
         *
         * Local development uses:
         * http://127.0.0.1:8000
         */
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        Order::observe(
        OrderObserver::class
        );

        Payment::observe(
            PaymentObserver::class
        );

        Product::observe(
            ProductObserver::class
        );
        }
}