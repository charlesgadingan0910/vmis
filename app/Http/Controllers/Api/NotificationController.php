<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FleetAlert;
use App\Services\FleetAlertSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Notification Center for the VMIS mobile app. Open to both DRIVER (their
 * own assigned vehicle's alerts + their own license expiry) and VIEWER
 * (every vehicle alert, same broad visibility VIEWER already gets
 * everywhere else) — see FleetAlert::scopeVisibleTo() for exactly what each
 * role sees. Mirrors the web admin's NotificationController; kept as a
 * separate controller rather than shared, same pattern as the rest of this
 * API (Api\FuelLogController vs FuelLogController, etc.) since the response
 * shape differs slightly (no web route() links here).
 */
class NotificationController extends Controller
{
    protected function syncIfStale(): void
    {
        if (Cache::add('fleet_alerts:last_sync', true, now()->addMinutes(5))) {
            app(FleetAlertSyncService::class)->sync();
        }
    }

    public function index(Request $request): JsonResponse
    {
        $this->syncIfStale();

        $user = $request->user();

        $alerts = FleetAlert::visibleTo($user)
            ->with(['vehicle', 'driver'])
            ->orderByRaw("severity = 'overdue' desc")
            ->latest('created_at')
            ->take(30)
            ->get();

        return response()->json([
            'success'      => true,
            'unread_count' => FleetAlert::visibleTo($user)->unread()->count(),
            'alerts'       => $alerts->map(fn (FleetAlert $a) => $this->payload($a)),
        ]);
    }

    public function markRead(Request $request, FleetAlert $alert): JsonResponse
    {
        $visible = FleetAlert::visibleTo($request->user())->whereKey($alert->id)->exists();
        if (! $visible) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        if (! $alert->isRead()) {
            $alert->forceFill(['read_at' => now()])->save();
        }

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        FleetAlert::visibleTo($request->user())->unread()->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    protected function payload(FleetAlert $a): array
    {
        return [
            'id'                   => $a->id,
            'type'                 => $a->type,
            'type_label'           => FleetAlert::TYPE_LABELS[$a->type] ?? $a->type,
            'severity'             => $a->severity,
            'title'                => $a->title,
            'body'                 => $a->body,
            'is_read'              => $a->isRead(),
            'created_at'           => optional($a->created_at)->toIso8601String(),
            'vehicle_id'           => $a->vehicle_id,
            'vehicle_plate_number' => $a->vehicle ? strtoupper($a->vehicle->plate_number) : null,
        ];
    }
}
