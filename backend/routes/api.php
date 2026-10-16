
<?php

use App\Http\Controllers\Api\Admin\AuthController;
use Illuminate\Support\Facades\Route;

// Public admin authentication endpoint with login rate limiting.
Route::post('/admin/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

// Protected endpoints require a valid token and admin privileges.
Route::prefix('admin')
    ->middleware(['auth:sanctum', 'admin'])
    ->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
