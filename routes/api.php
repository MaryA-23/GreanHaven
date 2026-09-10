<?php

use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminTenantController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TenantAuthController;
use App\Http\Controllers\Api\TenantDashboardController;
use App\Http\Controllers\Auth\PublicVerifyEmailController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TenantNotificationController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\TenantSettingsController;
use Illuminate\Support\Facades\Route;


// Customer authentication
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile/password', [AuthController::class, 'changePassword']);
});

// Tenant authentication
Route::prefix('tenant')->group(function () {
    Route::post('/register', [TenantAuthController::class, 'register']);
    Route::post('/login', [TenantAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->post(
        '/logout',
        [TenantAuthController::class, 'logout']
    );
});

// Tenant application
Route::prefix('tenant')
    ->middleware(['auth:sanctum', 'role:company'])
    ->group(function () {
        Route::get('/dashboard/summary', [TenantDashboardController::class, 'summary']);

        Route::get('/products', [ProductController::class, 'tenantProducts']);
        Route::post('/products', [ProductController::class, 'storeTenantProduct']);
        Route::put('/products/{id}', [ProductController::class, 'updateTenantProduct']);
        Route::delete('/products/{id}', [ProductController::class, 'destroyTenantProduct']);
        Route::patch('/products/{id}/stock', [ProductController::class, 'updateTenantStock']);

        Route::get('/categories', [CategoryController::class, 'tenantIndex']);

        Route::get('/settings', [TenantSettingsController::class, 'show']);
        Route::put('/settings/business', [TenantSettingsController::class, 'updateBusiness']);
        Route::put('/settings/notifications', [TenantSettingsController::class, 'updateNotifications']);
        Route::put('/settings/password', [TenantSettingsController::class, 'changePassword']);

        Route::get('/notifications', [TenantNotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [TenantNotificationController::class, 'unreadCount']);
        Route::patch('/notifications/read-all', [TenantNotificationController::class, 'markAllAsRead']);
        Route::patch('/notifications/{id}/read', [TenantNotificationController::class, 'markAsRead']);
        Route::delete('/notifications/clear-all', [TenantNotificationController::class, 'clearAll']);
        Route::delete('/notifications/{id}', [TenantNotificationController::class, 'destroy']);
    });

// Product browsing. Product writes remain under /tenant/products.
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{id}', [ProductController::class, 'show']);
});

// Master categories
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);

Route::middleware([
    'auth:sanctum',
    'role:admin,super_admin',
])->group(function () {
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
});

// Orders
Route::prefix('orders')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::middleware('role:user')->group(function () {
            Route::post('/', [OrderController::class, 'store']);
            Route::get('/my', [OrderController::class, 'index']);
            Route::get('/my/{id}', [OrderController::class, 'show']);
            Route::patch('/my/{id}/cancel', [OrderController::class, 'cancel']);
        });

        Route::middleware('role:company')->group(function () {
            Route::get('/company', [OrderController::class, 'index']);
            Route::get('/company/{id}', [OrderController::class, 'show']);
        });

        Route::patch('/{id}/processing', [OrderController::class, 'markAsProcessing']);
        Route::patch('/{id}/completed', [OrderController::class, 'markAsCompleted']);

        Route::middleware('role:admin,super_admin')->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::get('/{id}', [OrderController::class, 'show']);
            Route::patch('/{id}/cancel', [OrderController::class, 'adminCancel']);
            Route::patch('/{id}/expire', [OrderController::class, 'adminExpire']);
        });
    });

// Payments
Route::prefix('payments')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::middleware('role:user')->get('/my', [PaymentController::class, 'index']);
        Route::middleware('role:company')->get('/company', [PaymentController::class, 'index']);
        Route::middleware('role:admin,super_admin')->get('/', [PaymentController::class, 'index']);

        Route::post('/paystack/pay', [PaymentController::class, 'initialize']);
        Route::get('/{payment}', [PaymentController::class, 'show']);

        Route::middleware('role:admin,super_admin')->group(function () {
            Route::post('/manual', [PaymentController::class, 'store']);
            Route::patch('/{payment}', [PaymentController::class, 'update']);
            Route::delete('/{payment}', [PaymentController::class, 'destroy']);
        });
    });

