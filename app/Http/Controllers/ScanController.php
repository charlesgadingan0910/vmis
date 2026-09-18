<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\View\View;

class ScanController extends Controller
{
    /**
     * The in-app camera scanner page — lets a logged-in user scan a vehicle's
     * QR sticker with their device camera without leaving the app or relying
     * on the phone's default camera app.
     */
    public function index(): View
    {
        return view('scan.index');
    }

    /**
     * What every printed QR sticker actually links to: the vehicle's full profile.
     * This route sits behind the same auth + password.changed middleware as the
     * rest of the app, so scanning while logged out sends the officer to login
     * first and bounces them back here automatically afterward.
     */
    public function show(string $qrCode): View
    {
        $vehicle = Vehicle::with([
                'driver',
                'type',
                'encoder',
                'latestRegistration',
                'registrations.uploader',
            ])
            ->where('qr_code', $qrCode)
            ->firstOrFail();

        return view('vehicles.scan-result', compact('vehicle'));
    }
}
