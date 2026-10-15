<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    /**
     * How a vehicle came into the fleet — shared by the controller (validation,
     * dropdown options) and the view (filter/select options, badge labels) so
     * both stay in lockstep, same pattern as MaintenanceRecord::TYPES.
     */
    public const SOURCES = [
        'ORGANIC' => 'Organic',
        'LOANED'  => 'Loaned',
        'DONATED' => 'Donated',
    ];

    /**
     * Only meaningful when status = BER — see VehicleController, which forces
     * both this and disposal_date back to null for any other status.
     */
    public const BER_SUB_STATUSES = [
        'FOR_DISPOSAL' => 'For Disposal',
        'DISPOSED'     => 'Disposed',
    ];

    /**
     * How many days a vehicle can sit Unserviceable before it's flagged for
     * admin attention on the Dashboard and Vehicle Inventory (VMIS Additional
     * Updates item 3 — "after 3 months ... notify the admin").
     */
    public const UNSERVICEABLE_ALERT_DAYS = 90;

    protected $fillable = [
        'plate_number',
        'engine_number',
        'chassis_number',
        'make',
        'model',
        'vehicle_type_id',
        'year_model',
        'color',
        'acquisition_date',
        'source',
        'unit_id',
        'station_id',
        'assigned_driver_id',
        'assigned_driver_id_2',
        'odometer_km',
        'next_pms_date',
        'next_pms_odometer_km',
        'status',
        'ber_sub_status',
        'disposal_date',
        'unserviceable_since',
        'is_active',
        'qr_code',
        'encoded_by'
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'next_pms_date' => 'date',
            'disposal_date' => 'date',
            'unserviceable_since' => 'date',
        ];
    }

    /**
     * True once a vehicle is BER and specifically marked Disposed — used to
     * exclude it from the "Total Vehicles" count (it's no longer part of the
     * active fleet) without removing the record from the inventory listing.
     */
    public function isDisposed(): bool
    {
        return $this->status === 'BER' && $this->ber_sub_status === 'DISPOSED';
    }

    /**
     * Query scope: every vehicle EXCEPT ones that are BER + Disposed — the
     * "Total Vehicles" count everywhere in the app (Vehicle Inventory stats,
     * Dashboard) is built from this rather than a bare ->count().
     */
    public function scopeExcludingDisposed($query)
    {
        return $query->where(function ($q) {
            $q->where('status', '!=', 'BER')
              ->orWhereNull('ber_sub_status')
              ->orWhere('ber_sub_status', '!=', 'DISPOSED');
        });
    }

    /**
     * How many days this vehicle has been continuously Unserviceable, or null
     * if it isn't currently Unserviceable (or has no recorded start date —
     * shouldn't happen once VehicleController's normalization has run, but a
     * vehicle predating that column could theoretically still be null here).
     */
    public function daysUnserviceable(): ?int
    {
        if ($this->status !== 'UNSERVICEABLE' || ! $this->unserviceable_since) {
            return null;
        }

        return (int) $this->unserviceable_since->copy()->startOfDay()->diffInDays(now()->startOfDay());
    }

    /**
     * True once a vehicle has been Unserviceable for UNSERVICEABLE_ALERT_DAYS
     * (90) or more — the trigger for the Dashboard/Vehicle Inventory "needs
     * action" flag.
     */
    public function needsUnserviceableAlert(): bool
    {
        return ($this->daysUnserviceable() ?? 0) >= self::UNSERVICEABLE_ALERT_DAYS;
    }

    /**
     * Query scope: every vehicle that's been Unserviceable for 90+ days —
     * used to build the Dashboard/Vehicle Inventory admin alert list.
     */
    public function scopeUnserviceableAlert($query)
    {
        return $query->where('status', 'UNSERVICEABLE')
            ->whereNotNull('unserviceable_since')
            ->where('unserviceable_since', '<=', now()->subDays(self::UNSERVICEABLE_ALERT_DAYS)->startOfDay());
    }

    /**
     * The driver currently designated to this vehicle (slot 1 — the
     * original, still-primary assignment).
     */
    public function driver()
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id');
    }

    /**
     * The second driver currently designated to this vehicle, if any — a
     * vehicle can have up to two assigned drivers at once (Vehicle
     * Inventory's Register/Edit modal), with no priority between the two
     * slots beyond display order.
     */
    public function driver2()
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id_2');
    }

    /**
     * Both assigned drivers, slot 1 then slot 2, skipping whichever slot
     * is empty — the one place "who's assigned to this vehicle" is
     * assembled, so the Vehicle Inventory listing and the mobile vehicle
     * payload can't show the two drivers inconsistently.
     */
    public function assignedDrivers()
    {
        return collect([$this->driver, $this->driver2])->filter()->values();
    }

    /**
     * Query scope: vehicles currently assigned — in EITHER driver slot —
     * to the given driver. Every "what's my vehicle" lookup in this app
     * (web Trip Logs/Fuel Monitoring driver flows, every DRIVER-role
     * mobile API endpoint, FleetAlert's own alert scoping) goes through
     * this rather than comparing assigned_driver_id directly, so a
     * vehicle with two assigned drivers is visible to both of them
     * everywhere, not just to whichever one happens to sit in slot 1.
     */
    public function scopeAssignedToDriver($query, $driverId)
    {
        return $query->where(function ($q) use ($driverId) {
            $q->where('assigned_driver_id', $driverId)
              ->orWhere('assigned_driver_id_2', $driverId);
        });
    }

    /**
     * The category or classification type of the vehicle.
     */
    public function type()
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }

    /**
     * The unit this vehicle is deployed to — used on the Dashboard's
     * Unserviceable-alert panel to give fleet-wide viewers location context.
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /**
     * The specific station this vehicle is deployed to.
     */
    public function station()
    {
        return $this->belongsTo(Station::class, 'station_id');
    }

    public function encoder() {
        return $this->belongsTo(User::class, 'encoded_by');
    }

    public function registrations() {
        return $this->hasMany(VehicleRegistration::class)->latest('registration_year');
    }

    public function latestRegistration() {
        return $this->hasOne(VehicleRegistration::class)->latestOfMany('registration_year');
    }

    /**
     * A registration is flagged on the "Registration Due" panel once its
     * expiry is within this many days (or already past).
     */
    public const REGISTRATION_DUE_SOON_DAYS = 30;

    /**
     * Days remaining until this vehicle's current (latest) registration
     * expires — negative once it's overdue, 0 if it expires today, null if
     * there's no registration on file yet or its expiry_date was never
     * recorded (e.g. a record uploaded before this was tracked).
     * Computed from raw timestamps rather than Carbon::diffInDays(), whose
     * sign convention differs across versions — this way positive always
     * means "in the future" and negative always means "overdue", full stop.
     */
    public function registrationDaysRemaining(): ?int
    {
        $expiry = $this->latestRegistration?->expiry_date;
        if (! $expiry) {
            return null;
        }

        $today = now()->startOfDay();
        $expiryDay = $expiry->copy()->startOfDay();

        return (int) round(($expiryDay->getTimestamp() - $today->getTimestamp()) / 86400);
    }

    /**
     * True once this vehicle's registration has expired or is within
     * REGISTRATION_DUE_SOON_DAYS of expiring — the trigger for the
     * "Registration Due" priority panel on Vehicle Inventory.
     */
    public function needsRegistrationAlert(): bool
    {
        $days = $this->registrationDaysRemaining();

        return $days !== null && $days <= self::REGISTRATION_DUE_SOON_DAYS;
    }

    /**
     * A PMS due date is flagged once it's within this many days (or already
     * past) — same threshold convention as REGISTRATION_DUE_SOON_DAYS,
     * used by the Driver mobile app's vehicle-status screen.
     */
    public const PMS_DUE_SOON_DAYS = 14;

    /**
     * Days remaining until next_pms_date, negative once overdue, null if no
     * PMS date is on file yet. Same timestamp-based computation as
     * registrationDaysRemaining(), for the same sign-convention reason.
     */
    public function pmsDaysRemaining(): ?int
    {
        if (! $this->next_pms_date) {
            return null;
        }

        $today = now()->startOfDay();
        $dueDay = $this->next_pms_date->copy()->startOfDay();

        return (int) round(($dueDay->getTimestamp() - $today->getTimestamp()) / 86400);
    }

    /**
     * Mileage-based PMS trigger — the odometer-reading counterpart to
     * PMS_DUE_SOON_DAYS. A vehicle averaging well above a "typical"
     * pace can rack up thousands of km between calendar services, so
     * this exists alongside the date trigger rather than instead of it
     * (see pmsStatus()) — "every 6 months or 5,000 km, whichever comes
     * first," the same way a manufacturer's own service schedule works.
     */
    public const PMS_DUE_SOON_KM = 500;

    /**
     * Km remaining until next_pms_odometer_km, negative once overdue,
     * null if no mileage target is on file yet. Same shape as
     * pmsDaysRemaining() — positive always means "still ahead," negative
     * always means "overdue."
     */
    public function pmsKmRemaining(): ?int
    {
        if ($this->next_pms_odometer_km === null) {
            return null;
        }

        return (int) $this->next_pms_odometer_km - (int) $this->odometer_km;
    }

    /**
     * Single source of truth for "is this vehicle due for PMS, and why" —
     * every consumer (Vehicle Inventory's badge, the Maintenance & PMS
     * monitoring panel, the Dashboard's due-soon/overdue counts,
     * FleetAlertSyncService, PmsPredictionService) reads this rather than
     * re-deriving its own bucket from next_pms_date, so none of them can
     * ever disagree about whether a vehicle is overdue.
     *
     * The date and mileage triggers are independent — EITHER one being
     * due/overdue is enough, and whichever of the two is more urgent
     * decides the overall bucket. A vehicle with no schedule set at all
     * (neither column populated) gets 'none'.
     *
     * @return array{bucket: string, days: ?int, km: ?int}
     *         bucket: 'overdue' | 'soon' | 'normal' | 'none'
     */
    public function pmsStatus(): array
    {
        $days = $this->pmsDaysRemaining();
        $km = $this->pmsKmRemaining();

        $bucketFor = function (?int $value, int $dueSoonThreshold): string {
            if ($value === null) {
                return 'none';
            }
            if ($value < 0) {
                return 'overdue';
            }
            return $value <= $dueSoonThreshold ? 'soon' : 'normal';
        };

        $dateBucket = $bucketFor($days, self::PMS_DUE_SOON_DAYS);
        $kmBucket = $bucketFor($km, self::PMS_DUE_SOON_KM);

        // Rank, not a plain string compare — picks whichever of the two
        // triggers is currently more urgent ('overdue' beats 'soon' beats
        // 'normal' beats 'none', the last only surviving when BOTH
        // triggers are unset).
        $rank = ['none' => 0, 'normal' => 1, 'soon' => 2, 'overdue' => 3];
        $bucket = $rank[$dateBucket] >= $rank[$kmBucket] ? $dateBucket : $kmBucket;

        return ['bucket' => $bucket, 'days' => $days, 'km' => $km];
    }

    /**
     * Human-readable explanation of pmsStatus() — "overdue 5 day(s)",
     * "due in 320 km", or both joined when both triggers are set, so a
     * badge/alert/tooltip never has to re-derive this phrasing itself.
     */
    public function pmsReason(): string
    {
        $status = $this->pmsStatus();
        $parts = [];

        if ($status['days'] !== null) {
            $parts[] = match (true) {
                $status['days'] < 0 => 'overdue ' . abs($status['days']) . ' day(s)',
                $status['days'] === 0 => 'due today',
                default => 'due in ' . $status['days'] . ' day(s)',
            };
        }

        if ($status['km'] !== null) {
            $parts[] = match (true) {
                $status['km'] < 0 => 'overdue ' . number_format(abs($status['km'])) . ' km',
                $status['km'] === 0 => 'due now (at target odometer)',
                default => 'due in ' . number_format($status['km']) . ' km',
            };
        }

        return $parts ? implode(' · ', $parts) : 'not scheduled';
    }

    /**
     * True once EITHER next_pms_date OR next_pms_odometer_km says this
     * vehicle is due soon or overdue — mirrors needsRegistrationAlert()'s
     * shape, now delegated to pmsStatus() so this can never drift from
     * what the badges/panels actually show.
     */
    public function needsPmsAlert(): bool
    {
        return in_array($this->pmsStatus()['bucket'], ['soon', 'overdue'], true);
    }

    /**
     * Advances this vehicle's recorded odometer_km to $reading if (and
     * only if) that's higher than what's already on file — the running-
     * mileage counterpart to MaintenanceController::
     * syncVehicleFromLatestRecord()'s "odometer only ever moves forward"
     * rule, applied here to every logged trip's ending reading (see
     * TripLogController::store() / Api\TripLogController::store()) so
     * the current mileage keeps climbing day to day instead of sitting
     * frozen at whatever the last completed service happened to record.
     * A trip logged with no odometer reading, or a lower/stale one
     * (a typo, or a manually-logged trip entered out of order), never
     * moves it backward.
     */
    public function bumpOdometer(?int $reading): void
    {
        if ($reading !== null && $reading > ($this->odometer_km ?? 0)) {
            $this->odometer_km = $reading;
            $this->save();
        }
    }

    /**
     * Every logged maintenance/PMS activity for this vehicle, most recent service first.
     */
    public function maintenanceRecords()
    {
        return $this->hasMany(MaintenanceRecord::class)->orderByDesc('service_date')->orderByDesc('id');
    }

    public function latestMaintenanceRecord()
    {
        return $this->hasOne(MaintenanceRecord::class)->latestOfMany('service_date');
    }

    /**
     * Every time this vehicle's QR sticker was printed, and by whom.
     */
    public function qrPrints()
    {
        return $this->hasMany(VehicleQrPrint::class)->latest('printed_at');
    }

    public function latestQrPrint()
    {
        return $this->hasOne(VehicleQrPrint::class)->latestOfMany('printed_at');
    }

    /**
     * Every logged trip for this vehicle, most recent first.
     */
    public function tripLogs()
    {
        return $this->hasMany(TripLog::class)->orderByDesc('trip_date')->orderByDesc('id');
    }

    /**
     * Every logged refuel for this vehicle, most recent first.
     */
    public function fuelLogs()
    {
        return $this->hasMany(FuelLog::class)->orderByDesc('refuel_date')->orderByDesc('id');
    }

    public function latestFuelLog()
    {
        return $this->hasOne(FuelLog::class)->latestOfMany('refuel_date');
    }
}
