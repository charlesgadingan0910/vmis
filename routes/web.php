<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\DriverController;       
use App\Http\Controllers\VehicleTypeController; 
use App\Http\Controllers\ScanController;
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

        // Placeholder pages for planned modules — swap Route::view for a real
        // controller + view once each module is built. Keeps the nav links live.
        Route::view('/maintenance', 'coming-soon', ['title' => 'Maintenance & PMS'])->name('maintenance.index');
        Route::view('/driver-assignments', 'coming-soon', ['title' => 'Driver Assignment'])->name('driver-assignments.index');
    });
});
