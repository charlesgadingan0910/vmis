<?php

namespace App\Services;

use App\Http\Controllers\DriverAssignmentController;
use App\Http\Controllers\DriverParController;
use App\Http\Controllers\VehicleController;
use App\Models\ActivityLog;
use App\Models\ChangeRequest;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * P2-01 — approval workflow for driver assignment changes and vehicle
 * disposal.
 *
 * - SUPER ADMINISTRATOR acts directly (top of the chain; everything is logged).
 * - ADMINISTRATOR, UNIT ADMINISTRATOR and STATION ADMINISTRATOR do not change
 *   anything directly: the controller calls hold(), the change is stored as a
 *   ChangeRequest and NOTHING is applied yet.
 * - A user of a HIGHER role approves (a Unit Administrator only within their
 *   own unit). Approving replays the stored request through the very same
 *   controller action, run as the approver, so every existing rule (license
 *   expiry, scope, history rows, activity log) applies exactly as before.
 */
class ApprovalService
{
    /** True while an approved request is being replayed, so it is not held again. */
    public static bool $replaying = false;

    protected const RANKS = [
        'STATION ADMINISTRATOR' => 1,
        'UNIT ADMINISTRATOR'    => 2,
        'ADMINISTRATOR'         => 3,
        'SUPER ADMINISTRATOR'   => 4,
    ];

    public static function role(User $user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }

    public static function rank(string $role): int
    {
        return self::RANKS[strtoupper(trim($role))] ?? 0;
    }

    /** Do this user's changes need a second person's approval? */
    public static function requiresApproval(User $user): bool
    {
        if (self::$replaying) {
            return false;
        }

        $rank = self::rank(self::role($user));

        return $rank > 0 && $rank < 4;
    }

    /** May $user approve or reject this request? */
    public static function canApprove(User $user, ChangeRequest $request): bool
    {
        if (! $request->isPending()) {
            return false;
        }

        $role = self::role($user);
        $rank = self::rank($role);

        if ($rank < 2 || $rank <= self::rank($request->requester_role)) {
            return false;
        }

        if ($role === 'UNIT ADMINISTRATOR') {
            return $request->vehicle && $user->unit_id && $request->vehicle->unit_id == $user->unit_id;
        }

        return true;
    }

    /** Pending requests waiting on this user. @return Collection<int, ChangeRequest> */
    public static function pendingFor(User $user): Collection
    {
        if (self::rank(self::role($user)) < 2) {
            return collect();
        }

        return ChangeRequest::with('vehicle')
            ->where('status', ChangeRequest::STATUS_PENDING)
            ->get()
            ->filter(fn (ChangeRequest $r) => self::canApprove($user, $r))
            ->values();
    }

