<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\VehicleTypeController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\RepairController;
use App\Http\Controllers\TechnicalInspectionController;
use App\Http\Controllers\VehicleRepairRequisitionController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DocumentIntelligenceController;
use App\Http\Controllers\AccountTypeController;
use App\Http\Controllers\TripLogController;
use App\Http\Controllers\TripRegistrationController;
use App\Http\Controllers\FuelLogController;
use App\Http\Controllers\VehicleAccidentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\LiveMonitoringController;
use App\Http\Controllers\DriverAssignmentController;
use App\Http\Controllers\GeofenceController;
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

        // In-app Notification Center — the header bell on every page. Polled
        // via AJAX by layout.nav rather than server-rendered on every page
        // load, so one controller/route pair backs every page's bell instead
        // of threading alert data through every single page controller.
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::post('/{alert}/read', [NotificationController::class, 'markRead'])->name('read');
            Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
        });

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

        // Combined Maintenance & PMS + Repair history for one vehicle — every job ever
        // logged, regardless of stage/type, so it doesn't need its own module; it just
        // reads straight from the same maintenance_records table those two already use.
        Route::get('/vehicles/{vehicle}/service-history', [VehicleController::class, 'serviceHistory'])->name('vehicles.service-history');

        // The other three tabs of the "Vehicle Records" modal on Vehicle Inventory —
        // same reasoning as service-history above, just pointed at Trip Logs, Fuel
        // Monitoring and Accident Records, so every vehicle-related record is visible
        // from one place without leaving the Vehicle Inventory page.
        Route::get('/vehicles/{vehicle}/trip-logs-history', [VehicleController::class, 'tripLogsHistory'])->name('vehicles.trip-logs-history');
        Route::get('/vehicles/{vehicle}/fuel-logs-history', [VehicleController::class, 'fuelLogsHistory'])->name('vehicles.fuel-logs-history');
        Route::get('/vehicles/{vehicle}/accidents-history', [VehicleController::class, 'accidentsHistory'])->name('vehicles.accidents-history');

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
        // entirely inside TripLogController — see its class docblock. No edit —
        // logged trips are otherwise permanent, like Activity Logs — but a
        // SUPER ADMINISTRATOR alone may delete one (TripLogController::destroy()).
        Route::resource('trip-logs', TripLogController::class)->only(['index', 'store', 'destroy']);

        // Live Monitoring — a real-time map of every vehicle currently
        // mid-trip, fed by the driver app's periodic GPS pings (see
        // Api\ActiveTripController). Read-only, same unit/station
        // visibility scoping as Trip Logs; gated inside
        // LiveMonitoringController (DRIVER accounts 403 — they only ever
        // need their own trip, already live in the app while tracking).
        Route::prefix('live-monitoring')->name('live-monitoring.')->group(function () {
            Route::get('/', [LiveMonitoringController::class, 'index'])->name('index');
            Route::get('/active-trips', [LiveMonitoringController::class, 'activeTrips'])->name('active-trips');
        });

        // Geofence zones drawn on the Live Monitoring map — see
        // GeofenceController's class docblock. JSON API only, no view of
        // its own; the "Geofences" panel on live-monitoring.index calls
        // these directly.
        Route::prefix('geofences')->name('geofences.')->group(function () {
            Route::get('/', [GeofenceController::class, 'index'])->name('index');
            Route::post('/', [GeofenceController::class, 'store'])->name('store');
            Route::put('/{geofence}', [GeofenceController::class, 'update'])->name('update');
            Route::delete('/{geofence}', [GeofenceController::class, 'destroy'])->name('destroy');
        });

        // Trip Registrations — Register → Assign Personnel → Approve, ahead
        // of a trip. Only ever used for a scheduled trip or one with an
        // assistant assigned; a routine solo trip skips this entirely and
        // starts instantly from the app, unchanged. Gated inside
        // TripRegistrationController (DRIVER accounts 403 — they use the
        // mobile app instead; VIEWER can see the page shell but every
        // mutating action still re-checks role server-side).
        Route::prefix('trip-registrations')->name('trip-registrations.')->group(function () {
            Route::get('/', [TripRegistrationController::class, 'index'])->name('index');
            Route::post('/', [TripRegistrationController::class, 'store'])->name('store');
            Route::post('/{tripRegistration}/approve', [TripRegistrationController::class, 'approve'])->name('approve');
            Route::post('/{tripRegistration}/reject', [TripRegistrationController::class, 'reject'])->name('reject');
            Route::post('/{tripRegistration}/cancel', [TripRegistrationController::class, 'cancel'])->name('cancel');
            // Permanent delete — SUPER ADMINISTRATOR only, any status (see
            // TripRegistrationController::destroy()). Explicit route, not
            // Route::resource, matching the rest of this group.
            Route::delete('/{tripRegistration}', [TripRegistrationController::class, 'destroy'])->name('destroy');
        });

        // Fuel Monitoring — same permission shape as Trip Logs (a DRIVER logs
        // refuels for their own assigned vehicle via the mobile view, a SUPER
        // ADMINISTRATOR may log for any vehicle, everyone else is read-only,
        // scoped by unit/station). See FuelLogController's class docblock for
        // why this is its own module rather than part of Trip Logs. No edit —
        // logged refuels are otherwise permanent, like Trip Logs — but a
        // SUPER ADMINISTRATOR alone may delete one (FuelLogController::destroy()).
        Route::resource('fuel-logs', FuelLogController::class)->only(['index', 'store', 'destroy']);

        // Serves uploaded refuel receipts directly (bypasses the public/storage
        // symlink, unreliable on Windows/WAMP) and keeps document access behind login.
        Route::get('/fuel-documents/{path}', [FuelLogController::class, 'viewDocument'])
            ->where('path', '.*')
            ->name('fuel-logs.document');

        // In-app camera scanner + what a scanned sticker actually resolves to.
        Route::get('/scan', [ScanController::class, 'index'])->name('scan.index');
        Route::get('/vehicles/scan/{qrCode}', [ScanController::class, 'show'])->name('vehicles.scan');

        // Manual-entry fallback (searches by plate number — the only identifier
        // actually printed and visible on the physical sticker).
        Route::get('/vehicles/scan-plate/{plate}', [ScanController::class, 'showByPlate'])->name('vehicles.scan-plate');

        // Driver Management — as of the Driver Assignments merge, this is
        // also where vehicle assignment is managed (see DriverController's
        // class docblock); there is no separate assignment page anymore.
        Route::resource('drivers', DriverController::class)->except(['show']);

        // Live license-number duplicate check + profile photo streaming
        Route::post('/drivers/check-availability', [DriverController::class, 'checkAvailability'])->name('drivers.check-availability');
        Route::get('/drivers/{driver}/photo', [DriverController::class, 'photo'])->name('drivers.photo');

        // Vehicle Assignment, driver-centric — "assign this driver a
        // vehicle" rather than "assign this vehicle a driver" — backing
        // the Assign/Reassign/Unassign/History actions folded into the
        // Driver Management table above. See DriverAssignmentController's
        // class docblock for how this relates to its older vehicle-centric
        // methods (assign()/unassign()/history() further below).
        Route::post('/drivers/{driver}/assign-vehicle', [DriverAssignmentController::class, 'assignToDriver'])->name('drivers.assign-vehicle');
        Route::post('/drivers/{driver}/unassign-vehicle', [DriverAssignmentController::class, 'unassignDriver'])->name('drivers.unassign-vehicle');
        Route::get('/drivers/{driver}/assignment-history', [DriverAssignmentController::class, 'driverHistory'])->name('drivers.assignment-history');

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

        // Process-flow redesign: marks a Requested/Inspected/Awaiting Parts record
        // Completed — the final step, gated by MaintenanceRecord::canComplete().
        // Separate from update() because it validates/accepts a different set of
        // fields (the completion-only ones: service_date, cost, performed_by, etc.).
        Route::post('/maintenance/{maintenance}/complete', [MaintenanceController::class, 'completeService'])->name('maintenance.complete');

        // Serves uploaded maintenance receipts/invoices directly (bypasses the public/storage
        // symlink, which is unreliable on Windows/WAMP) and keeps document access behind login.
        Route::get('/maintenance-documents/{path}', [MaintenanceController::class, 'viewDocument'])
            ->where('path', '.*')
            ->name('maintenance.document');

        // Repairs — split out from Maintenance & PMS (VMIS Additional Updates item 4).
        // Same maintenance_records table, partitioned by type; see RepairController's
        // class docblock. Same only(...)/edit-data/update convention as maintenance above.
        Route::resource('repairs', RepairController::class)->only(['index', 'store', 'destroy']);
        Route::get('/repairs/{repair}/edit-data', [RepairController::class, 'editData'])->name('repairs.edit-data');
        Route::put('/repairs/{repair}', [RepairController::class, 'update'])->name('repairs.update');

        // See the matching maintenance.complete route above — same process-flow
        // "Complete Service" action, mirrored for the Repairs module.
        Route::post('/repairs/{repair}/complete', [RepairController::class, 'completeService'])->name('repairs.complete');

        Route::get('/repair-documents/{path}', [RepairController::class, 'viewDocument'])
            ->where('path', '.*')
            ->name('repairs.document');

        // Accident Records — a separate log of collisions/incidents, kept
        // apart from Maintenance/Repairs (see the migration's docblock). Same
        // only(...)/edit-data/update convention as maintenance/repairs above.
        Route::resource('accidents', VehicleAccidentController::class)->only(['index', 'store', 'destroy']);
        Route::get('/accidents/{accident}/edit-data', [VehicleAccidentController::class, 'editData'])->name('accidents.edit-data');
        Route::put('/accidents/{accident}', [VehicleAccidentController::class, 'update'])->name('accidents.update');

        Route::get('/accident-documents/{path}', [VehicleAccidentController::class, 'viewDocument'])
            ->where('path', '.*')
            ->name('accidents.document');

        // Digitized Technical Inspection Report checklist — one shared route pair
        // for both Maintenance & PMS and Repairs records, since a checklist
        // belongs to the underlying maintenance_records row regardless of which
        // module logged it. See TechnicalInspectionController's class docblock.
        Route::get('/technical-inspections/{maintenanceRecord}', [TechnicalInspectionController::class, 'edit'])->name('technical-inspections.edit');
        Route::post('/technical-inspections/{maintenanceRecord}', [TechnicalInspectionController::class, 'save'])->name('technical-inspections.save');

        // Digitized Vehicle Repair Requisition Slip — same shared-controller
        // shape as the Technical Inspection routes above (see
        // VehicleRepairRequisitionController's class docblock).
        Route::get('/vehicle-requisitions/{maintenanceRecord}', [VehicleRepairRequisitionController::class, 'edit'])->name('vehicle-requisitions.edit');
        Route::post('/vehicle-requisitions/{maintenanceRecord}', [VehicleRepairRequisitionController::class, 'save'])->name('vehicle-requisitions.save');

        // Driver Assignments (legacy, vehicle-centric) — this used to be
        // its own page; it's now merged into Driver Management above (see
        // DriverController's and DriverAssignmentController's class
        // docblocks), so the index route just redirects there and nothing
        // currently links to the assign/unassign/history routes below.
        // They're kept rather than removed — same authorization rules,
        // harmless if something calls them directly later — but the
        // Driver Management page's own Assign/Unassign/History buttons
        // call the driver-centric /drivers/{driver}/... routes above instead.
        Route::prefix('driver-assignments')->name('driver-assignments.')->group(function () {
            Route::get('/', fn () => redirect()->route('drivers.index'))->name('index');
            Route::post('/{vehicle}/assign', [DriverAssignmentController::class, 'assign'])->name('assign');
            Route::post('/{vehicle}/unassign', [DriverAssignmentController::class, 'unassign'])->name('unassign');
            Route::get('/{vehicle}/history', [DriverAssignmentController::class, 'history'])->name('history');
        });

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
