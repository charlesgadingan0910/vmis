<?php

namespace App\Services;

use App\Models\FleetAlert;
use App\Models\Geofence;
use App\Models\GeofenceEvent;
use App\Models\Vehicle;

/**
 * Called once per GPS ping (Api\ActiveTripController::ping()) to detect
 * whether a vehicle just crossed into or out of any geofence relevant to
 * it. Stateless by design — "just entered" / "just exited" is derived
 * purely by comparing Geofence::containsPoint() for the PREVIOUS ping's
 * coordinates against the NEW ping's coordinates (both already in hand at
 * the ping() call site), rather than persisting a separate "is vehicle X
 * currently inside zone Y" membership row somewhere. A missing previous
 * point (the very first ping of a trip) is treated as "was outside
 * everything", so a trip that starts already inside a restricted zone
 * still raises an enter event immediately instead of silently missing it.
 */
class GeofenceDetectionService
{
    /**
     * @param  array{lat: float, lng: float}|null  $previousPoint
     * @param  array{lat: float, lng: float}  $newPoint
     */
    public function detect(Vehicle $vehicle, ?int $driverId, ?array $previousPoint, array $newPoint): void
    {
        $geofences = Geofence::query()
            ->where('is_active', true)
            ->where(function ($q) use ($vehicle) {
                $q->whereNull('unit_id')->orWhere('unit_id', $vehicle->unit_id);
            })
            ->where(function ($q) use ($vehicle) {
                $q->whereNull('station_id')->orWhere('station_id', $vehicle->station_id);
            })
            ->get();

        foreach ($geofences as $geofence) {
            $wasInside = $previousPoint ? $geofence->containsPoint($previousPoint['lat'], $previousPoint['lng']) : false;
            $isInside = $geofence->containsPoint($newPoint['lat'], $newPoint['lng']);

            if ($isInside && ! $wasInside) {
                $this->recordEvent($geofence, $vehicle, $driverId, 'enter', $newPoint);
            } elseif (! $isInside && $wasInside) {
                $this->recordEvent($geofence, $vehicle, $driverId, 'exit', $newPoint);
            }
        }
    }

    /**
     * @param  array{lat: float, lng: float}  $point
     */
    protected function recordEvent(Geofence $geofence, Vehicle $vehicle, ?int $driverId, string $eventType, array $point): void
    {
        $event = GeofenceEvent::create([
            'geofence_id'   => $geofence->id,
            'geofence_name' => $geofence->name,
            'vehicle_id'    => $vehicle->id,
            'driver_id'     => $driverId,
            'event_type'    => $eventType,
            'latitude'      => $point['lat'],
            'longitude'     => $point['lng'],
            'occurred_at'   => now(),
        ]);

        $shouldAlert = $eventType === 'enter' ? $geofence->alert_on_enter : $geofence->alert_on_exit;
        if (! $shouldAlert) {
            return;
        }

        $verb = $eventType === 'enter' ? 'entered' : 'exited';
        $type = $eventType === 'enter' ? FleetAlert::TYPE_GEOFENCE_ENTER : FleetAlert::TYPE_GEOFENCE_EXIT;

        FleetAlert::create([
            'type'       => $type,
            'severity'   => FleetAlert::SEVERITY_OVERDUE,
            'vehicle_id' => $vehicle->id,
            'driver_id'  => null,
            'title'      => strtoupper($vehicle->plate_number) . ' ' . $verb . ' ' . $geofence->name,
            'body'       => strtoupper($vehicle->plate_number) . ' (' . trim($vehicle->make . ' ' . $vehicle->model) . ') ' . $verb . ' the "' . $geofence->name . '" zone.',
            'dedupe_key' => 'geofence_event:' . $event->id,
        ]);
    }
}
