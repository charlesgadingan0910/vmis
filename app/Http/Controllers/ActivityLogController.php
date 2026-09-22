<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    protected const ROLE_SUPER_ADMIN = 'SUPER ADMINISTRATOR';

    protected function role($user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }

    /**
     * Per the spec: only the Super Administrator may view the system
     * activity log at all — every other role, however senior, is blocked.
     */
    protected function authorizeAccess(): void
    {
        if ($this->role(auth()->user()) !== self::ROLE_SUPER_ADMIN) {
            abort(403, 'Only the Super Administrator can view system activity logs.');
        }
    }

    public function index(Request $request)
    {
        $this->authorizeAccess();

        if ($request->ajax()) {
            $query = ActivityLog::query();

            if ($request->filled('module')) {
                $query->where('module', $request->module);
            }

            if ($request->filled('action')) {
                $query->where('action', $request->action);
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            if ($request->filled('search.value')) {
                $search = $request->input('search.value');
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhere('user_name', 'like', "%{$search}%")
                      ->orWhere('module', 'like', "%{$search}%");
                });
            }

            // Unfiltered, system-wide totals — a monitoring page's headline
            // numbers should read the same regardless of whatever module/date
            // filter is currently selected in the toolbar.
            $today = now()->startOfDay();
            $stats = [
                'total'         => ActivityLog::count(),
                'today'         => ActivityLog::where('created_at', '>=', $today)->count(),
                'logins_today'  => ActivityLog::where('action', 'login')->where('created_at', '>=', $today)->count(),
                'failed_today'  => ActivityLog::where('action', 'login_failed')->where('created_at', '>=', $today)->count(),
            ];

            $recordsTotal = ActivityLog::count();
            $recordsFiltered = (clone $query)->count();

            if ($request->has('order')) {
                $orderColumnIndex = $request->input('order.0.column');
                $orderDirection = strtolower((string) $request->input('order.0.dir'));
                $orderDirection = in_array($orderDirection, ['asc', 'desc'], true) ? $orderDirection : 'desc';
                $columns = ['created_at', 'user_name', 'action', 'module', 'description', 'id'];
                $orderColumn = $columns[$orderColumnIndex] ?? 'created_at';
                $query->orderBy($orderColumn, $orderDirection);
            } else {
                $query->latest('created_at');
            }

            // Always paginate server-side regardless of what the client sends —
            // this table only grows, so an unbounded fetch here would get worse
            // over time, not better.
            $start = max(0, (int) $request->input('start', 0));
            $length = (int) $request->input('length', 25);
            if ($length < 1 || $length > 100) {
                $length = 25;
            }
            $query->skip($start)->take($length);

            $logs = $query->get();

            $actionMeta = [
                'created'      => ['label' => 'Created',      'class' => 'badge-success'],
                'updated'      => ['label' => 'Updated',      'class' => 'badge-primary'],
                'deleted'      => ['label' => 'Deleted',      'class' => 'badge-danger'],
                'login'        => ['label' => 'Login',        'class' => 'badge-info'],
                'logout'       => ['label' => 'Logout',       'class' => 'badge-secondary'],
                'login_failed' => ['label' => 'Failed Login', 'class' => 'badge-warning text-dark'],
            ];

            $data = $logs->map(function ($log) use ($actionMeta) {
                $meta = $actionMeta[$log->action] ?? ['label' => ucfirst($log->action), 'class' => 'badge-dark'];

                $whoHtml = $log->user_name
                    ? '<div class="user-main-name">'.e($log->user_name).'</div>'
                    : '<span class="text-muted small">System / Unauthenticated</span>';

                $hasDetails = !empty($log->changes);

                return [
                    'when' => '<div class="log-when">'.e($log->created_at->format('M d, Y')).'</div><div class="text-muted small">'.e($log->created_at->format('h:i A')).'</div>',
                    'who' => $whoHtml,
                    'action' => '<span class="badge '.$meta['class'].'" style="font-size:11px;padding:6px 10px;">'.e($meta['label']).'</span>',
                    'module' => '<span class="badge badge-light border">'.e($log->module).'</span>',
                    'description' => '<div class="log-description">'.e($log->description).'</div>',
                    'details' => $hasDetails
                        ? '<button class="btn btn-sm btn-light border btn-view-log-details" data-id="'.$log->id.'"><i class="fas fa-eye"></i> View</button>'
                        : '<span class="text-muted small">—</span>',
                ];
            });

            return response()->json([
                'draw' => intval($request->draw),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
                'stats' => $stats,
            ]);
        }

        $modules = ActivityLog::query()->select('module')->distinct()->orderBy('module')->pluck('module');
        $actors = User::orderBy('firstname')->get(['id', 'rank', 'firstname', 'lastname', 'fullname']);

        return view('activity-logs.index', compact('modules', 'actors'));
    }

    /**
     * Raw before/after payload for the "View" details modal.
     */
    public function show(ActivityLog $activityLog)
    {
        $this->authorizeAccess();

        return response()->json([
            'success' => true,
            'description' => $activityLog->description,
            'user_name' => $activityLog->user_name,
            'ip_address' => $activityLog->ip_address,
            'created_at' => optional($activityLog->created_at)->format('F d, Y \a\t h:i:s A'),
            'changes' => $activityLog->changes,
        ]);
    }
}
