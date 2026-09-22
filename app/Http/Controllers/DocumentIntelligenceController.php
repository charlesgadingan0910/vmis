<?php

namespace App\Http\Controllers;

use App\Services\DocumentIntelligenceService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Thin AJAX endpoints in front of DocumentIntelligenceService. Every action
 * here follows the same rule on failure: return a friendly JSON error
 * instead of a 500, so a scanning hiccup (missing API key, network blip,
 * an unreadable photo) never blocks the manual-entry path the rest of the
 * form already supports — the officer just types the fields in themselves,
 * exactly as before this feature existed.
 */
class DocumentIntelligenceController extends Controller
{
    public function __construct(protected DocumentIntelligenceService $documentIntelligence)
    {
    }

    /**
     * Auto-fill for the vehicle registration form, from an OR/CR photo.
     */
    public function extractVehicleDocument(Request $request): JsonResponse
    {
        return $this->extract($request, fn (string $path) => $this->documentIntelligence->extractVehicleRegistration($path));
    }

    /**
     * Auto-fill for the maintenance log form, from a receipt/invoice photo.
     */
    public function extractMaintenanceReceipt(Request $request): JsonResponse
    {
        return $this->extract($request, fn (string $path) => $this->documentIntelligence->extractMaintenanceReceipt($path));
    }

    /**
     * QR-scanner fallback: reads a plate number from a photo so a damaged or
     * missing QR sticker doesn't dead-end the lookup.
     */
    public function extractPlate(Request $request): JsonResponse
    {
        return $this->extract($request, fn (string $path) => $this->documentIntelligence->extractPlateNumber($path));
    }

    /**
     * Shared validate-call-respond flow for all three endpoints above.
     */
    protected function extract(Request $request, Closure $callback): JsonResponse
    {
        // OR/CR and maintenance receipts are very commonly saved as PDF rather
        // than a photo, so this accepts PDF alongside the usual image types —
        // DocumentIntelligenceService sends a PDF to the API as a "document"
        // block instead of an "image" one, no conversion needed on our end.
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpeg,jpg,png,gif,webp,pdf', 'max:15360'],
        ]);

        try {
            $data = $callback($request->file('image')->getRealPath());

            return response()->json(['success' => true, 'data' => $data]);
        } catch (RuntimeException $e) {
            // Expected, user-facing failures (not configured, bad response, unreadable file).
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'AI document scanning is unavailable right now. Please enter the details manually.',
            ]);
        }
    }
}
