
<?php

use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\ProductController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\InventoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\Admin\OrderController;


// Public login endpoint.
Route::post('/admin/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

// All admin routes require authentication and admin authorization.
Route::prefix('admin')
    ->middleware(['auth:sanctum', 'admin'])
    ->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::apiResource('products', ProductController::class);

        Route::get('products/{product}/inventory', [InventoryController::class, 'show']);
        Route::post('products/{product}/inventory/adjust', [InventoryController::class, 'adjust']);
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
    });

Route::post('/checkout', [CheckoutController::class, 'store'])
    ->middleware('throttle:10,1');