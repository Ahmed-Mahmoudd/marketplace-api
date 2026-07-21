<?php

use App\Http\Controllers\Api\Admin\CategoryAdminController;
use App\Http\Controllers\Api\Admin\VendorAdminController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\Vendor\ProductController;
use App\Http\Controllers\Api\VendorController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{slug}', [CategoryController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::post('/vendor/apply', [VendorController::class, 'apply'])->middleware('idempotency');
    Route::get('/vendor/me', [VendorController::class, 'show']);

    Route::prefix('vendor')->middleware('role:vendor')->group(function () {
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::get('/products/{product}', [ProductController::class, 'show']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    });

    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/vendors', [VendorAdminController::class, 'index']);
        Route::post('/vendors/{vendor}/approve', [VendorAdminController::class, 'approve']);
        Route::post('/vendors/{vendor}/suspend', [VendorAdminController::class, 'suspend']);
        Route::post('/vendors/{vendor}/reject', [VendorAdminController::class, 'reject']);

        Route::post('/categories', [CategoryAdminController::class, 'store']);
        Route::put('/categories/{category}', [CategoryAdminController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryAdminController::class, 'destroy']);
    });
});