// Paystack
Route::match(
    ['get', 'post'],
    '/payments/paystack/callback',
    [PaymentController::class, 'callback']
)->name('payments.paystack.callback');

Route::post(
    '/payments/paystack/webhook',
    [PaymentController::class, 'webhook']
)->name('payments.paystack.webhook');

// Reports
Route::prefix('reports')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::middleware('role:user')->get('/my-sales', [ReportController::class, 'salesSummary']);
        Route::middleware('role:company')->get('/company-sales', [ReportController::class, 'salesSummary']);

        Route::middleware('role:admin,super_admin')->group(function () {
            Route::get('/orders', [ReportController::class, 'ordersSummary']);
            Route::get('/sales', [ReportController::class, 'salesSummary']);
            Route::get('/payments', [ReportController::class, 'paymentsSummary']);
        });
    });

// Administration
Route::prefix('admin')->group(function () {
    Route::post('/setup', [AdminController::class, 'register'])
        ->middleware('throttle:5,1');

    Route::post('/login', [AdminController::class, 'login']);

    Route::middleware('auth:sanctum')->post(
        '/logout',
        [AdminController::class, 'logout']
    );

    Route::middleware([
        'auth:sanctum',
        'role:admin,super_admin',
    ])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);

        Route::get('/tenants', [AdminTenantController::class, 'index']);
        Route::get('/tenants/{id}', [AdminTenantController::class, 'show'])
            ->whereNumber('id');

        Route::get('/profile', [AdminController::class, 'myProfile']);
        Route::put('/profile', [AdminController::class, 'updateProfile']);

        Route::put(
            '/profile/password',
            [AdminController::class, 'changePassword']
        )->middleware('throttle:5,1');

        Route::get('/profile/{uuid}', [AdminController::class, 'profile']);

        Route::post('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{user_id}', [AdminUserController::class, 'show']);
    });

    Route::middleware([
        'auth:sanctum',
        'role:super_admin',
    ])->group(function () {
        Route::get('/accounts', [AdminAccountController::class, 'index']);

        Route::patch(
            '/accounts/{uuid}',
            [AdminAccountController::class, 'update']
        );

        Route::post('/register', [AdminController::class, 'addnewuser']);

        Route::post(
            '/change_admin_role',
            [AdminAccountController::class, 'changeRole']
        );
    });

     Route::middleware([
        'auth:sanctum',
        'role:admin,super_admin'
    ])->group(function () {

        // your existing admin routes...

        Route::get('/notifications', [AdminNotificationController::class, 'index']);

        Route::get(
            '/notifications/unread-count',
            [AdminNotificationController::class, 'unreadCount']
        );

        Route::patch(
            '/notifications/read-all',
            [AdminNotificationController::class, 'markAllAsRead']
        );

        Route::patch(
            '/notifications/{id}/read',
            [AdminNotificationController::class, 'markAsRead']
        );

        Route::delete(
            '/notifications/clear-all',
            [AdminNotificationController::class, 'clearAll']
        );

        Route::delete(
            '/notifications/{id}',
            [AdminNotificationController::class, 'destroy']
        );
    });
});

// Cart
Route::middleware('auth:sanctum')
    ->prefix('cart')
    ->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/items', [CartController::class, 'add']);
        Route::patch('/items/{id}', [CartController::class, 'update']);
        Route::delete('/items/{id}', [CartController::class, 'remove']);
        Route::delete('/clear', [CartController::class, 'clear']);
        Route::post('/checkout', [CartController::class, 'checkout']);
    });

// Email verification
Route::get(
    '/email/verify/{id}/{hash}',
    [PublicVerifyEmailController::class, '__invoke']
)->name('verification.verify');