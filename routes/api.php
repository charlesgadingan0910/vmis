<?php

use App\Http\Controllers\Api\AccidentController;
use App\Http\Controllers\Api\ActiveTripController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FuelLogController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\ServiceHistoryController;
use App\Http\Controllers\Api\TripLogController;
use App\Http\Controllers\Api\TripRegistrationController;
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

        // Live Monitoring pings — a *separate*, transient channel from the
        // /trips endpoints above. The app calls these every ~15s while GPS
        // tracking is active (see TripTrackingService) so the admin Live
        // Monitoring map can show the vehicle moving in near-real-time; the
        // permanent trip record still only ever gets written once via the
        // normal POST /trips call when the driver taps End Trip. See
        // Api\ActiveTripController's own docblock.
        Route::post('/active-trips/ping', [ActiveTripController::class, 'ping']);
        Route::post('/active-trips/end', [ActiveTripController::class, 'end']);

        // Trip Registrations — the driver-facing half of Register → Assign
        // Personnel → Approve. Only ever populated for a scheduled or
        // assistant-crewed trip (see TripRegistration's own docblock); a
        // routine solo trip never shows up here at all.
        Route::get('/trip-registrations', [TripRegistrationController::class, 'index']);

        // Self-service Emergency Trip — declares and activates its own
        // TripRegistration in one call, no pre-existing registration or
        // admin approval needed (see TripRegistrationController::
        // startEmergency() for why). Registered BEFORE the {tripRegistration}
        // /start route below: both are POST /trip-registrations/*/start, and
        // Laravel matches in registration order, so this literal path has to
        // come first or "emergency" would be parsed as a {tripRegistration} id.
        Route::post('/trip-registrations/emergency/start', [TripRegistrationController::class, 'startEmergency']);
        Route::post('/trip-registrations/{tripRegistration}/start', [TripRegistrationController::class, 'start']);

        Route::get('/fuel-logs', [FuelLogController::class, 'index']);
        Route::post('/fuel-logs', [FuelLogController::class, 'store']);

        Route::get('/accidents', [AccidentController::class, 'index']);
        Route::post('/accidents', [AccidentController::class, 'store']);

        // VIEWER-only — see ServiceHistoryController's own docblock for why
        // DRIVER is rejected here rather than just not calling it.
        Route::get('/service-history', [ServiceHistoryController::class, 'index']);

        // Notification Center — open to both DRIVER and VIEWER (unlike
        // service-history above), see Api\NotificationController's docblock.
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{alert}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    });
});
