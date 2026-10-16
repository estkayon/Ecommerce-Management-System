
<?php

use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\ProductController;
use Illuminate\Support\Facades\Route;

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
    });
