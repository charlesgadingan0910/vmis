<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * P2-02 — vehicle requests and dispatch.
 *
 * - STATION / UNIT / ADMINISTRATOR / SUPER ADMINISTRATOR can request a vehicle
 *   inside their scope; Drivers cannot, Viewers see the calendar only.
 * - A request is approved by the Unit Administrator of the vehicle's unit or a
 *   higher role (never by its own requester — same rank rule as P2-01). A
 *   Super Administrator's own request is approved on the spot.
 * - The approver may swap the vehicle and/or driver when approving.
 * - An APPROVED request is a booking: another request for the same vehicle —
 *   or the same driver — cannot overlap it. Back-to-back bookings are fine.
 */
class DispatchService
{
    /** Dispatch times are entered in Philippine time. */
    public const TIMEZONE = 'Asia/Manila';

    public static function canRequest(User $user): bool
    {
        return ApprovalService::rank(ApprovalService::role($user)) >= 1;
    }

    public static function canView(User $user): bool
    {
        return self::canRequest($user) || ApprovalService::role($user) === 'VIEWER';
    }

    /** Vehicles this user may request / dispatch (serviceable, not disposed, in scope). */
    public static function bookableVehicles(User $user): Builder
    {
        $query = Vehicle::excludingDisposed()->where('status', 'SERVICEABLE');
        $role = ApprovalService::role($user);

        if ($role === 'UNIT ADMINISTRATOR') {
            $query->where('unit_id', $user->unit_id);
        } elseif ($role === 'STATION ADMINISTRATOR') {
            $query->where('station_id', $user->station_id);
        }

        return $query;
    }

    /** May $user approve or reject this request? */
    public static function canDecide(User $user, VehicleRequest $request): bool
    {
        if (! $request->isPending()) {
            return false;
        }

        $role = ApprovalService::role($user);
        $rank = ApprovalService::rank($role);

        if ($rank < 2 || $rank <= ApprovalService::rank($request->requester_role)) {
            return false;
        }

        if ($role === 'UNIT ADMINISTRATOR') {
            return $request->vehicle && $user->unit_id && $request->vehicle->unit_id == $user->unit_id;
        }

        return true;
    }

    /** Pending requests waiting on this user. @return Collection<int, VehicleRequest> */
    public static function pendingFor(User $user): Collection
    {
        if (ApprovalService::rank(ApprovalService::role($user)) < 2) {
            return collect();
        }

        return VehicleRequest::with('vehicle')->where('status', VehicleRequest::STATUS_PENDING)->get()
            ->filter(fn ($r) => self::canDecide($user, $r))->values();
    }

    /**
     * Approved bookings that clash with this vehicle or driver in the period.
     *
     * @return Collection<int, VehicleRequest>
     */
    public function conflicts(int $vehicleId, ?int $driverId, Carbon $start, Carbon $end, ?int $ignoreId = null): Collection
    {
        return VehicleRequest::with(['vehicle', 'driver'])
            ->approved()
            ->overlapping($start, $end)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where(function ($q) use ($vehicleId, $driverId) {
                $q->where('vehicle_id', $vehicleId);
                if ($driverId) {
                    $q->orWhere('driver_id', $driverId);
                }
            })
            ->orderBy('start_at')
            ->get();
    }

    protected function conflictMessage(Collection $conflicts, int $vehicleId): string
    {
        $c = $conflicts->first();
        $what = $c->vehicle_id === $vehicleId
            ? '[' . strtoupper(optional($c->vehicle)->plate_number) . '] is already booked'
            : trim(optional($c->driver)->firstname . ' ' . optional($c->driver)->lastname) . ' is already booked on [' . strtoupper(optional($c->vehicle)->plate_number) . ']';

        return $what . ' from ' . $c->start_at->format('M d, Y h:i A') . ' to ' . $c->end_at->format('M d, Y h:i A') . '.';
    }

    protected function assertDriverUsable(?int $driverId): void
    {
        if (! $driverId) {
            return;
        }

        $driver = Driver::find($driverId);
        if (! $driver || $driver->status !== 'active') {
            throw new DomainException('That driver is not active.');
        }
        if ($driver->isLicenseExpired()) {
            throw new DomainException(trim($driver->firstname . ' ' . $driver->lastname) . "'s Driver's License expired on " . $driver->license_expiration_date->format('M d, Y') . '. Choose another driver.');
        }
    }

