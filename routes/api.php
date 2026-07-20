<?php

use App\Http\Controllers\Api\Admin\VendorAdminController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\VendorController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Route::post('/test-idempotency', function () {
//     return response()->json([
//         'message' => 'Controller executed'
//     ]);
// })->middleware('idempotency');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::post('/vendor/apply', [VendorController::class, 'apply'])->middleware('idempotency');
    Route::get('/vendor/me', [VendorController::class, 'show']);

    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/vendors', [VendorAdminController::class, 'index']);
        Route::post('/vendors/{vendor}/approve', [VendorAdminController::class, 'approve']);
        Route::post('/vendors/{vendor}/suspend', [VendorAdminController::class, 'suspend']);
        Route::post('/vendors/{vendor}/reject', [VendorAdminController::class, 'reject']);
    });
});
