<?php

use App\Http\Controllers\Api\PaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VegetableController;
use App\Http\Controllers\VegetableRequestController;
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


      Route::get('vegetables', [VegetableController::class, 'index']);
      Route::get('vegetables/{id}', [VegetableController::class, 'show']);
      Route::middleware('auth:sanctum')->group(function () {
      Route::post('vegetables', [VegetableController::class, 'store']);
      Route::put('vegetables/{id}', [VegetableController::class, 'update']);
      Route::delete('vegetables/{id}', [VegetableController::class, 'destroy']);
      Route::post('vegetables/{id}/restore', [VegetableController::class, 'restore']);
      // Requests
      Route::post('vegetables/{id}/request', [VegetableRequestController::class, 'store']);
      Route::post('vegetables/{id}/fulfill', [VegetableRequestController::class, 'fulfill']);
      Route::get('vegetables/{id}/request-status', [VegetableRequestController::class, 'status']);

  });

  Route::prefix('orders')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [OrderController::class, 'index']);          // List all orders
    Route::post('/', [OrderController::class, 'store']);         // Create new order
    Route::get('/{id}', [OrderController::class, 'show']);       // Show single order
    Route::patch('/{id}/status', [OrderController::class, 'update'])->middleware('can:admin'); // Update order status
    Route::delete('/{id}', [OrderController::class, 'destroy'])->middleware('can:admin'); // Delete order
});


// Assuming you have routes for orders already
Route::middleware('auth:sanctum')->prefix('payments')->group(function () {
    Route::get('/', [PaymentController::class, 'index']); // List user payments
    Route::post('/order/{order}', [PaymentController::class, 'store']); // Create payment for order
    Route::get('/order/{order}', [PaymentController::class, 'show']); // Show payment for order
    Route::patch('/{payment}/status', [PaymentController::class, 'updateStatus']); // Update payment status
    
    // Paystack integration
    Route::post('/pay', [PaymentController::class, 'redirectToGateway'])->name('pay');
});
 Route::get('/callback', [PaymentController::class, 'handleGatewayCallback'])->name('payment.callback');