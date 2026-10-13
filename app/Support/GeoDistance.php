<?php

namespace App\Support;

/**
 * Pure-math helper that turns a GPS breadcrumb trail into a total distance
 * covered. Used by both TripLogController (web) and Api\TripLogController
 * (mobile) so a trip's stored distance_km is always server-computed from
 * the actual route_points rather than trusted blindly from the client —
 * the client-sent distance_km is only ever a fallback for the rare case a
 * trip has start/end coordinates but no full route_points trail.
 */
class GeoDistance
{
    /**
     * Sums the great-circle distance between each consecutive pair of
     * points in $routePoints (each an array with numeric 'lat'/'lng' keys,
     * in recording order). Returns kilometers, rounded to 2 decimals.
     * Fewer than 2 usable points returns 0.0 — a single GPS fix on its own
     * carries no distance.
     */
    public static function totalKm(array $routePoints): float
    {
        $usable = array_values(array_filter(
            $routePoints,
            fn ($p) => is_array($p) && isset($p['lat'], $p['lng']) && is_numeric($p['lat']) && is_numeric($p['lng'])
        ));

        if (count($usable) < 2) {
            return 0.0;
        }

        $totalMeters = 0.0;
        for ($i = 1; $i < count($usable); $i++) {
            $totalMeters += self::haversineMeters(
                (float) $usable[$i - 1]['lat'],
                (float) $usable[$i - 1]['lng'],
                (float) $usable[$i]['lat'],
                (float) $usable[$i]['lng'],
            );
        }

        return round($totalMeters / 1000, 2);
    }

    /**
     * Great-circle distance between two points, in meters — the same
     * haversine math totalKm() sums internally, exposed directly for a
     * single-pair check like Geofence::containsPoint()'s circle case.
     */
    public static function pointDistanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return self::haversineMeters($lat1, $lng1, $lat2, $lng2);
    }

    protected static function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusMeters = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusMeters * $c;
    }
}
