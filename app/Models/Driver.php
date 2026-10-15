<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    /**
     * DL (Driver's License) Codes — the LTO vehicle-class authorizations
     * printed on the back of the license card. Distinct from `license_type`
     * (Professional/Non-Professional), which is a separate classification.
     */
    public const DL_CODES = [
        'A'  => 'Motorcycle / motorized tricycle',
        'A1' => 'Tricycle below 400kg vehicle weight',
        'B'  => 'Up to 5,000kg GVW, max 8 seats excl. driver (car, SUV, pick-up)',
        'B1' => 'Up to 5,000kg GVW, more than 8 seats excl. driver (AUV, van)',
        'B2' => 'Up to 5,000kg GVW, used for goods transport (truck, delivery van)',
        'C'  => 'Above 5,000kg GVW (truck, bus)',
        'D'  => 'Above 5,000kg GVW, public transport, more than 8 seats (bus)',
        'BE' => 'Category B vehicle with trailer exceeding 650kg GVW',
        'CE' => 'Category C vehicle with trailer exceeding 650kg GVW',
    ];

    /**
     * Numbered driving-condition restrictions printed on the license back.
     */
    public const RESTRICTION_CODES = [
        '1' => 'Corrective lenses',
        '2' => 'Special equipment for limbs',
        '3' => 'Customized vehicle only',
        '4' => 'Daylight driving only',
        '5' => 'Hearing aid required',
    ];

    protected $fillable = [
        'rank', 'firstname', 'middlename', 'lastname', 'qlfr',
        'license_number', 'license_expiration_date', 'license_type',
        'dl_codes', 'restriction_codes', 'license_serial_number',
        'contact_number', 'emergency_contact_name', 'emergency_contact_address',
        'emergency_contact_number', 'status', 'photo_path'
    ];

    protected $casts = [
        'license_expiration_date' => 'date',
        'dl_codes' => 'array',
        'restriction_codes' => 'array',
    ];

    /**
     * Days remaining until license_expiration_date, negative once overdue,
     * null if no expiration date is on file yet. Same timestamp-based
     * computation as Vehicle::registrationDaysRemaining() / pmsDaysRemaining(),
     * for the same sign-convention reason (positive = future, negative =
     * overdue, 0 = expires today).
     */
    public function licenseDaysRemaining(): ?int
    {
        if (! $this->license_expiration_date) {
            return null;
        }

        $today = now()->startOfDay();
        $expiryDay = $this->license_expiration_date->copy()->startOfDay();

        return (int) round(($expiryDay->getTimestamp() - $today->getTimestamp()) / 86400);
    }

    /**
     * True once license_expiration_date has passed. False (not true) when
     * there is no expiration date on file at all — an incomplete record
     * should not silently block registration/assignment; that is a data
     * gap for the admin to fill in, not an expired license.
     */
    public function isLicenseExpired(): bool
    {
        $days = $this->licenseDaysRemaining();

        return $days !== null && $days < 0;
    }

    /**
     * The DRIVER-type login account linked to this profile, if one has been
     * created for them on the System Users page — most drivers on file may
     * not have a login account at all, so this is frequently null.
     */
    public function user()
    {
        return $this->hasOne(User::class, 'driver_id');
    }

    /**
     * Vehicle(s) currently assigned to this driver as the PRIMARY (slot 1)
     * driver (vehicles.assigned_driver_id). See vehiclesAsSecondary() for
     * slot 2 — a driver can appear in either slot, so neither relation
     * alone is the full picture of "this driver's vehicle(s)"; app code
     * that needs that uses Vehicle::assignedToDriver() instead.
     */
    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'assigned_driver_id');
    }

    /**
     * Vehicle(s) where this driver sits in the SECOND assigned-driver slot
     * (vehicles.assigned_driver_id_2) — see Vehicle::driver2().
     */
    public function vehiclesAsSecondary()
    {
        return $this->hasMany(Vehicle::class, 'assigned_driver_id_2');
    }
}