<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VegetableController;
use App\Http\Controllers\VegetableRequestController;
use App\Http\Controllers\Api\AuthController;



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
  });