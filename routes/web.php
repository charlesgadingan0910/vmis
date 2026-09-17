<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\DriverController;       
use App\Http\Controllers\VehicleTypeController; 
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

        // Vehicle Management
        Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
        Route::post('/vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
    
        // Real-time AJAX duplicate checker endpoint
        Route::post('/vehicles/check-availability', [VehicleController::class, 'checkAvailability'])->name('vehicles.check-availability');
        
        // Driver Management 
        Route::resource('drivers', DriverController::class)->except(['show']);
        
        // Vehicle Types Management
        Route::resource('vehicle-types', VehicleTypeController::class)->except(['show']);

        // Placeholder pages for planned modules — swap Route::view for a real
        // controller + view once each module is built. Keeps the nav links live.
        Route::view('/maintenance', 'coming-soon', ['title' => 'Maintenance & PMS'])->name('maintenance.index');
        Route::view('/driver-assignments', 'coming-soon', ['title' => 'Driver Assignment'])->name('driver-assignments.index');
    });
});
