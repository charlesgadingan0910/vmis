<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Geofence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Geofence zones drawn on the Live Monitoring map — see the geofences
 * migration's docblock for the schema, Geofence::containsPoint() for the
 * shape math, and GeofenceDetectionService for how a zone actually turns
 * into an enter/exit fleet_alerts notification.
 *
 * Pure JSON API (no index view of its own — LiveMonitoringController's
 * index() renders the map page, this just backs the "Geofences" panel on
 * it): list/create/update/delete, all AJAX. Scoping mirrors every other
 * module in this app — SUPER ADMINISTRATOR/ADMINISTRATOR unrestricted,
 * UNIT/STATION ADMINISTRATOR confined to their own unit/station (and may
 * only draw a zone scoped to their own unit/station, never a fleet-wide
 * one), VIEWER read-only, DRIVER has no access to Live Monitoring at all
 * so never reaches this controller.
 */
class GeofenceController extends Controller
{
    protected const ROLE_SUPER_ADMIN   = 'SUPER ADMINISTRATOR';
    protected const ROLE_ADMIN         = 'ADMINISTRATOR';
    protected const ROLE_UNIT_ADMIN    = 'UNIT ADMINISTRATOR';
    protected const ROLE_STATION_ADMIN = 'STATION ADMINISTRATOR';
    protected const ROLE_VIEWER        = 'VIEWER';
    protected const ROLE_DRIVER        = 'DRIVER';

