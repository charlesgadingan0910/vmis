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
        'odometer_km',
        'next_pms_date',
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
     * The driver currently designated to this vehicle.
     */
    public function driver()
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id');
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
