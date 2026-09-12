<?php

use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminCustomerController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminTenantController;
use App\Http\Controllers\Admin\AdminTenantSubscriptionController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\DashboardController;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TenantAuthController;
use App\Http\Controllers\Api\TenantDashboardController;
use App\Http\Controllers\Api\TenantSubscriptionController;

use App\Http\Controllers\Auth\PublicVerifyEmailController;

use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TenantNotificationController;
use App\Http\Controllers\TenantSettingsController;

use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Customer Authentication
|--------------------------------------------------------------------------
*/

Route::post(
    '/register',
    [AuthController::class, 'register']
)->middleware('throttle:5,1');

Route::post(
    '/login',
    [AuthController::class, 'login']
)->middleware('throttle:5,1');


Route::middleware(
    'auth:sanctum'
)->group(function () {

    Route::post(
        '/logout',
        [AuthController::class, 'logout']
    );

    Route::get(
        '/user',
        [AuthController::class, 'user']
    );

    Route::post(
        '/profile',
        [AuthController::class, 'updateProfile']
    );

    Route::post(
        '/profile/password',
        [AuthController::class, 'changePassword']
    );
});


/*
|--------------------------------------------------------------------------
| Tenant Authentication
|--------------------------------------------------------------------------
*/

Route::prefix(
    'tenant'
)->group(function () {

    Route::post(
        '/register',
        [TenantAuthController::class, 'register']
    )->middleware(
        'throttle:5,1'
    );

    Route::post(
        '/login',
        [TenantAuthController::class, 'login']
    )->middleware(
        'throttle:5,1'
    );


    Route::middleware(
        'auth:sanctum'
    )->post(
        '/logout',
        [TenantAuthController::class, 'logout']
    );
});


/*
|--------------------------------------------------------------------------
| Tenant Subscriptions
|--------------------------------------------------------------------------
|
| These routes do NOT require an active subscription.
| An expired tenant must still be able to check status and renew.
|
*/

Route::prefix(
    'tenant/subscriptions'
)->group(function () {

    Route::middleware([
        'auth:sanctum',
        'role:company',
    ])->group(function () {

        Route::get(
            '/plans',
            [
                TenantSubscriptionController::class,
                'plans'
            ]
        );

        Route::get(
            '/status',
            [
                TenantSubscriptionController::class,
                'status'
            ]
        );

        Route::post(
            '/paystack/pay',
            [
                TenantSubscriptionController::class,
                'initialize'
            ]
        );
    });


    /*
    |--------------------------------------------------------------------------
    | Subscription Paystack Callback
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/paystack/callback',
        [
            TenantSubscriptionController::class,
            'callback'
        ]
    )->name(
        'tenant.subscriptions.paystack.callback'
    );


    /*
    |--------------------------------------------------------------------------
    | Subscription Paystack Webhook
    |--------------------------------------------------------------------------
    |
    | This may remain available.
    |
    | Your main Paystack dashboard webhook can point to:
    |
    | /api/payments/paystack/webhook
    |
    | because PaymentController now also understands
    | tenant subscription payments.
    |
    */

    Route::post(
        '/paystack/webhook',
        [
            TenantSubscriptionController::class,
            'webhook'
        ]
    )->name(
        'tenant.subscriptions.paystack.webhook'
    );
});


/*
|--------------------------------------------------------------------------
| Tenant Application
|--------------------------------------------------------------------------
|
| Tenant must:
|
| 1. Be authenticated
| 2. Have role "company"
| 3. Have an active subscription
|
*/

