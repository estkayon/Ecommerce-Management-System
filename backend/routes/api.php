
<?php

use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\ProductController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\InventoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\PaymentCallbackController;
use App\Http\Controllers\Api\PaymentInitiationController;
use App\Http\Controllers\Api\Admin\PaymentSettingController;
use App\Http\Controllers\Api\Admin\PaymentReviewController;
use App\Http\Controllers\Api\Admin\PaymentResolutionController;
use App\Http\Controllers\Api\Admin\DeliveryController;
use App\Http\Controllers\Api\ProductController as PublicProductController;
use App\Http\Controllers\Api\OrderPaymentStatusController;
use App\Http\Controllers\Api\Admin\PaymentController;
use App\Http\Controllers\Api\Admin\DashboardController;


// Public login endpoint.
Route::post('/admin/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

// All admin routes require authentication and admin authorization.
Route::prefix('admin')
    ->middleware(['auth:sanctum', 'admin'])
    ->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::apiResource('products', ProductController::class);

        Route::get('products/{product}/inventory', [InventoryController::class, 'show']);
        Route::post('products/{product}/inventory/adjust', [InventoryController::class, 'adjust']);
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::get('payments', [PaymentController::class, 'index']);
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);

        
        Route::get(
            'payment-settings',
            [PaymentSettingController::class, 'show']
        );

        Route::patch(
            'payment-settings',
            [PaymentSettingController::class, 'update']
        );

        Route::get(
            'payment-reviews',
            [PaymentReviewController::class, 'index']
        );

        Route::patch(
            'payment-reviews/{payment}',
            [PaymentResolutionController::class, 'update']
        );

        Route::get('/deliveries', [DeliveryController::class, 'index']);
        Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show']);

        Route::post('/deliveries/{delivery}/retry', [DeliveryController::class, 'retry']);

        });

    Route::get('/products', [PublicProductController::class, 'index']);
    Route::get('/products/{product}', [PublicProductController::class, 'show']);

    Route::post(
        '/orders/{order}/payment-status',
        [OrderPaymentStatusController::class, 'show']
    )->middleware('throttle:30,1');

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