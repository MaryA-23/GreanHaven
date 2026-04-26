<?php

use App\Http\Controllers\Api\PaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\Api\Admin\DashboardController;
use Illuminate\Support\Facades\Mail;



/*
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

  // Public routes - no authentication required
  Route::post('/register', [AuthController::class, 'register']);
  Route::post('/login', [AuthController::class, 'login']);
  // Protected routes - require valid Sanctum token
  Route::middleware('auth:sanctum')->group(function () {
  Route::post('/logout', [AuthController::class, 'logout']);
  Route::get('/user', [AuthController::class, 'user']);
  // Example: farming-related routes
  // Route::apiResource('farms', FarmController::class);
});

Route::prefix('products')->group(function () {

    // Public/customer browsing
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{id}', [ProductController::class, 'show']);

    // Admin product management
    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::post('/', [ProductController::class, 'store']);
        Route::patch('/{id}', [ProductController::class, 'update']);
        Route::delete('/{id}', [ProductController::class, 'destroy']);
        Route::patch('/{id}/restore', [ProductController::class, 'restore']);
    });
});

        Route::get('categories', [CategoryController::class, 'index']);
        Route::get('categories/{id}', [CategoryController::class, 'show']);
        Route::middleware('auth:sanctum')->group(function () {
        Route::post('categories', [CategoryController::class, 'store']);
        Route::put('categories/{id}', [CategoryController::class, 'update']);
        Route::delete('categories/{id}', [CategoryController::class, 'destroy']);
    });

  
// Orders routes
Route::prefix('orders')->middleware('auth:sanctum')->group(function () {

    Route::middleware('role:user')->group(function () {
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/my', [OrderController::class, 'index']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::patch('/{id}/cancel', [OrderController::class, 'cancel']);
    });

    Route::middleware('role:company')->group(function () {
        Route::get('/company', [OrderController::class, 'index']);
        Route::get('/company/{id}', [OrderController::class, 'show']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::patch('/{id}/processing', [OrderController::class, 'markAsProcessing']);
        Route::patch('/{id}/completed', [OrderController::class, 'markAsCompleted']);
        Route::patch('/{id}/cancel', [OrderController::class, 'adminCancel']);
        Route::patch('/{id}/expire', [OrderController::class, 'adminExpire']);
    });
});



Route::prefix('payments')->middleware('auth:sanctum')->group(function () {
    // Normal users: only their payments
    Route::middleware('role:user')->get('/my', [PaymentController::class, 'index']);
    // Company: payments related to their company orders
    Route::middleware('role:company')->get('/company', [PaymentController::class, 'index']);
    // Admin: all payments
    Route::middleware('role:admin')->get('/', [PaymentController::class, 'index']);
    // Manual payment routes - I recommend admin only
    Route::middleware('role:admin')->group(function () {
        Route::post('/manual', [PaymentController::class, 'store']);
        Route::patch('/{payment}', [PaymentController::class, 'update']);
        Route::delete('/{payment}', [PaymentController::class, 'destroy']);
    });
    // Common authenticated view
    Route::get('/{payment}', [PaymentController::class, 'show']);
    // Initialize Paystack payment
    Route::post('/paystack/pay', [PaymentController::class, 'initialize']);
});
// Public callback from Paystack - no auth
Route::match(['get', 'post'], '/payments/paystack/callback', [PaymentController::class, 'callback']);



// Reports routes
Route::prefix('reports')->middleware('auth:sanctum')->group(function () {
    // Normal users: only their sales summary
    Route::middleware('role:user')->get('/my-sales', [ReportController::class, 'salesSummary']);
    // Company: sales summary for company orders
    Route::middleware('role:company')->get('/company-sales', [ReportController::class, 'salesSummary']);
    // Admin: all reports
    Route::middleware('role:admin')->group(function () {
        Route::get('/orders', [ReportController::class, 'ordersSummary']);
        Route::get('/sales', [ReportController::class, 'salesSummary']);
        Route::get('/payments', [ReportController::class, 'paymentsSummary']);
    });
});

Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/products', [ProductController::class, 'store']);
    Route::post('/categories', [CategoryController::class, 'store']);
});

Route::get('/test-mail', function () {
    Mail::raw('Greenhaven Gmail SMTP is working.', function ($message) {
        $message->to('yourpersonalemail@gmail.com')
            ->subject('Greenhaven Test Mail');
    });

    return response()->json([
        'message' => 'Test mail sent'
    ]);
});