    /**
     * @param  array{vehicle_id: int, driver_id?: ?int, start_at: string, end_at: string, purpose: string, destination: string, origin?: ?string, passengers?: ?string}  $data
     *
     * @throws DomainException when a business rule blocks the request
     */
    public function submit(User $user, array $data): VehicleRequest
    {
        if (! self::canRequest($user)) {
            throw new DomainException('Your account type cannot request vehicles.');
        }

        $vehicle = self::bookableVehicles($user)->find($data['vehicle_id']);
        if (! $vehicle) {
            throw new DomainException('That vehicle is not available for requests (it must be Serviceable and inside your scope).');
        }

        // Times are stored exactly as entered (Philippine wall-clock time), so
        // the calendar shows what the requester typed; only "today" needs the zone.
        $start = Carbon::parse($data['start_at']);
        $end = Carbon::parse($data['end_at']);

        if ($end->lessThanOrEqualTo($start)) {
            throw new DomainException('The end time must be after the start time.');
        }
        if ($start->toDateString() < now(self::TIMEZONE)->toDateString()) {
            throw new DomainException('The start date cannot be in the past.');
        }

        $driverId = ! empty($data['driver_id']) ? (int) $data['driver_id'] : null;
        $this->assertDriverUsable($driverId);

        $conflicts = $this->conflicts($vehicle->id, $driverId, $start, $end);
        if ($conflicts->isNotEmpty()) {
            throw new DomainException($this->conflictMessage($conflicts, $vehicle->id));
        }

        $request = VehicleRequest::create([
            'vehicle_id'     => $vehicle->id,
            'driver_id'      => $driverId,
            'start_at'       => $start,
            'end_at'         => $end,
            'purpose'        => $data['purpose'],
            'origin'         => $data['origin'] ?? null,
            'destination'    => $data['destination'],
            'passengers'     => $data['passengers'] ?? null,
            'requested_by'   => $user->id,
            'requester_role' => ApprovalService::role($user),
            'status'         => VehicleRequest::STATUS_PENDING,
        ]);

        ActivityLog::record('created', 'Dispatch', 'Requested [' . strtoupper($vehicle->plate_number) . '] from ' . $start->format('M d, Y h:i A') . ' to ' . $end->format('M d, Y h:i A') . ' (' . $data['purpose'] . ').', $request);

        // The top role has nobody above them: their own request is approved on the spot.
        if (ApprovalService::role($user) === 'SUPER ADMINISTRATOR') {
            $request->update([
                'status' => VehicleRequest::STATUS_APPROVED, 'decided_by' => $user->id, 'decided_at' => now(),
            ]);
            ActivityLog::record('updated', 'Dispatch', 'Auto-approved request #' . $request->id . ' (Super Administrator).', $request);
        }

        return $request;
    }

    /** @throws DomainException when the approver may not, or the booking no longer works */
    public function approve(VehicleRequest $request, User $approver, ?int $vehicleId = null, ?int $driverId = null, ?string $note = null): VehicleRequest
    {
        if (! self::canDecide($approver, $request)) {
            abort(403, 'You cannot decide this request.');
        }

        $vehicle = $request->vehicle;
        if ($vehicleId && $vehicleId !== $request->vehicle_id) {
            $vehicle = self::bookableVehicles($approver)->find($vehicleId);
            if (! $vehicle) {
                throw new DomainException('That vehicle is not available (it must be Serviceable and inside your scope).');
            }
        } elseif (! $vehicle || $vehicle->status !== 'SERVICEABLE') {
            throw new DomainException('This vehicle is no longer Serviceable. Pick another vehicle.');
        }

        $driverId = $driverId ?: $request->driver_id;
        $this->assertDriverUsable($driverId);

        $conflicts = $this->conflicts($vehicle->id, $driverId, $request->start_at, $request->end_at, $request->id);
        if ($conflicts->isNotEmpty()) {
            throw new DomainException($this->conflictMessage($conflicts, $vehicle->id));
        }

        $request->update([
            'vehicle_id' => $vehicle->id, 'driver_id' => $driverId,
            'status' => VehicleRequest::STATUS_APPROVED, 'decided_by' => $approver->id, 'decided_at' => now(),
            'decision_note' => $note ? mb_substr($note, 0, 255) : null,
        ]);

        ActivityLog::record('updated', 'Dispatch', 'Approved request #' . $request->id . ' for [' . strtoupper($vehicle->plate_number) . '].', $request);

        return $request;
    }

    public function reject(VehicleRequest $request, User $approver, ?string $note = null): void
    {
        if (! self::canDecide($approver, $request)) {
            abort(403, 'You cannot decide this request.');
        }

        $request->update([
            'status' => VehicleRequest::STATUS_REJECTED, 'decided_by' => $approver->id, 'decided_at' => now(),
            'decision_note' => $note ? mb_substr($note, 0, 255) : null,
        ]);

        ActivityLog::record('updated', 'Dispatch', 'Rejected request #' . $request->id . '.', $request);
    }

    /** The requester withdraws a pending request, or an approver/requester releases an approved booking. */
    public function cancel(VehicleRequest $request, User $user): void
    {
        $own = $request->requested_by === $user->id;
        $canRelease = $request->status === VehicleRequest::STATUS_APPROVED && self::approverRank($user) >= 2;

        if (! in_array($request->status, [VehicleRequest::STATUS_PENDING, VehicleRequest::STATUS_APPROVED], true)
            || ! ($own || $canRelease)) {
            abort(403, 'You cannot cancel this request.');
        }

        $request->update(['status' => VehicleRequest::STATUS_CANCELLED, 'decided_by' => $user->id, 'decided_at' => now()]);

        ActivityLog::record('updated', 'Dispatch', 'Cancelled request #' . $request->id . '.', $request);
    }

    protected static function approverRank(User $user): int
    {
        return ApprovalService::rank(ApprovalService::role($user));
    }
}
