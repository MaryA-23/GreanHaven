<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Observers\OrderObserver;
use App\Observers\PaymentObserver;
use App\Observers\ProductObserver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (config('app.url')) {
            URL::forceRootUrl(config('app.url'));
        }

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        Order::observe(OrderObserver::class);
        Payment::observe(PaymentObserver::class);
        Product::observe(ProductObserver::class);

        // Hide suspended suppliers' products in public browsing.
        // Administrative views and historical orders remain available.
        Product::addGlobalScope(
            'visible_supplier',
            function ($query) {
                if (!app()->bound('request')) {
                    return;
                }

                $request = request();

                if (
                    !$request->isMethod('GET') ||
                    !$request->is(
                        'api/products',
                        'api/products/*',
                        'api/categories',
                        'api/categories/*'
                    )
                ) {
                    return;
                }

                $actor = Auth::guard('sanctum')->user();

                if (
                    $actor &&
                    in_array(
                        $actor->role,
                        ['admin', 'super_admin', 'company'],
                        true
                    )
                ) {
                    return;
                }

                $query->whereDoesntHave(
                    'company',
                    fn ($company) =>
                        $company->where('is_suspended', true)
                );
            }
        );

        Company::created(function ($company) {
            DB::table('admin_alerts')->insert([
                'title' => 'New tenant registered',
                'message' => $company->name . ' joined GreenHaven.',
                'path' => '/super-admin/tenants',
                'created_at' => now(),
            ]);
        });

        Order::created(function ($order) {
            DB::table('admin_alerts')->insert([
                'title' => 'New order',
                'message' => 'Order GH-' . $order->id . ' was placed.',
                'path' => '/super-admin/orders',
                'created_at' => now(),
            ]);
        });
    }
}