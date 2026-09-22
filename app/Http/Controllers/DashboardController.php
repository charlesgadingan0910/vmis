<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\MaintenanceRecord;
use App\Models\Station;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;

class DashboardController extends Controller
{
    // Same role handling as VehicleController/MaintenanceController — the dashboard's
    // numbers have to respect the same unit/station visibility those pages already
    // enforce, otherwise a Station Administrator would see organization-wide totals here that
    // contradict what they can actually open on the Vehicle Inventory or Maintenance page.
    protected const ROLE_SUPER_ADMIN   = 'SUPER ADMINISTRATOR';
    protected const ROLE_ADMIN         = 'ADMINISTRATOR';
    protected const ROLE_UNIT_ADMIN    = 'UNIT ADMINISTRATOR';
    protected const ROLE_STATION_ADMIN = 'STATION ADMINISTRATOR';
    protected const ROLE_VIEWER        = 'VIEWER';

    protected const BROAD_VISIBILITY_ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_VIEWER];

    // Kept identical to the threshold used on the Vehicle Inventory PMS badge and the
    // Maintenance & PMS monitoring panel, so "due soon" means the same thing everywhere.
    protected const DUE_SOON_DAYS = 14;

    protected function role($user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }

    public function dashboard()
    {
        $user = auth()->user();
        $role = $this->role($user);
        $hasBroadVisibility = in_array($role, self::BROAD_VISIBILITY_ROLES, true);

        // Reused both as a plain query constraint and inside withCount()'s relation
        // closure below — one definition of "what this role can see" for vehicles.
        $scopeVehicles = function ($q) use ($role, $user) {
            if ($role === self::ROLE_UNIT_ADMIN) {
                $q->where('unit_id', $user->unit_id);
            } elseif ($role === self::ROLE_STATION_ADMIN) {
                $q->where('station_id', $user->station_id);
            }
        };

        // ---------------- Vehicles ----------------
        $vehicleScope = Vehicle::query();
        $scopeVehicles($vehicleScope);

        $vehicleStats = [
            'total'         => (clone $vehicleScope)->count(),
            'serviceable'   => (clone $vehicleScope)->where('status', 'SERVICEABLE')->count(),
            'unserviceable' => (clone $vehicleScope)->where('status', 'UNSERVICEABLE')->count(),
            'ber'           => (clone $vehicleScope)->where('status', 'BER')->count(),
        ];
        $vehicleStats['serviceable_pct'] = $vehicleStats['total'] > 0
            ? (int) round(($vehicleStats['serviceable'] / $vehicleStats['total']) * 100)
            : 0;

        $dueSoon = 0;
        $overdue = 0;
        foreach ((clone $vehicleScope)->whereNotNull('next_pms_date')->pluck('next_pms_date') as $date) {
            $days = now()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);
            if ($days < 0) {
                $overdue++;
            } elseif ($days <= self::DUE_SOON_DAYS) {
                $dueSoon++;
            }
        }
        $vehicleStats['due_soon'] = $dueSoon;
        $vehicleStats['overdue'] = $overdue;

        $typeBreakdown = VehicleType::withCount(['vehicles' => $scopeVehicles])
            ->having('vehicles_count', '>', 0)
            ->orderByDesc('vehicles_count')
            ->take(6)
            ->get();

        // ---------------- Maintenance & PMS ----------------
        $maintenanceScope = MaintenanceRecord::query()->whereHas('vehicle', $scopeVehicles);

        $maintenanceStats = [
            'total'          => (clone $maintenanceScope)->count(),
            'this_month'     => (clone $maintenanceScope)->whereBetween('service_date', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'this_month_cost'=> (clone $maintenanceScope)->whereBetween('service_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('cost'),
        ];

        $recentMaintenance = (clone $maintenanceScope)
            ->with(['vehicle', 'recorder'])
            ->latest('created_at')
            ->take(6)
            ->get();

        // ---------------- Personnel ----------------
        // Drivers carry no unit_id/station_id in this schema (see drivers migration),
        // so — matching DriverController's own index(), which likewise shows every
        // driver regardless of role — these stay unscoped rather than inventing a
        // restriction the rest of the app doesn't have.
        $driverStats = [
            'total'    => Driver::count(),
            'active'   => Driver::where('status', 'active')->count(),
            'expiring' => Driver::whereBetween('license_expiration_date', [now()->startOfDay(), now()->addDays(30)->endOfDay()])->count(),
            'expired'  => Driver::where('license_expiration_date', '<', now()->startOfDay())->count(),
        ];

        // ---------------- System Users ----------------
        // Mirrors UserController::index()'s own visibility rule exactly, so the count
        // shown here always matches what this role would actually see on that page —
        // including the Unit Administrator scope covering its own unit AND every
        // station under that unit, not just a bare unit_id match.
        $userQuery = User::query();
        if ($role === self::ROLE_ADMIN) {
            $userQuery->whereIn('account_type', [self::ROLE_ADMIN, self::ROLE_UNIT_ADMIN, self::ROLE_STATION_ADMIN, self::ROLE_VIEWER]);
        } elseif ($role === self::ROLE_UNIT_ADMIN) {
            $stationIds = Station::where('unit_id', $user->unit_id)->pluck('id');
            $userQuery->where(function ($q) use ($user, $stationIds) {
                $q->where('unit_id', $user->unit_id);
                if ($stationIds->isNotEmpty()) {
                    $q->orWhereIn('station_id', $stationIds);
                }
            });
        } elseif ($role === self::ROLE_STATION_ADMIN) {
            $userQuery->where('station_id', $user->station_id);
        }
        // Super Admin & Viewer: unfiltered, same as the implicit else branch there.

        $userStats = [
            'total'  => (clone $userQuery)->count(),
            'active' => (clone $userQuery)->where('is_active', '1')->count(),
            'online' => (clone $userQuery)->where('is_online', '1')->count(),
        ];

        $userRoleBreakdown = (clone $userQuery)
            ->selectRaw('account_type, count(*) as total')
            ->groupBy('account_type')
            ->orderByDesc('total')
            ->pluck('total', 'account_type');

        // ---------------- System overview (broad-visibility roles only) ----------------
        $systemOverview = $hasBroadVisibility ? [
            'units'         => Unit::count(),
            'stations'      => Station::count(),
            'vehicle_types' => VehicleType::count(),
        ] : null;

        // A short "scoped to X" line for the welcome banner — only meaningful for
        // roles that are actually restricted to one unit/station.
        $scopeLabel = null;
        if ($role === self::ROLE_UNIT_ADMIN && $user->unit_id) {
            $scopeLabel = optional(Unit::find($user->unit_id))->unit_name;
        } elseif ($role === self::ROLE_STATION_ADMIN && $user->station_id) {
            $scopeLabel = optional(Station::find($user->station_id))->station_name;
        }

        return view('dashboard', [
            'user'               => $user,
            'role'               => $role,
            'hasBroadVisibility' => $hasBroadVisibility,
            'scopeLabel'         => $scopeLabel,
            'vehicleStats'       => $vehicleStats,
            'typeBreakdown'      => $typeBreakdown,
            'maintenanceStats'   => $maintenanceStats,
            'recentMaintenance'  => $recentMaintenance,
            'driverStats'        => $driverStats,
            'userStats'          => $userStats,
            'userRoleBreakdown'  => $userRoleBreakdown,
            'systemOverview'     => $systemOverview,
        ]);
    }
}