Route::prefix(
    'tenant'
)
    ->middleware([
        'auth:sanctum',
        'role:company',
        'subscription.active',
    ])
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/dashboard/summary',
            [
                TenantDashboardController::class,
                'summary'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Products
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/products',
            [
                ProductController::class,
                'tenantProducts'
            ]
        );

        Route::post(
            '/products',
            [
                ProductController::class,
                'storeTenantProduct'
            ]
        );

        Route::put(
            '/products/{id}',
            [
                ProductController::class,
                'updateTenantProduct'
            ]
        );

        Route::delete(
            '/products/{id}',
            [
                ProductController::class,
                'destroyTenantProduct'
            ]
        );

        Route::patch(
            '/products/{id}/stock',
            [
                ProductController::class,
                'updateTenantStock'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Categories
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/categories',
            [
                CategoryController::class,
                'tenantIndex'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Tenant Settings
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/settings',
            [
                TenantSettingsController::class,
                'show'
            ]
        );

        Route::put(
            '/settings/business',
            [
                TenantSettingsController::class,
                'updateBusiness'
            ]
        );

        Route::put(
            '/settings/notifications',
            [
                TenantSettingsController::class,
                'updateNotifications'
            ]
        );

        Route::put(
            '/settings/password',
            [
                TenantSettingsController::class,
                'changePassword'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Tenant Notifications
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/notifications',
            [
                TenantNotificationController::class,
                'index'
            ]
        );

        Route::get(
            '/notifications/unread-count',
            [
                TenantNotificationController::class,
                'unreadCount'
            ]
        );

        Route::patch(
            '/notifications/read-all',
            [
                TenantNotificationController::class,
                'markAllAsRead'
            ]
        );

        Route::patch(
            '/notifications/{id}/read',
            [
                TenantNotificationController::class,
                'markAsRead'
            ]
        );

        Route::delete(
            '/notifications/clear-all',
            [
                TenantNotificationController::class,
                'clearAll'
            ]
        );

        Route::delete(
            '/notifications/{id}',
            [
                TenantNotificationController::class,
                'destroy'
            ]
        );
    });


/*
|--------------------------------------------------------------------------
| Public Product Browsing
|--------------------------------------------------------------------------
|
| Product creation/editing remains tenant controlled.
|
*/

Route::prefix(
    'products'
)->group(function () {

    Route::get(
        '/',
        [ProductController::class, 'index']
    );

    Route::get(
        '/{id}',
        [ProductController::class, 'show']
    );
});


/*
|--------------------------------------------------------------------------
| Master Categories
|--------------------------------------------------------------------------
*/

Route::get(
    '/categories',
    [CategoryController::class, 'index']
);

Route::get(
    '/categories/{id}',
    [CategoryController::class, 'show']
);


Route::middleware([
    'auth:sanctum',
    'role:admin,super_admin',
])->group(function () {

    Route::post(
        '/categories',
        [CategoryController::class, 'store']
    );

    Route::put(
        '/categories/{id}',
        [CategoryController::class, 'update']
    );

    Route::delete(
        '/categories/{id}',
        [CategoryController::class, 'destroy']
    );
});


/*
|--------------------------------------------------------------------------
| Orders
|--------------------------------------------------------------------------
*/

Route::prefix(
    'orders'
)
    ->middleware(
        'auth:sanctum'
    )
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Customer Orders
        |--------------------------------------------------------------------------
        */

        Route::middleware(
            'role:user'
        )->group(function () {

            Route::post(
                '/',
                [OrderController::class, 'store']
            );

            Route::get(
                '/my',
                [OrderController::class, 'index']
            );

            Route::get(
                '/my/{id}',
                [OrderController::class, 'show']
            );

            Route::patch(
                '/my/{id}/cancel',
                [OrderController::class, 'cancel']
            );
        });


        /*
        |--------------------------------------------------------------------------
        | Tenant Orders
        |--------------------------------------------------------------------------
        */

        Route::middleware(
            'role:company'
        )->group(function () {

            Route::get(
                '/company',
                [OrderController::class, 'index']
            );

            Route::get(
                '/company/{id}',
                [OrderController::class, 'show']
            );
        });


        /*
        |--------------------------------------------------------------------------
        | Order Processing
        |--------------------------------------------------------------------------
        */

        Route::middleware(
            'role:company,admin,super_admin'
        )->group(function () {

            Route::patch(
                '/{id}/processing',
                [
                    OrderController::class,
                    'markAsProcessing'
                ]
            );

            Route::patch(
                '/{id}/completed',
                [
                    OrderController::class,
                    'markAsCompleted'
                ]
            );
        });


        /*
        |--------------------------------------------------------------------------
        | Admin Order Management
        |--------------------------------------------------------------------------
        */

        Route::middleware(
            'role:admin,super_admin'
        )->group(function () {

            Route::get(
                '/',
                [OrderController::class, 'index']
            );

            Route::get(
                '/{id}',
                [OrderController::class, 'show']
            );

            Route::patch(
                '/{id}/cancel',
                [
                    OrderController::class,
                    'adminCancel'
                ]
            );

            Route::patch(
                '/{id}/expire',
                [
                    OrderController::class,
                    'adminExpire'
                ]
            );
        });
    });


