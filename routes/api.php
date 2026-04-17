<?php

use App\Http\Controllers\Api\PaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\OrderController;



/*
|--------------------------------------------------------------------------
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


      Route::get('products', [ProductController::class, 'index']);
      Route::get('products/{id}', [ProductController::class, 'show']);
      Route::middleware('auth:sanctum')->group(function () {
      Route::post('products', [ProductController::class, 'store']);
      Route::put('products/{id}', [ProductController::class, 'update']);
      Route::delete('products/{id}', [ProductController::class, 'destroy']);
      Route::post('products/{id}/restore', [ProductController::class, 'restore']);
  });

  
// Orders routes
Route::prefix('orders')->middleware('auth:sanctum')->group(function () {
    // Normal users: can place orders & view only their own
    Route::middleware('role:user')->group(function () {
        Route::post('/', [OrderController::class, 'store']);      // Place order
        Route::get('/my', [OrderController::class, 'index']);    // Only their orders
        Route::get('/{id}', [OrderController::class, 'show']);   // View single order (owned)
    });
    // Company: can view only company orders
    Route::middleware('role:company')->group(function () {
        Route::get('/company', [OrderController::class, 'index']); // Company orders
    });
    // Admin: can view all orders, update, delete
    Route::middleware('role:admin')->group(function () {
        Route::get('/', [OrderController::class, 'index']);            // All orders
        Route::patch('/{id}/status', [OrderController::class, 'update']);  // Update order status
        Route::delete('/{id}', [OrderController::class, 'destroy']);       // Delete order
    });
});



// Payments routes
Route::prefix('payments')->middleware('auth:sanctum')->group(function () {
    // Normal users: only their payments
    Route::middleware('role:user')->get('/my', [PaymentController::class, 'index']);
    // Company: payments related to their company orders
    Route::middleware('role:company')->get('/company', [PaymentController::class, 'index']);
    // Admin: all payments
    Route::middleware('role:admin')->get('/', [PaymentController::class, 'index']);
    // Common authenticated actions
    Route::post('/', [PaymentController::class, 'store']);        // Create manual payment
    Route::get('/{payment}', [PaymentController::class, 'show']); // View single payment
    Route::patch('/{payment}', [PaymentController::class, 'update']); // Update payment
    Route::delete('/{payment}', [PaymentController::class, 'destroy']); // Delete payment
    // Initialize Paystack payment (authenticated)
    Route::post('/paystack/pay', [PaymentController::class, 'initialize']);
});
// Public callback from Paystack (no auth)
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

