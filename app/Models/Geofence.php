<?php

namespace App\Models;

use App\Support\GeoDistance;
use Illuminate\Database\Eloquent\Model;

/**
 * A named zone drawn on the Live Monitoring map — see the geofences
 * migration's docblock for the full schema rationale and
 * GeofenceDetectionService for how containsPoint() below is actually used
 * to detect enter/exit transitions from GPS pings.
 */
class Geofence extends Model
{
    protected $fillable = [
        'name',
        'description',
        'zone_type',
        'shape_type',
        'geometry',
        'color',
        'alert_on_enter',
        'alert_on_exit',
        'unit_id',
        'station_id',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'geometry'       => 'array',
            'alert_on_enter' => 'boolean',
            'alert_on_exit'  => 'boolean',
            'is_active'      => 'boolean',
        ];
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events()
    {
        return $this->hasMany(GeofenceEvent::class);
    }

    /**
     * Whether (lat, lng) falls inside this zone — a plain radius check for
     * a circle, ray-casting for a polygon. Planar ray-casting (lat/lng
     * treated as ordinary y/x on a flat plane) rather than a geodesic
     * point-in-polygon is accurate enough at the sizes a real geofence
     * here is drawn at (a compound, a city, a province) — the distortion
     * only becomes meaningful at scales far larger than that.
     */
    public function containsPoint(float $lat, float $lng): bool
    {
        if ($this->shape_type === 'circle') {
            $center = $this->geometry['center'] ?? null;
            $radius = $this->geometry['radius_meters'] ?? null;
            if (! $center || ! isset($center['lat'], $center['lng']) || ! $radius) {
                return false;
            }

            return GeoDistance::pointDistanceMeters((float) $center['lat'], (float) $center['lng'], $lat, $lng) <= (float) $radius;
        }

        if ($this->shape_type === 'polygon') {
            return $this->pointInPolygon($lat, $lng, $this->geometry['points'] ?? []);
        }

        return false;
    }

    protected function pointInPolygon(float $lat, float $lng, array $points): bool
    {
        $n = count($points);
        if ($n < 3) {
            return false;
        }

        $inside = false;
        $j = $n - 1;
        for ($i = 0; $i < $n; $i++) {
            $yi = (float) ($points[$i]['lat'] ?? 0);
            $xi = (float) ($points[$i]['lng'] ?? 0);
            $yj = (float) ($points[$j]['lat'] ?? 0);
            $xj = (float) ($points[$j]['lng'] ?? 0);

            $intersect = (($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi);
            if ($intersect) {
                $inside = ! $inside;
            }
            $j = $i;
        }

        return $inside;
    }
}
