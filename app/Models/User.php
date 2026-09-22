<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'account_type',
    'rank',
    'lastname',
    'firstname',
    'middlename',
    'qlfr',
    'fullname',
    'badge_number',
    'email',
    'password',
    'unit_id',
    'station_id',
    'driver_id',
    'is_active',
    'is_online',
    'is_password_changed',
    'created_by',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Who created/last (re)activated this account — surfaced on the System
     * Users page per the user-management spec's transparency requirement.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The Driver profile record this DRIVER-type login account is linked to
     * (license number, contact info, etc.) — null for every other account
     * type. See TripLogController for how this resolves to "their vehicle".
     */
    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }
}