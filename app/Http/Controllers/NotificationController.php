<?php

namespace App\Http\Controllers;

use App\Models\FleetAlert;
use App\Services\FleetAlertSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Backs the header bell/Notification Center on the web admin side. See
 * FleetAlertSyncService for how fleet_alerts rows get generated, and
 * FleetAlert::scopeVisibleTo() for the same role/unit/station visibility
 * every other page already enforces.
 */
class NotificationController extends Controller
{
    /**
     * Recomputes fleet_alerts at most once every 5 minutes (shared across
     * every user hitting this endpoint — Cache::add() only succeeds for the
     * first caller in that window), so the sync's per-vehicle/driver scan
     * doesn't re-run on every single bell click.
     */
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

        $unreadCount = FleetAlert::visibleTo($user)->unread()->count();

        return response()->json([
            'success'      => true,
            'unread_count' => $unreadCount,
            'alerts'       => $alerts->map(fn (FleetAlert $a) => $this->payload($a)),
        ]);
    }

    public function markRead(Request $request, FleetAlert $alert): JsonResponse
    {
        // visibleTo() re-checked here (not just on index()) so a user can't
        // mark an alert read by guessing another vehicle's alert id.
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
            'id'         => $a->id,
            'type'       => $a->type,
            'type_label' => FleetAlert::TYPE_LABELS[$a->type] ?? $a->type,
            'icon'       => FleetAlert::TYPE_ICONS[$a->type] ?? 'fa-bell',
            'severity'   => $a->severity,
            'title'      => $a->title,
            'body'       => $a->body,
            'is_read'    => $a->isRead(),
            'created_at' => $a->created_at->diffForHumans(),
            'link'       => $this->linkFor($a),
        ];
    }

    /**
     * Geofence alerts belong on Live Monitoring (that's where the zone and
     * the vehicle's live position are visible) rather than the Vehicles
     * list every other vehicle-scoped alert type links to.
     */
    protected function linkFor(FleetAlert $a): ?string
    {
        if (! $a->vehicle_id) {
            return null;
        }

        if (in_array($a->type, [FleetAlert::TYPE_GEOFENCE_ENTER, FleetAlert::TYPE_GEOFENCE_EXIT], true)) {
            return route('live-monitoring.index');
        }

        return route('vehicles.index');
    }
}
