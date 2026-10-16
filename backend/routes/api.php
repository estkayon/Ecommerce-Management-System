
<?php

use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\ProductController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\InventoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\PaymentCallbackController;
use App\Http\Controllers\Api\PaymentInitiationController;


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

Route::prefix('payments/sslcommerz')
    ->name('payments.sslcommerz.')
    ->group(function () {
        Route::post('/success', [PaymentCallbackController::class, 'success'])
            ->name('success');

        Route::post('/fail', [PaymentCallbackController::class, 'fail'])
            ->name('fail');

        Route::post('/cancel', [PaymentCallbackController::class, 'cancel'])
            ->name('cancel');

        Route::post('/ipn', [PaymentCallbackController::class, 'ipn'])
            ->name('ipn');
    });

Route::post(
    '/orders/{order}/payments/initiate',
    [PaymentInitiationController::class, 'store']
)->middleware('throttle:5,1');