/*
|--------------------------------------------------------------------------
| Payments
|--------------------------------------------------------------------------
*/

Route::prefix(
    'payments'
)
    ->middleware(
        'auth:sanctum'
    )
    ->group(function () {

        Route::middleware(
            'role:user'
        )->get(
            '/my',
            [PaymentController::class, 'index']
        );


        Route::middleware(
            'role:company'
        )->get(
            '/company',
            [PaymentController::class, 'index']
        );


        Route::middleware(
            'role:admin,super_admin'
        )->get(
            '/',
            [PaymentController::class, 'index']
        );


        /*
        |--------------------------------------------------------------------------
        | Customer Paystack Payment
        |--------------------------------------------------------------------------
        */

        Route::middleware(
            'role:user'
        )->post(
            '/paystack/pay',
            [
                PaymentController::class,
                'initialize'
            ]
        );


        Route::get(
            '/{payment}',
            [
                PaymentController::class,
                'show'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Admin Payment Actions
        |--------------------------------------------------------------------------
        */

        Route::middleware(
            'role:admin,super_admin'
        )->group(function () {

            Route::post(
                '/manual',
                [
                    PaymentController::class,
                    'store'
                ]
            );

            Route::patch(
                '/{payment}',
                [
                    PaymentController::class,
                    'update'
                ]
            );

            Route::delete(
                '/{payment}',
                [
                    PaymentController::class,
                    'destroy'
                ]
            );
        });
    });


/*
|--------------------------------------------------------------------------
| Paystack Callback
|--------------------------------------------------------------------------
*/

Route::match(
    ['get', 'post'],
    '/payments/paystack/callback',
    [
        PaymentController::class,
        'callback'
    ]
)->name(
    'payments.paystack.callback'
);


/*
|--------------------------------------------------------------------------
| Main Paystack Webhook
|--------------------------------------------------------------------------
|
| Handles:
|
| - Customer order payments
| - Tenant subscription payments
|
*/

Route::post(
    '/payments/paystack/webhook',
    [
        PaymentController::class,
        'webhook'
    ]
)->name(
    'payments.paystack.webhook'
);


/*
|--------------------------------------------------------------------------
| Reports
|--------------------------------------------------------------------------
*/

Route::prefix(
    'reports'
)
    ->middleware(
        'auth:sanctum'
    )
    ->group(function () {

        Route::middleware(
            'role:user'
        )->get(
            '/my-sales',
            [
                ReportController::class,
                'salesSummary'
            ]
        );


        Route::middleware(
            'role:company'
        )->get(
            '/company-sales',
            [
                ReportController::class,
                'salesSummary'
            ]
        );


        Route::middleware(
            'role:admin,super_admin'
        )->group(function () {

            Route::get(
                '/orders',
                [
                    ReportController::class,
                    'ordersSummary'
                ]
            );

            Route::get(
                '/sales',
                [
                    ReportController::class,
                    'salesSummary'
                ]
            );

            Route::get(
                '/payments',
                [
                    ReportController::class,
                    'paymentsSummary'
                ]
            );
        });
    });


/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
*/

Route::prefix(
    'admin'
)->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Initial Super Admin Setup
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/setup',
        [
            AdminController::class,
            'register'
        ]
    )->middleware(
        'throttle:5,1'
    );


    /*
    |--------------------------------------------------------------------------
    | Admin Login
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/login',
        [
            AdminController::class,
            'login'
        ]
    )->middleware(
        'throttle:5,1'
    );


    /*
    |--------------------------------------------------------------------------
    | Admin Logout
    |--------------------------------------------------------------------------
    */

    Route::middleware(
        'auth:sanctum'
    )->post(
        '/logout',
        [
            AdminController::class,
            'logout'
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | Admin + Super Admin
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'auth:sanctum',
        'role:admin,super_admin',
    ])->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/dashboard',
            [
                DashboardController::class,
                'index'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Tenant Management
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/tenants',
            [
                AdminTenantController::class,
                'index'
            ]
        );

        Route::get(
            '/tenants/{id}',
            [
                AdminTenantController::class,
                'show'
            ]
        )->whereNumber(
            'id'
        );


        /*
        |--------------------------------------------------------------------------
        | Admin Profile
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/profile',
            [
                AdminController::class,
                'myProfile'
            ]
        );

        Route::put(
            '/profile',
            [
                AdminController::class,
                'updateProfile'
            ]
        );

        Route::put(
            '/profile/password',
            [
                AdminController::class,
                'changePassword'
            ]
        )->middleware(
            'throttle:5,1'
        );

        Route::get(
            '/profile/{uuid}',
            [
                AdminController::class,
                'profile'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Admin Users
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/users',
            [
                AdminUserController::class,
                'index'
            ]
        );

        Route::get(
            '/users/{user_id}',
            [
                AdminUserController::class,
                'show'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Admin Notifications
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/notifications',
            [
                AdminNotificationController::class,
                'index'
            ]
        );

        Route::get(
            '/notifications/unread-count',
            [
                AdminNotificationController::class,
                'unreadCount'
            ]
        );

        Route::patch(
            '/notifications/read-all',
            [
                AdminNotificationController::class,
                'markAllAsRead'
            ]
        );

        Route::patch(
            '/notifications/{id}/read',
            [
                AdminNotificationController::class,
                'markAsRead'
            ]
        );

        Route::delete(
            '/notifications/clear-all',
            [
                AdminNotificationController::class,
                'clearAll'
            ]
        );

        Route::delete(
            '/notifications/{id}',
            [
                AdminNotificationController::class,
                'destroy'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Customer Management
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/customers',
            [
                AdminCustomerController::class,
                'index'
            ]
        );

        Route::get(
            '/customers/{id}',
            [
                AdminCustomerController::class,
                'show'
            ]
        )->whereNumber(
            'id'
        );

        Route::patch(
            '/customers/{id}/status',
            [
                AdminCustomerController::class,
                'updateStatus'
            ]
        )->whereNumber(
            'id'
        );
    });


    /*
    |--------------------------------------------------------------------------
    | Super Admin Only
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'auth:sanctum',
        'role:super_admin',
    ])->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Tenant Subscriptions
        |--------------------------------------------------------------------------
        */

            Route::get(
                '/subscriptions',
                [AdminTenantSubscriptionController::class, 'index']
            );

            Route::post(
                '/subscriptions/{id}/verify-payment',
                [AdminTenantSubscriptionController::class, 'verifyPayment']
            )->whereNumber('id');

            Route::post(
                '/subscriptions/{id}/grant-access',
                [AdminTenantSubscriptionController::class, 'grantAccess']
            )->whereNumber('id');

        /*
        |--------------------------------------------------------------------------
        | Admin Accounts
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/accounts',
            [
                AdminAccountController::class,
                'index'
            ]
        );

        Route::patch(
            '/accounts/{uuid}',
            [
                AdminAccountController::class,
                'update'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Create Admin
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/register',
            [
                AdminController::class,
                'addnewuser'
            ]
        );


        Route::post(
            '/change_admin_role',
            [
                AdminAccountController::class,
                'changeRole'
            ]
        );
    });
});


/*
|--------------------------------------------------------------------------
| Cart
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'role:user'
])
    ->prefix(
        'cart'
    )
    ->group(function () {

        Route::get(
            '/',
            [
                CartController::class,
                'index'
            ]
        );

        Route::post(
            '/items',
            [
                CartController::class,
                'add'
            ]
        );

        Route::patch(
            '/items/{id}',
            [
                CartController::class,
                'update'
            ]
        );

        Route::delete(
            '/items/{id}',
            [
                CartController::class,
                'remove'
            ]
        );

        Route::delete(
            '/clear',
            [
                CartController::class,
                'clear'
            ]
        );

        Route::post(
            '/checkout',
            [
                CartController::class,
                'checkout'
            ]
        );
    });


/*
|--------------------------------------------------------------------------
| Email Verification
|--------------------------------------------------------------------------
*/

Route::get(
    '/email/verify/{id}/{hash}',
    [
        PublicVerifyEmailController::class,
        '__invoke'
    ]
)
    ->middleware([
        'signed',
        'throttle:6,1'
    ])
    ->name(
        'verification.verify'
    );