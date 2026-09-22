<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Station;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Every account type can reach its own profile — there is no role
     * restriction here at all, unlike Units & Stations or Activity Logs.
     * The only access rule that matters on every action below is implicit:
     * everything reads/writes Auth::user() and nothing else, so there's
     * nothing for a role check to gate.
     */
    public function index(): View
    {
        $user = Auth::user();

        $unit = $user->unit_id ? Unit::find($user->unit_id) : null;
        $station = $user->station_id ? Station::find($user->station_id) : null;

        return view('profile.index', compact('user', 'unit', 'station'));
    }

    /**
     * Personal-info fields only — badge number, rank, account type, unit and
     * station are administratively assigned and stay read-only here; a user
     * changing their own role or unit would be a privilege-escalation bug
     * hiding as a convenience feature, so those simply aren't accepted here.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'firstname'  => ['required', 'string', 'max:255'],
            'middlename' => ['nullable', 'string', 'max:255'],
            'lastname'   => ['required', 'string', 'max:255'],
            'qlfr'       => ['nullable', 'string', 'max:50'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $validated['fullname'] = trim($validated['firstname'] . ' ' . $validated['middlename'] . ' ' . $validated['lastname'] . ' ' . $validated['qlfr']);

        $before = $user->getOriginal();
        $user->update($validated);
        $changed = $user->getChanges();
        unset($changed['updated_at']);

        if (!empty($changed)) {
            ActivityLog::record(
                'updated',
                'User',
                'Updated own profile information.',
                $user,
                ['before' => Arr::only($before, array_keys($changed)), 'after' => $changed]
            );
        }

        return redirect()->route('profile.index')->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'      => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'new_password.different' => 'Your new password must be different from your current password.',
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            return redirect()->route('profile.index')->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        $user->password = Hash::make($validated['new_password']);
        $user->is_password_changed = '1';
        $user->save();

        // Deliberately no ['changes' => ...] payload here — a password
        // change is exactly the one kind of update an audit trail should
        // never carry the before/after value of, even hashed.
        ActivityLog::record('updated', 'User', 'Changed own account password.', $user);

        return redirect()->route('profile.index')->with('success', 'Password changed successfully.');
    }

    /**
     * Server-side AJAX feed for the "My Activity" table — scoped to the
     * logged-in user's own entries only (indexed on activity_logs.user_id)
     * and always paginated via skip/take, so this stays fast and light
     * regardless of whether the account has a dozen log entries or
     * hundreds of thousands: the browser only ever receives one page's
     * worth of rows, never the full history.
     */
    public function activity(Request $request): JsonResponse
    {
        $userId = Auth::id();

        $query = ActivityLog::where('user_id', $userId);

        if ($search = trim((string) $request->input('search.value'))) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('module', 'like', "%{$search}%");
            });
        }

        $recordsTotal = ActivityLog::where('user_id', $userId)->count();
        $recordsFiltered = (clone $query)->count();

        if ($request->has('order')) {
            $orderColumnIndex = $request->input('order.0.column');
            $orderDirection = strtolower((string) $request->input('order.0.dir'));
            $orderDirection = in_array($orderDirection, ['asc', 'desc'], true) ? $orderDirection : 'desc';
            $columns = ['created_at', 'action', 'module', 'description', 'id'];
            $orderColumn = $columns[$orderColumnIndex] ?? 'created_at';
            $query->orderBy($orderColumn, $orderDirection);
        } else {
            $query->latest('created_at');
        }

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        if ($length < 1 || $length > 100) {
            $length = 10;
        }
        $query->skip($start)->take($length);

        $logs = $query->get();

        $actionMeta = [
            'created'      => ['label' => 'Created', 'class' => 'badge-success'],
            'updated'      => ['label' => 'Updated', 'class' => 'badge-primary'],
            'deleted'      => ['label' => 'Deleted', 'class' => 'badge-danger'],
            'login'        => ['label' => 'Login', 'class' => 'badge-info'],
            'logout'       => ['label' => 'Logout', 'class' => 'badge-secondary'],
            'login_failed' => ['label' => 'Failed Login', 'class' => 'badge-warning text-dark'],
        ];

        $data = $logs->map(function ($log) use ($actionMeta) {
            $meta = $actionMeta[$log->action] ?? ['label' => ucfirst($log->action), 'class' => 'badge-dark'];
            $hasDetails = !empty($log->changes);

            return [
                'when'        => '<div class="log-when">' . e($log->created_at->format('M d, Y')) . '</div><div class="text-muted small">' . e($log->created_at->format('h:i A')) . '</div>',
                'action'      => '<span class="badge ' . $meta['class'] . '" style="font-size:11px;padding:6px 10px;">' . e($meta['label']) . '</span>',
                'module'      => '<span class="badge badge-light border">' . e($log->module) . '</span>',
                'description' => '<div class="log-description">' . e($log->description) . '</div>',
                'details'     => $hasDetails
                    ? '<button class="btn btn-sm btn-light border btn-view-log-details" data-id="' . $log->id . '"><i class="fas fa-eye"></i> View</button>'
                    : '<span class="text-muted small">—</span>',
            ];
        });

        return response()->json([
            'draw'            => intval($request->input('draw')),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    /**
     * Raw before/after payload for the details modal — scoped to the
     * logged-in user's own log entries so nobody can page through someone
     * else's audit history just by guessing an id in the URL.
     */
    public function showActivity(ActivityLog $activityLog): JsonResponse
    {
        if ((int) $activityLog->user_id !== (int) Auth::id()) {
            abort(403, 'You are not authorized to view this log entry.');
        }

        return response()->json([
            'success'     => true,
            'description' => $activityLog->description,
            'ip_address'  => $activityLog->ip_address,
            'created_at'  => optional($activityLog->created_at)->format('F d, Y \a\t h:i:s A'),
            'changes'     => $activityLog->changes,
        ]);
    }
}
