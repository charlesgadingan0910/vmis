<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\VehicleTypeController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DocumentIntelligenceController;
use App\Http\Controllers\AccountTypeController;
use App\Http\Controllers\TripLogController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------
// Guest
// ---------------------------------------------------------------------
Route::get('/', [AuthController::class, 'login'])->name('login');
Route::post('/login', [AuthController::class, 'authenticate'])->name('login.authenticate');

// ---------------------------------------------------------------------
// Authenticated
// ---------------------------------------------------------------------
Route::middleware('auth')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Reachable even before the forced password change so a first-time
    // user can actually get to it (and so it isn't a redirect loop).
    Route::get('/change-password', [PasswordController::class, 'edit'])->name('password.change');
    Route::post('/change-password', [PasswordController::class, 'update'])->name('password.update');

    // Everything below this line requires an already-changed password.
    // Add future protected routes inside this group.
    Route::middleware('password.changed')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');

        // My Profile — every account type can reach this (no role gate); it
        // only ever reads/writes Auth::user(), so there's nothing to scope.
        // The activity feed is its own AJAX endpoint, server-side paginated
        // and filtered on the already-indexed activity_logs.user_id column,
        // so it stays fast even once an account has thousands of log entries.
        Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::get('/profile/activity', [ProfileController::class, 'activity'])->name('profile.activity');
        Route::get('/profile/activity/{activityLog}', [ProfileController::class, 'showActivity'])->name('profile.activity.show');

        // AI Document Intelligence — OCR-style extraction from a photographed
        // OR/CR document, a maintenance receipt, or a vehicle's plate. Every
        // endpoint degrades gracefully when no API key is configured (see
        // DocumentIntelligenceController::extract()), so these only ever
        // assist the existing manual-entry forms, never block them.
        Route::post('/ai/extract-vehicle-document', [DocumentIntelligenceController::class, 'extractVehicleDocument'])->name('ai.extract-vehicle-document');
        Route::post('/ai/extract-maintenance-receipt', [DocumentIntelligenceController::class, 'extractMaintenanceReceipt'])->name('ai.extract-maintenance-receipt');
        Route::post('/ai/extract-plate', [DocumentIntelligenceController::class, 'extractPlate'])->name('ai.extract-plate');

        // Vehicle Management — only(...) because create/show/edit (the Blade-view-returning
        // resource actions) aren't implemented; editing is AJAX-driven via edit-data/update below.
        Route::resource('vehicles', VehicleController::class)->only(['index', 'store', 'destroy']);

        // Edit support: fetch raw field values, then submit changes — both AJAX, both open in the same modal.
        Route::get('/vehicles/{vehicle}/edit-data', [VehicleController::class, 'editData'])->name('vehicles.edit-data');
        Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');

        // OR/CR history + yearly re-registration (a vehicle can accumulate one per year)
        Route::get('/vehicles/{vehicle}/history', [VehicleController::class, 'getHistory'])->name('vehicles.history');
        Route::post('/vehicles/{vehicle}/registrations', [VehicleController::class, 'storeRegistration'])->name('vehicles.registrations.store');

        // Serves uploaded OR/CR files directly (bypasses the public/storage symlink, which is
        // unreliable on Windows) and keeps document access behind login like everything else.
        Route::get('/vehicle-documents/{path}', [VehicleController::class, 'viewDocument'])
            ->where('path', '.*')
            ->name('vehicles.document');

        // Real-time AJAX duplicate checker endpoint
        Route::post('/vehicles/check-availability', [VehicleController::class, 'checkAvailability'])->name('vehicles.check-availability');

        // ---------------- QR Code module ----------------
        Route::get('/vehicles/{vehicle}/qr-image', [VehicleController::class, 'qrImage'])->name('vehicles.qr-image');
        Route::get('/vehicles/{vehicle}/qr-data', [VehicleController::class, 'qrData'])->name('vehicles.qr-data');
        Route::get('/vehicles/qr/print', [VehicleController::class, 'printQr'])->name('vehicles.qr.print');

        // Trip Logs — a DRIVER logs trips for their own assigned vehicle (mobile
        // view), a SUPER ADMINISTRATOR may log for any vehicle; every other role
        // is read-only, scoped by unit/station same as Vehicle Inventory. Gated
        // entirely inside TripLogController — see its class docblock. No edit/
        // delete: logged trips are permanent records, like Activity Logs.
        Route::resource('trip-logs', TripLogController::class)->only(['index', 'store']);

        // In-app camera scanner + what a scanned sticker actually resolves to.
        Route::get('/scan', [ScanController::class, 'index'])->name('scan.index');
        Route::get('/vehicles/scan/{qrCode}', [ScanController::class, 'show'])->name('vehicles.scan');

        // Manual-entry fallback (searches by plate number — the only identifier
        // actually printed and visible on the physical sticker).
        Route::get('/vehicles/scan-plate/{plate}', [ScanController::class, 'showByPlate'])->name('vehicles.scan-plate');

        // Driver Management 
        Route::resource('drivers', DriverController::class)->except(['show']);

        // Live license-number duplicate check + profile photo streaming
        Route::post('/drivers/check-availability', [DriverController::class, 'checkAvailability'])->name('drivers.check-availability');
        Route::get('/drivers/{driver}/photo', [DriverController::class, 'photo'])->name('drivers.photo');
        
        // Vehicle Types Management
        Route::resource('vehicle-types', VehicleTypeController::class)->except(['show']);

        // Units & Stations Management — one combined page, gated to SUPER
        // ADMINISTRATOR + ADMINISTRATOR only inside the controllers themselves
        // (UnitController/StationController::authorizeAccess), same pattern
        // as ActivityLogController but for two allowed roles instead of one.
        Route::resource('units', UnitController::class)->except(['show']);
        Route::resource('stations', StationController::class)->except(['show']);

        // Account Types Management — gated to SUPER ADMINISTRATOR ONLY inside
        // the controller itself (AccountTypeController::authorizeAccess), the
        // same single-role pattern ActivityLogController uses.
        Route::resource('account-types', AccountTypeController::class)->except(['show']);

        // Maintenance & PMS — only(...) because create/show/edit (the Blade-view-returning
        // resource actions) aren't implemented; editing is AJAX-driven via edit-data/update
        // below, same convention as vehicles.
        Route::resource('maintenance', MaintenanceController::class)->only(['index', 'store', 'destroy']);
        Route::get('/maintenance/{maintenance}/edit-data', [MaintenanceController::class, 'editData'])->name('maintenance.edit-data');
        Route::put('/maintenance/{maintenance}', [MaintenanceController::class, 'update'])->name('maintenance.update');

        // Serves uploaded maintenance receipts/invoices directly (bypasses the public/storage
        // symlink, which is unreliable on Windows/WAMP) and keeps document access behind login.
        Route::get('/maintenance-documents/{path}', [MaintenanceController::class, 'viewDocument'])
            ->where('path', '.*')
            ->name('maintenance.document');

        // Placeholder pages for planned modules — swap Route::view for a real
        // controller + view once each module is built. Keeps the nav links live.
        Route::view('/driver-assignments', 'coming-soon', ['title' => 'Driver Assignment'])->name('driver-assignments.index');

        // User Management
        Route::resource('users', \App\Http\Controllers\UserController::class)->except(['create', 'show', 'edit']);
        Route::get('/users/{user}/edit-data', [\App\Http\Controllers\UserController::class, 'editData'])->name('users.edit-data');
        Route::post('/users/{user}/reset-password', [\App\Http\Controllers\UserController::class, 'resetPassword'])->name('users.reset-password');

        // System Activity Logs — read-only, and gated to SUPER ADMINISTRATOR
        // only inside the controller itself (ActivityLogController::authorizeAccess),
        // matching how every other role check in this app is done inline
        // rather than through a route middleware/policy layer.
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('/activity-logs/{activityLog}', [ActivityLogController::class, 'show'])->name('activity-logs.show');
    });
});