    /**
     * Called by a controller right before it would make a sensitive change.
     * Returns null when the change may go ahead now, or a JSON response (the
     * request was stored for approval) that the controller must return instead.
     *
     * @param  array<string, mixed>  $payload  the original request input, replayed on approval
     */
    public function hold(string $type, string $category, ?Vehicle $vehicle, ?Driver $driver, string $summary, array $payload): ?JsonResponse
    {
        $user = auth()->user();

        if (! $user || ! self::requiresApproval($user)) {
            return null;
        }

        if ($vehicle && ChangeRequest::where('vehicle_id', $vehicle->id)
            ->where('category', $category)
            ->where('status', ChangeRequest::STATUS_PENDING)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'There is already a ' . $category . ' request for this vehicle waiting for approval. Wait for it to be decided, or cancel it under Approvals.',
            ], 422);
        }

        $request = ChangeRequest::create([
            'type'           => $type,
            'category'       => $category,
            'vehicle_id'     => $vehicle?->id,
            'driver_id'      => $driver?->id,
            'summary'        => mb_substr($summary, 0, 255),
            'payload'        => $payload,
            'requested_by'   => $user->id,
            'requester_role' => self::role($user),
            'status'         => ChangeRequest::STATUS_PENDING,
        ]);

        ActivityLog::record('created', 'Approval', 'Requested approval: ' . $summary, $request);

        return response()->json([
            'success' => true,
            'pending' => true,
            'message' => 'Sent for approval — a higher-level administrator must approve this before it takes effect. Follow it under Approvals.',
        ]);
    }

    /** @return array{ok: bool, message: string} */
    public function approve(ChangeRequest $request, User $approver, ?string $note = null): array
    {
        if (! self::canApprove($approver, $request)) {
            abort(403, 'You cannot approve this request.');
        }

        $result = $this->replay($request, $approver);

        if (! $result['ok']) {
            return $result;
        }

        $request->update([
            'status'         => ChangeRequest::STATUS_APPROVED,
            'decided_by'     => $approver->id,
            'decided_at'     => now(),
            'decision_note'  => $note ? mb_substr($note, 0, 255) : null,
            'result_message' => mb_substr($result['message'], 0, 255),
        ]);

        ActivityLog::record(
            'updated',
            'Approval',
            'Approved request #' . $request->id . ' (' . $request->summary . ') requested by ' . $this->requesterLabel($request) . '.',
            $request
        );

        return $result;
    }

    public function reject(ChangeRequest $request, User $approver, ?string $note = null): void
    {
        if (! self::canApprove($approver, $request)) {
            abort(403, 'You cannot reject this request.');
        }

        $request->update([
            'status'        => ChangeRequest::STATUS_REJECTED,
            'decided_by'    => $approver->id,
            'decided_at'    => now(),
            'decision_note' => $note ? mb_substr($note, 0, 255) : null,
        ]);

        ActivityLog::record(
            'updated',
            'Approval',
            'Rejected request #' . $request->id . ' (' . $request->summary . ') requested by ' . $this->requesterLabel($request) . '.',
            $request
        );
    }

    /** The requester withdraws their own pending request. */
    public function cancel(ChangeRequest $request, User $user): void
    {
        if (! $request->isPending() || $request->requested_by !== $user->id) {
            abort(403, 'Only the person who made a pending request can cancel it.');
        }

        $request->update([
            'status'     => ChangeRequest::STATUS_CANCELLED,
            'decided_by' => $user->id,
            'decided_at' => now(),
        ]);

        ActivityLog::record('updated', 'Approval', 'Cancelled request #' . $request->id . ' (' . $request->summary . ').', $request);
    }

    protected function requesterLabel(ChangeRequest $request): string
    {
        return $request->requester ? ActivityLog::actorLabel($request->requester) : 'a former user';
    }

    /**
     * Runs the stored request through the original controller action as the
     * approver. Nothing is marked approved unless the action itself succeeded.
     *
     * @return array{ok: bool, message: string}
     */
    protected function replay(ChangeRequest $change, User $approver): array
    {
        $vehicle = $change->vehicle_id ? Vehicle::find($change->vehicle_id) : null;
        $driver = $change->driver_id ? Driver::find($change->driver_id) : null;

        $targets = [
            'vehicle.update'    => [VehicleController::class, 'update', ['vehicle' => $vehicle]],
            'vehicle.destroy'   => [VehicleController::class, 'destroy', ['id' => $change->vehicle_id]],
            'assignment.assign' => [DriverAssignmentController::class, 'assign', ['vehicle' => $vehicle]],
            'assignment.unassign' => [DriverAssignmentController::class, 'unassign', ['vehicle' => $vehicle]],
            'driver.assign'     => [DriverAssignmentController::class, 'assignToDriver', ['driver' => $driver]],
            'driver.unassign'   => [DriverAssignmentController::class, 'unassignDriver', ['driver' => $driver]],
            'par.link'          => [DriverParController::class, 'link', ['driver' => $driver]],
        ];

        if (! isset($targets[$change->type])) {
            return ['ok' => false, 'message' => 'Unknown request type.'];
        }

        [$controller, $method, $parameters] = $targets[$change->type];

        foreach ($parameters as $name => $value) {
            if ($value === null || $value === '') {
                return ['ok' => false, 'message' => 'The vehicle or driver in this request no longer exists.'];
            }
        }

        $payload = $change->payload ?? [];
        // A Unit/Station Administrator's edit form has no unit field, but Administrators require one.
        if ($change->type === 'vehicle.update' && $vehicle && ! array_key_exists('unit_id', $payload)) {
            $payload['unit_id'] = $vehicle->unit_id;
        }

        $replayed = Request::create('/approval-replay', 'POST', $payload);
        $replayed->setUserResolver(fn () => $approver);

        self::$replaying = true;

        try {
            $response = app()->call([app($controller), $method], $parameters + ['request' => $replayed]);
        } catch (ValidationException $e) {
            return ['ok' => false, 'message' => (string) collect($e->errors())->flatten()->first()];
        } catch (HttpExceptionInterface $e) {
            return ['ok' => false, 'message' => $e->getMessage() ?: 'You are not allowed to apply this change.'];
        } catch (ModelNotFoundException) {
            return ['ok' => false, 'message' => 'The vehicle or driver in this request no longer exists.'];
        } finally {
            self::$replaying = false;
        }

        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            $ok = $response->getStatusCode() < 400 && ($data['success'] ?? true);

            return ['ok' => (bool) $ok, 'message' => (string) ($data['message'] ?? ($ok ? 'Applied.' : 'Could not apply this change.'))];
        }

        if ($response instanceof RedirectResponse) {
            // vehicle.destroy: success means the vehicle is gone
            $gone = $change->vehicle_id && ! Vehicle::whereKey($change->vehicle_id)->exists();

            return ['ok' => $gone, 'message' => $gone ? 'Vehicle record deleted.' : 'Could not apply this change.'];
        }

        return ['ok' => false, 'message' => 'Could not apply this change.'];
    }
}
