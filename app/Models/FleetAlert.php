<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Backing model for the in-app Notification Center. Named FleetAlert (not
 * Notification) to stay clear of Illuminate\Notifications\Notifiable, which
 * User already uses — see the fleet_alerts migration's docblock for why
 * that collision matters.
 */
class FleetAlert extends Model
{
    protected $table = 'fleet_alerts';

    public const TYPE_REGISTRATION_DUE = 'registration_due';
    public const TYPE_PMS_DUE          = 'pms_due';
    public const TYPE_UNSERVICEABLE    = 'unserviceable_alert';
    public const TYPE_LICENSE_EXPIRY   = 'license_expiry';

    public const TYPE_LABELS = [
        self::TYPE_REGISTRATION_DUE => 'Registration',
        self::TYPE_PMS_DUE          => 'PMS',
        self::TYPE_UNSERVICEABLE    => 'Unserviceable',
        self::TYPE_LICENSE_EXPIRY   => "Driver's License",
    ];

    public const TYPE_ICONS = [
        self::TYPE_REGISTRATION_DUE => 'fa-file-contract',
        self::TYPE_PMS_DUE          => 'fa-tools',
        self::TYPE_UNSERVICEABLE    => 'fa-car-crash',
        self::TYPE_LICENSE_EXPIRY   => 'fa-id-card',
    ];

    public const SEVERITY_OVERDUE  = 'overdue';
    public const SEVERITY_DUE_SOON = 'due_soon';

    protected $fillable = [
        'type', 'severity', 'title', 'body',
        'vehicle_id', 'driver_id', 'dedupe_key', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Same role/unit/station visibility rules used everywhere else in the
     * app (DashboardController, VehicleController, etc.), applied here so
     * the bell/inbox never shows a user a record they couldn't already open
     * elsewhere:
     *
     * - SUPER ADMINISTRATOR / ADMINISTRATOR / VIEWER (broad visibility):
     *   every vehicle-scoped alert, org-wide.
     * - UNIT ADMINISTRATOR / STATION ADMINISTRATOR: only vehicle-scoped
     *   alerts for vehicles in their own unit/station.
     * - DRIVER: only alerts for their own assigned vehicle, plus their own
     *   license_expiry alert (via $user->driver_id).
     *
     * A driver-scoped (license_expiry) row is additionally visible to
     * whichever User has driver_id matching it, regardless of role — covers
     * the rare case of an admin account that's also linked to a driver
     * profile.
     */
    public function scopeVisibleTo($query, User $user)
    {
        $role = strtoupper(trim((string) $user->account_type));
        $broadVisibility = in_array($role, ['SUPER ADMINISTRATOR', 'ADMINISTRATOR', 'VIEWER'], true);

        return $query->where(function ($outer) use ($user, $role, $broadVisibility) {
            $outer->where(function ($vehicleAlerts) use ($user, $role, $broadVisibility) {
                $vehicleAlerts->whereNotNull('vehicle_id');

                if ($role === 'DRIVER') {
                    $vehicleAlerts->whereHas('vehicle', function ($v) use ($user) {
                        $v->where('assigned_driver_id', $user->driver_id);
                    });
                } elseif (! $broadVisibility) {
                    $vehicleAlerts->whereHas('vehicle', function ($v) use ($user, $role) {
                        if ($role === 'UNIT ADMINISTRATOR') {
                            $v->where('unit_id', $user->unit_id);
                        } elseif ($role === 'STATION ADMINISTRATOR') {
                            $v->where('station_id', $user->station_id);
                        }
                    });
                }
                // Broad-visibility roles: no further constraint — every
                // vehicle-scoped alert is visible.
            });

            if ($user->driver_id) {
                $outer->orWhere(function ($driverAlerts) use ($user) {
                    $driverAlerts->whereNotNull('driver_id')->where('driver_id', $user->driver_id);
                });
            }
        });
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
