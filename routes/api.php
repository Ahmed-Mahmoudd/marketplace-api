<?php

use App\Http\Controllers\Api\Admin\CategoryAdminController;
use App\Http\Controllers\Api\Admin\VendorAdminController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\Vendor\ProductController as VendorProductController;
use App\Http\Controllers\Api\Vendor\ProductImageController;
use App\Http\Controllers\Api\VendorController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{slug}', [CategoryController::class, 'show']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slug}', [ProductController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::post('/vendor/apply', [VendorController::class, 'apply'])->middleware('idempotency');
    Route::get('/vendor/me', [VendorController::class, 'show']);

    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'storeItem']);
    Route::put('/cart/items/{item}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{item}', [CartController::class, 'destroyItem']);
    Route::delete('/cart', [CartController::class, 'clear']);

    Route::prefix('vendor')->middleware('role:vendor')->group(function () {
        Route::get('/products', [VendorProductController::class, 'index']);
        Route::post('/products', [VendorProductController::class, 'store']);
        Route::get('/products/{product}', [VendorProductController::class, 'show']);
        Route::put('/products/{product}', [VendorProductController::class, 'update']);
        Route::delete('/products/{product}', [VendorProductController::class, 'destroy']);

        Route::post('/products/{product}/images', [ProductImageController::class, 'store']);
        Route::patch('/products/{product}/images/{image}/primary', [ProductImageController::class, 'setPrimary']);
        Route::delete('/products/{product}/images/{image}', [ProductImageController::class, 'destroy']);
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
