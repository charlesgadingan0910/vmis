<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Services\DocumentIntelligenceService;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function __construct(protected DocumentIntelligenceService $documentIntelligence)
    {
    }

    /**
     * The in-app camera scanner page — lets a logged-in user scan a vehicle's
     * QR sticker with their device camera without leaving the app or relying
     * on the phone's default camera app.
     */
    public function index(): View
    {
        // Same "only show the AI button when a key is actually configured"
        // rule used on the vehicle/maintenance forms — keeps the fallback
        // itself (typing the plate) working with nothing extra to explain
        // when ANTHROPIC_API_KEY isn't set.
        $aiDocumentScanningEnabled = $this->documentIntelligence->isConfigured();

        return view('scan.index', compact('aiDocumentScanningEnabled'));
    }

    /**
     * What every printed QR sticker actually links to: the vehicle's full profile.
     * This route sits behind the same auth + password.changed middleware as the
     * rest of the app, so scanning while logged out sends the officer to login
     * first and bounces them back here automatically afterward.
     */
    public function show(string $qrCode)
    {
        $vehicle = Vehicle::with([
                'driver',
                'type',
                'encoder',
                'latestRegistration',
                'registrations.uploader',
            ])
            ->where('qr_code', $qrCode)
            ->first();

        if (! $vehicle) {
            return response()->view('vehicles.scan-not-found', [
                'identifier' => $qrCode,
                'type'       => 'code',
            ], 404);
        }

        return view('vehicles.scan-result', compact('vehicle'));
    }

    /**
     * Manual-entry fallback for when the camera can't scan (common on non-HTTPS
     * mobile connections). Deliberately looks up by PLATE NUMBER, not qr_code —
     * the plate is the only identifier actually printed and visible on the
     * physical sticker; the raw qr_code token is invisible to a human, so asking
     * someone to type it in was the real bug being fixed here.
     */
    public function showByPlate(string $plate)
    {
        $plate = strtoupper(trim($plate));

        $vehicle = Vehicle::with([
                'driver',
                'type',
                'encoder',
                'latestRegistration',
                'registrations.uploader',
            ])
            ->where('plate_number', $plate)
            ->first();

        if (! $vehicle) {
            return response()->view('vehicles.scan-not-found', [
                'identifier' => $plate,
                'type'       => 'plate',
            ], 404);
        }

        return view('vehicles.scan-result', compact('vehicle'));
    }
}
