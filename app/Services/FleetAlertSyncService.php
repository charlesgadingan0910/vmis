<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\FleetAlert;
use App\Models\Vehicle;

/**
 * Keeps the fleet_alerts table (the Notification Center's backing store) in
 * sync with the alert conditions Vehicle/Driver already know how to detect:
 * Vehicle::needsRegistrationAlert(), needsPmsAlert(), needsUnserviceableAlert(),
 * and a driver's license_expiration_date. Deliberately re-derives from those
 * same model helpers rather than any separate rule set, so the bell/inbox
 * can never disagree with the Dashboard panels or Vehicle Inventory badges
 * that already surface the same conditions.
 *
 * sync() is idempotent and safe to call often — see the dedupe_key docblock
 * on the fleet_alerts migration. Callers should still rate-limit it (a short
 * cache lock, e.g. in a View composer or controller) rather than run it on
 * every single request; recomputing every vehicle/driver on every page load
 * is wasted work once a handful of users are active.
 */
class FleetAlertSyncService
{
    public function sync(): void
    {
        $this->syncVehicleAlerts();
        $this->syncLicenseExpiryAlerts();
    }

    protected function syncVehicleAlerts(): void
    {
        $vehicles = Vehicle::excludingDisposed()->with('latestRegistration')->get();
        $seenKeys = [];

        foreach ($vehicles as $vehicle) {
            if ($vehicle->needsRegistrationAlert()) {
                $seenKeys[] = $this->upsertVehicleAlert(
                    $vehicle,
                    FleetAlert::TYPE_REGISTRATION_DUE,
                    $vehicle->registrationDaysRemaining(),
                    'Registration',
                );
            }

            if ($vehicle->needsPmsAlert()) {
                $seenKeys[] = $this->upsertVehicleAlert(
                    $vehicle,
                    FleetAlert::TYPE_PMS_DUE,
                    $vehicle->pmsDaysRemaining(),
                    'PMS',
                );
            }

            if ($vehicle->needsUnserviceableAlert()) {
                $days = $vehicle->daysUnserviceable();
                $key = FleetAlert::TYPE_UNSERVICEABLE . ':vehicle:' . $vehicle->id;
                $seenKeys[] = $key;

                FleetAlert::updateOrCreate(
                    ['dedupe_key' => $key],
                    [
                        'type'       => FleetAlert::TYPE_UNSERVICEABLE,
                        'severity'   => FleetAlert::SEVERITY_OVERDUE,
                        'vehicle_id' => $vehicle->id,
                        'driver_id'  => null,
                        'title'      => 'Unserviceable ' . $days . '+ days — ' . strtoupper($vehicle->plate_number),
                        'body'       => strtoupper($vehicle->plate_number) . ' (' . $vehicle->make . ' ' . $vehicle->model . ') has been Unserviceable for ' . $days . ' days and needs attention.',
                    ]
                );
            }
        }

        FleetAlert::whereIn('type', [
            FleetAlert::TYPE_REGISTRATION_DUE,
            FleetAlert::TYPE_PMS_DUE,
            FleetAlert::TYPE_UNSERVICEABLE,
        ])->whereNotIn('dedupe_key', $seenKeys ?: [''])->delete();
    }

    protected function upsertVehicleAlert(Vehicle $vehicle, string $type, ?int $days, string $label): string
    {
        $key = $type . ':vehicle:' . $vehicle->id;
        $overdue = $days !== null && $days < 0;

        FleetAlert::updateOrCreate(
            ['dedupe_key' => $key],
            [
                'type'       => $type,
                'severity'   => $overdue ? FleetAlert::SEVERITY_OVERDUE : FleetAlert::SEVERITY_DUE_SOON,
                'vehicle_id' => $vehicle->id,
                'driver_id'  => null,
                'title'      => $label . ' ' . ($overdue ? 'overdue' : 'due soon') . ' — ' . strtoupper($vehicle->plate_number),
                'body'       => $overdue
                    ? $label . ' for ' . strtoupper($vehicle->plate_number) . ' is ' . abs($days) . ' day(s) overdue.'
                    : $label . ' for ' . strtoupper($vehicle->plate_number) . ' is due in ' . $days . ' day(s).',
            ]
        );

        return $key;
    }

    protected function syncLicenseExpiryAlerts(): void
    {
        $drivers = Driver::whereNotNull('license_expiration_date')
            ->where('license_expiration_date', '<=', now()->addDays(30)->endOfDay())
            ->get();

        $seenKeys = [];

        foreach ($drivers as $driver) {
            $days = (int) round((
                $driver->license_expiration_date->copy()->startOfDay()->getTimestamp()
                - now()->startOfDay()->getTimestamp()
            ) / 86400);
            $overdue = $days < 0;
            $key = FleetAlert::TYPE_LICENSE_EXPIRY . ':driver:' . $driver->id;
            $seenKeys[] = $key;

            FleetAlert::updateOrCreate(
                ['dedupe_key' => $key],
                [
                    'type'       => FleetAlert::TYPE_LICENSE_EXPIRY,
                    'severity'   => $overdue ? FleetAlert::SEVERITY_OVERDUE : FleetAlert::SEVERITY_DUE_SOON,
                    'vehicle_id' => null,
                    'driver_id'  => $driver->id,
                    'title'      => $overdue ? "Driver's license expired" : "Driver's license expiring soon",
                    'body'       => $overdue
                        ? 'Your license expired ' . abs($days) . ' day(s) ago.'
                        : 'Your license expires in ' . $days . ' day(s).',
                ]
            );
        }

        FleetAlert::where('type', FleetAlert::TYPE_LICENSE_EXPIRY)
            ->whereNotIn('dedupe_key', $seenKeys ?: [''])
            ->delete();
    }
}
