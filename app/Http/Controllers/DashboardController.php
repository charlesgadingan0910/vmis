<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\Driver;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'total_vehicles'       => Vehicle::count(),
            'serviceable_vehicles' => Vehicle::where('status', 'SERVICEABLE')->count(),
            'total_drivers'        => Driver::count(),
            'expiring_licenses'    => Driver::whereBetween('license_expiration_date', [
                                          now()->startOfDay(), now()->addDays(30)->endOfDay(),
                                      ])->count(),
        ];

        return view('dashboard', compact('stats'));
    }
}
