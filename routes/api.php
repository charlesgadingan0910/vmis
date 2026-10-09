<?php

use App\Http\Controllers\Api\AccidentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FuelLogController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\ServiceHistoryController;
use App\Http\Controllers\Api\TripLogController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Driver Mobile App API
|--------------------------------------------------------------------------
|
| Everything under here is consumed only by the VMIS Driver mobile app —
| see App\Http\Middleware\AuthenticateApiToken, which additionally rejects
| any account that isn't DRIVER-type. Not a general-purpose API for other
| VMIS account types or the desktop site.
*/

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/change-password', [PasswordController::class, 'update']);

    Route::middleware('api.auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/vehicle', [VehicleController::class, 'show']);
        Route::get('/vehicles/scan/{code}', [VehicleController::class, 'scan']);
        Route::get('/vehicles/scan-plate/{plate}', [VehicleController::class, 'scanByPlate']);

        Route::get('/trips', [TripLogController::class, 'index']);
        Route::post('/trips', [TripLogController::class, 'store']);

        Route::get('/fuel-logs', [FuelLogController::class, 'index']);
        Route::post('/fuel-logs', [FuelLogController::class, 'store']);

        Route::get('/accidents', [AccidentController::class, 'index']);
        Route::post('/accidents', [AccidentController::class, 'store']);

        // VIEWER-only — see ServiceHistoryController's own docblock for why
        // DRIVER is rejected here rather than just not calling it.
        Route::get('/service-history', [ServiceHistoryController::class, 'index']);
    });
});