    protected const BROAD_VISIBILITY_ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_VIEWER];

    protected function role($user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }

    /**
     * Non-aborting check — true if $user can see this zone at all. A
     * fleet-wide zone (unit_id and station_id both null) is visible to
     * everyone; a unit/station-scoped zone is only visible to admins
     * inside that same unit/station (broad-visibility roles see every
     * zone regardless of scope).
     */
    protected function canView(Geofence $geofence, $user, string $role): bool
    {
        if (in_array($role, self::BROAD_VISIBILITY_ROLES, true)) {
            return true;
        }
        if ($geofence->unit_id === null && $geofence->station_id === null) {
            return true;
        }
        if ($role === self::ROLE_UNIT_ADMIN) {
            return $geofence->unit_id == $user->unit_id;
        }
        if ($role === self::ROLE_STATION_ADMIN) {
            return $geofence->station_id == $user->station_id;
        }
        return false;
    }

    /**
     * Non-aborting check — true if $user can create/edit/delete this
     * zone. VIEWER and DRIVER never can. A Unit/Station Administrator may
     * only manage a zone scoped to their own unit/station — NEVER a
     * fleet-wide zone (unit_id and station_id both null), even though
     * canView() lets them see one. Without this carve-out, editing a
     * fleet-wide zone would hit resolveScope()'s "you can only save
     * within your own scope" rule and silently narrow it down to their
     * own unit/station on save — hiding it from every other unit/station
     * that relied on it. Broad-visibility roles are unrestricted either way.
     */
    protected function canWrite(Geofence $geofence, $user, string $role): bool
    {
        if ($role === self::ROLE_VIEWER || $role === self::ROLE_DRIVER) {
            return false;
        }
        if (in_array($role, self::BROAD_VISIBILITY_ROLES, true)) {
            return true;
        }
        if ($geofence->unit_id === null && $geofence->station_id === null) {
            return false;
        }
        return $this->canView($geofence, $user, $role);
    }

    protected function authorizeWrite(Geofence $geofence, $user, string $role): void
    {
        if (! $this->canWrite($geofence, $user, $role)) {
            abort(403, ($role === self::ROLE_VIEWER)
                ? 'Your account type cannot manage geofences.'
                : 'Unauthorized Action');
        }
    }

    /**
     * Validates the requested unit_id/station_id against the current
     * user's own scope, and returns the pair to actually save. A Unit/
     * Station Administrator can't leave both null (that would draw a
     * fleet-wide zone visible to everyone) or point them at a different
     * unit/station than their own — the request is rejected with a clear
     * message instead of silently being re-scoped out from under them.
     *
     * @return array{0: int|null, 1: int|null}|null  null on failure
     */
    protected function resolveScope(array $validated, $user, string $role, ?string &$error): ?array
    {
        $unitId = $validated['unit_id'] ?? null;
        $stationId = $validated['station_id'] ?? null;

        if (in_array($role, self::BROAD_VISIBILITY_ROLES, true)) {
            return [$unitId, $stationId];
        }

        if ($role === self::ROLE_UNIT_ADMIN) {
            if (($unitId !== null && $unitId != $user->unit_id) || $stationId !== null) {
                $error = 'As a Unit Administrator you can only create zones scoped to your own unit.';
                return null;
            }
            return [$user->unit_id, null];
        }

        if ($role === self::ROLE_STATION_ADMIN) {
            if ($stationId !== null && $stationId != $user->station_id) {
                $error = 'As a Station Administrator you can only create zones scoped to your own station.';
                return null;
            }
            return [$unitId, $user->station_id];
        }

        $error = 'Your account type cannot manage geofences.';
        return null;
    }

    /**
     * Shape-dependent validation for the geometry payload — a circle
     * needs a center point + radius, a polygon needs at least 3 points.
     * Kept as its own method since both store() and update() need it.
     */
    protected function geometryRules(string $shapeType): array
    {
        if ($shapeType === 'circle') {
            return [
                'geometry.center'             => ['required', 'array'],
                'geometry.center.lat'         => ['required', 'numeric', 'between:-90,90'],
                'geometry.center.lng'         => ['required', 'numeric', 'between:-180,180'],
                'geometry.radius_meters'      => ['required', 'numeric', 'min:10'],
            ];
        }

        return [
            'geometry.points'     => ['required', 'array', 'min:3'],
            'geometry.points.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'geometry.points.*.lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Every active zone visible to this user, for rendering on the Live
     * Monitoring map. Unpaginated — a real deployment realistically draws
     * a handful of zones (compounds, restricted areas), not hundreds.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $this->role($user);

        $query = Geofence::query()->where('is_active', true);

        if (! in_array($role, self::BROAD_VISIBILITY_ROLES, true)) {
            $query->where(function ($q) use ($user, $role) {
                $q->where(function ($fleetWide) {
                    $fleetWide->whereNull('unit_id')->whereNull('station_id');
                });
                if ($role === self::ROLE_UNIT_ADMIN) {
                    $q->orWhere('unit_id', $user->unit_id);
                } elseif ($role === self::ROLE_STATION_ADMIN) {
                    $q->orWhere('station_id', $user->station_id);
                }
            });
        }

        $canManage = ! in_array($role, [self::ROLE_VIEWER, self::ROLE_DRIVER], true);

        $geofences = $query->orderBy('name')->get()->map(function (Geofence $g) use ($user, $role, $canManage) {
            return [
                'id'             => $g->id,
                'name'           => $g->name,
                'description'    => $g->description,
                'zone_type'      => $g->zone_type,
                'shape_type'     => $g->shape_type,
                'geometry'       => $g->geometry,
                'color'          => $g->color,
                'alert_on_enter' => $g->alert_on_enter,
                'alert_on_exit'  => $g->alert_on_exit,
                'unit_id'        => $g->unit_id,
                'station_id'     => $g->station_id,
                'can_edit'       => $canManage && $this->canWrite($g, $user, $role),
            ];
        });

        return response()->json(['success' => true, 'can_create' => $canManage, 'geofences' => $geofences]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $this->role($user);

        if ($role === self::ROLE_VIEWER || $role === self::ROLE_DRIVER) {
            abort(403, 'Your account type cannot manage geofences.');
        }

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:150'],
            'description'    => ['nullable', 'string', 'max:500'],
            'zone_type'      => ['required', 'string', Rule::in(['restricted', 'allowed', 'checkpoint'])],
            'shape_type'     => ['required', 'string', Rule::in(['circle', 'polygon'])],
            'color'          => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'alert_on_enter' => ['nullable', 'boolean'],
            'alert_on_exit'  => ['nullable', 'boolean'],
            'unit_id'        => ['nullable', 'exists:units,id'],
            'station_id'     => ['nullable', 'exists:stations,id'],
        ] + $this->geometryRules($request->input('shape_type', 'circle')));

        $error = null;
        $scope = $this->resolveScope($validated, $user, $role, $error);
        if ($scope === null) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }

        $geofence = Geofence::create([
            'name'           => $validated['name'],
            'description'    => $validated['description'] ?? null,
            'zone_type'      => $validated['zone_type'],
            'shape_type'     => $validated['shape_type'],
            'geometry'       => $validated['geometry'],
            'color'          => $validated['color'] ?? '#ef4444',
            'alert_on_enter' => $request->boolean('alert_on_enter', true),
            'alert_on_exit'  => $request->boolean('alert_on_exit', false),
            'unit_id'        => $scope[0],
            'station_id'     => $scope[1],
            'is_active'      => true,
            'created_by'     => $user->id,
        ]);

        ActivityLog::record(
            'created',
            'Geofence',
            'Created geofence "' . $geofence->name . '".',
            $geofence,
            ['after' => $geofence->only(['name', 'zone_type', 'shape_type', 'unit_id', 'station_id'])]
        );

        return response()->json(['success' => true, 'message' => 'Geofence "' . $geofence->name . '" created.', 'id' => $geofence->id]);
    }

    public function update(Request $request, Geofence $geofence): JsonResponse
    {
        $user = $request->user();
        $role = $this->role($user);
        $this->authorizeWrite($geofence, $user, $role);

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:150'],
            'description'    => ['nullable', 'string', 'max:500'],
            'zone_type'      => ['required', 'string', Rule::in(['restricted', 'allowed', 'checkpoint'])],
            'shape_type'     => ['required', 'string', Rule::in(['circle', 'polygon'])],
            'color'          => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'alert_on_enter' => ['nullable', 'boolean'],
            'alert_on_exit'  => ['nullable', 'boolean'],
            'unit_id'        => ['nullable', 'exists:units,id'],
            'station_id'     => ['nullable', 'exists:stations,id'],
            'is_active'      => ['nullable', 'boolean'],
        ] + $this->geometryRules($request->input('shape_type', 'circle')));

        $error = null;
        $scope = $this->resolveScope($validated, $user, $role, $error);
        if ($scope === null) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }

        $before = $geofence->only(['name', 'zone_type', 'shape_type', 'unit_id', 'station_id', 'is_active']);

        $geofence->update([
            'name'           => $validated['name'],
            'description'    => $validated['description'] ?? null,
            'zone_type'      => $validated['zone_type'],
            'shape_type'     => $validated['shape_type'],
            'geometry'       => $validated['geometry'],
            'color'          => $validated['color'] ?? $geofence->color,
            'alert_on_enter' => $request->boolean('alert_on_enter', $geofence->alert_on_enter),
            'alert_on_exit'  => $request->boolean('alert_on_exit', $geofence->alert_on_exit),
            'unit_id'        => $scope[0],
            'station_id'     => $scope[1],
            'is_active'      => $request->has('is_active') ? $request->boolean('is_active') : $geofence->is_active,
        ]);

        ActivityLog::record(
            'updated',
            'Geofence',
            'Updated geofence "' . $geofence->name . '".',
            $geofence,
            ['before' => $before, 'after' => $geofence->only(['name', 'zone_type', 'shape_type', 'unit_id', 'station_id', 'is_active'])]
        );

        return response()->json(['success' => true, 'message' => 'Geofence "' . $geofence->name . '" updated.']);
    }

    public function destroy(Request $request, Geofence $geofence): JsonResponse
    {
        $user = $request->user();
        $role = $this->role($user);
        $this->authorizeWrite($geofence, $user, $role);

        $name = $geofence->name;
        $before = $geofence->only(['name', 'zone_type', 'shape_type', 'unit_id', 'station_id']);

        // Hard delete, per this codebase's convention (no SoftDeletes
        // anywhere) — geofence_events.geofence_id is nullOnDelete so the
        // event history this zone generated survives with its
        // geofence_name snapshot intact.
        $geofence->delete();

        ActivityLog::record(
            'deleted',
            'Geofence',
            'Deleted geofence "' . $name . '".',
            $geofence,
            ['before' => $before]
        );

        return response()->json(['success' => true, 'message' => 'Geofence "' . $name . '" deleted.']);
    }
}
