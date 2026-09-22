<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AccountType;
use App\Models\Rank;
use App\Models\Unit;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    // Mirrors the role constants/pattern already used by VehicleController,
    // MaintenanceController and DashboardController.
    protected const ROLE_SUPER_ADMIN   = 'SUPER ADMINISTRATOR';
    protected const ROLE_ADMIN         = 'ADMINISTRATOR';
    protected const ROLE_UNIT_ADMIN    = 'UNIT ADMINISTRATOR';
    protected const ROLE_STATION_ADMIN = 'STATION ADMINISTRATOR';
    protected const ROLE_VIEWER        = 'VIEWER';

    protected function role($user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }

    /**
     * Which account types a role is permitted to create/edit/deactivate/reset,
     * straight from the "Users Management" spec:
     *   SUPER ADMINISTRATOR   -> everyone
     *   ADMINISTRATOR         -> everyone except SUPER ADMINISTRATOR (spec's own explicit list)
     *   UNIT ADMINISTRATOR    -> spec only restricts these two by location, not by type;
     *                            capped here at UNIT ADMINISTRATOR/STATION ADMINISTRATOR/VIEWER
     *                            so a Unit Administrator can never mint an ADMINISTRATOR or
     *                            SUPER ADMINISTRATOR account — a deliberate safety extension
     *                            beyond the literal doc text, flagged to the user.
     *   STATION ADMINISTRATOR -> STATION ADMINISTRATOR/VIEWER only, same reasoning.
     *   VIEWER                -> none (read-only, per spec's silence on VIEWER permissions).
     */
    protected function manageableAccountTypes(string $role): array
    {
        return match ($role) {
            self::ROLE_SUPER_ADMIN => [
                self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_UNIT_ADMIN,
                self::ROLE_STATION_ADMIN, self::ROLE_VIEWER,
            ],
            self::ROLE_ADMIN => [
                self::ROLE_ADMIN, self::ROLE_UNIT_ADMIN, self::ROLE_STATION_ADMIN, self::ROLE_VIEWER,
            ],
            self::ROLE_UNIT_ADMIN => [
                self::ROLE_UNIT_ADMIN, self::ROLE_STATION_ADMIN, self::ROLE_VIEWER,
            ],
            self::ROLE_STATION_ADMIN => [
                self::ROLE_STATION_ADMIN, self::ROLE_VIEWER,
            ],
            default => [],
        };
    }

    /**
     * Station IDs that belong to a given unit (stations.unit_id), used to
     * implement the spec's "own Unit and Stations under it" scope for a
     * Unit Administrator.
     */
    protected function stationIdsUnderUnit(?int $unitId): array
    {
        if (!$unitId) {
            return [];
        }

        return Station::where('unit_id', $unitId)->pluck('id')->all();
    }

    /**
     * Does $role, acting from $actingUser's own unit/station, cover a record
     * located at $unitId/$stationId?
     */
    protected function locationInScope($actingUser, string $role, ?int $unitId, ?int $stationId): bool
    {
        return match ($role) {
            self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN => true, // organization-wide, no location restriction
            self::ROLE_UNIT_ADMIN => $unitId == $actingUser->unit_id
                || ($stationId && in_array($stationId, $this->stationIdsUnderUnit($actingUser->unit_id), true)),
            self::ROLE_STATION_ADMIN => $stationId == $actingUser->station_id,
            default => false,
        };
    }

    /**
     * Can $actingUser (with $role) manage this already-existing $target
     * (edit / deactivate / reset password)?
     */
    protected function canManageExisting($actingUser, string $role, User $target): bool
    {
        return in_array($this->role($target), $this->manageableAccountTypes($role), true)
            && $this->locationInScope($actingUser, $role, $target->unit_id, $target->station_id);
    }

    /**
     * Shared gate for store()/update(): is $actingUser allowed to save a user
     * record with these RESULTING values? Returns an error message, or null
     * when allowed. Checked in addition to canManageExisting() on update, so
     * a role can't use an edit to move/escalate a user out of what it could
     * have created in the first place.
     */
    protected function authorizationError($actingUser, string $role, string $accountType, ?int $unitId, ?int $stationId): ?string
    {
        $allowedTypes = $this->manageableAccountTypes($role);

        if (empty($allowedTypes)) {
            return 'Your account does not have permission to manage system users.';
        }

        if (!in_array($accountType, $allowedTypes, true)) {
            return 'You are not authorized to assign the "'.$accountType.'" account type.';
        }

        if (!$this->locationInScope($actingUser, $role, $unitId, $stationId)) {
            return 'You can only manage users within your own unit/station assignment.';
        }

        return null;
    }

    public function index(Request $request)
    {
        $authUser = Auth::user();
        $role = $this->role($authUser);

        if ($request->ajax()) {
            $query = User::query()->select([
                'id', 'account_type', 'rank', 'firstname', 'middlename', 'lastname', 'qlfr',
                'fullname', 'badge_number', 'email', 'unit_id', 'station_id',
                'is_active', 'is_online', 'created_by', 'created_at',
            ]);

            if ($role === self::ROLE_ADMIN) {
                $query->whereIn('account_type', [self::ROLE_ADMIN, self::ROLE_UNIT_ADMIN, self::ROLE_STATION_ADMIN, self::ROLE_VIEWER]);
            } elseif ($role === self::ROLE_UNIT_ADMIN) {
                // Own unit AND any station under that unit — not just a bare unit_id match.
                $stationIds = $this->stationIdsUnderUnit($authUser->unit_id);
                $query->where(function ($q) use ($authUser, $stationIds) {
                    $q->where('unit_id', $authUser->unit_id);
                    if (!empty($stationIds)) {
                        $q->orWhereIn('station_id', $stationIds);
                    }
                });
            } elseif ($role === self::ROLE_STATION_ADMIN) {
                $query->where('station_id', $authUser->station_id);
            }
            // Super Admin & Viewer: unfiltered.

            $recordsTotal = $query->count();

            if ($request->filled('account_type')) {
                $query->where('account_type', $request->account_type);
            }

            if ($request->filled('status')) {
                if ($request->status === 'Active') {
                    $query->where('is_active', '1');
                } elseif ($request->status === 'Inactive') {
                    $query->where('is_active', '0');
                }
            }

            if ($request->filled('search.value')) {
                $search = $request->input('search.value');
                $query->where(function ($q) use ($search) {
                    $q->where('firstname', 'like', "%{$search}%")
                      ->orWhere('lastname', 'like', "%{$search}%")
                      ->orWhere('badge_number', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $statsQuery = clone $query;
            $stats = [
                'total' => (clone $statsQuery)->count(),
                'active' => (clone $statsQuery)->where('is_active', '1')->count(),
                'inactive' => (clone $statsQuery)->where('is_active', '0')->count(),
                'admins' => (clone $statsQuery)->whereIn('account_type', ['ADMINISTRATOR', 'SUPER ADMINISTRATOR'])->count(),
            ];

            $recordsFiltered = $stats['total'];

            if ($request->has('order')) {
                $orderColumnIndex = $request->input('order.0.column');
                $orderDirection = strtolower((string) $request->input('order.0.dir'));
                $orderDirection = in_array($orderDirection, ['asc', 'desc'], true) ? $orderDirection : 'asc';
                $columns = ['rank', 'firstname', 'account_type', 'badge_number', 'unit_id', 'is_active', 'is_online', 'id'];
                $orderColumn = $columns[$orderColumnIndex] ?? 'id';
                $query->orderBy($orderColumn, $orderDirection);
            } else {
                $query->latest();
            }

            // Always paginate server-side, regardless of what the client sends —
            // no length=-1 / missing-start path that can trigger an unbounded get().
            $start = max(0, (int) $request->input('start', 0));
            $length = (int) $request->input('length', 25);
            if ($length < 1 || $length > 100) {
                $length = 25;
            }
            $query->skip($start)->take($length);

            $users = $query->get();

            // Built once for this page of results rather than resolved per row.
            $unitNames = Unit::pluck('unit_name', 'id');
            $stationNames = Station::pluck('station_name', 'id');
            $creatorIds = $users->pluck('created_by')->filter()->unique();
            $creatorNames = $creatorIds->isEmpty()
                ? collect()
                : User::whereIn('id', $creatorIds)->get()->mapWithKeys(fn ($u) => [$u->id => $u->fullname ?: trim($u->firstname.' '.$u->lastname)]);

            $data = $users->map(function ($u) use ($authUser, $role, $unitNames, $stationNames, $creatorNames) {
                $initials = strtoupper(substr($u->firstname, 0, 1) . substr($u->lastname, 0, 1));
                $fullname = $u->fullname ?: trim($u->firstname . ' ' . $u->lastname);
                $email = $u->email ?: 'No email provided';

                $unitStr = $u->unit_id ? ($unitNames[$u->unit_id] ?? 'Unit #'.$u->unit_id) : 'N/A';
                $stationStr = $u->station_id ? ($stationNames[$u->station_id] ?? 'Station #'.$u->station_id) : 'N/A';

                $badgeClass = match (strtoupper($u->account_type)) {
                    'SUPER ADMINISTRATOR' => 'badge-danger',
                    'ADMINISTRATOR'       => 'badge-warning text-dark',
                    'UNIT ADMINISTRATOR'  => 'badge-primary',
                    'STATION ADMINISTRATOR' => 'badge-info',
                    'VIEWER'              => 'badge-secondary',
                    default               => 'badge-dark'
                };

                $statusHtml = $u->is_active == '1'
                    ? '<span class="status-pill status-active"><div class="dot"></div> Active</span>'
                    : '<span class="status-pill status-inactive"><div class="dot"></div> Inactive</span>';

                $connectionHtml = $u->is_online == '1'
                    ? '<span class="badge badge-success px-2 py-1"><i class="fas fa-wifi mr-1"></i> Online</span>'
                    : '<span class="badge badge-light border text-muted px-2 py-1"><i class="fas fa-globe mr-1"></i> Offline</span>';

                $creatorLine = '';
                if ($u->created_by && $creatorNames->has($u->created_by)) {
                    $creatorLine = '<div class="user-sub-info text-muted" style="font-size:11px;"><i class="fas fa-user-plus mr-1"></i>Added by '.e($creatorNames[$u->created_by]).'</div>';
                }

                $isSelf = $u->id === $authUser->id;
                $canManage = $this->canManageExisting($authUser, $role, $u);

                $buttons = '';
                if ($canManage) {
                    $buttons .= '<button class="btn btn-sm btn-light text-primary shadow-sm btn-edit-user" data-id="'.$u->id.'" title="Edit Profile"><i class="fas fa-edit"></i></button>';
                    $buttons .= '<button class="btn btn-sm btn-light text-warning shadow-sm mx-1 btn-reset-password" data-id="'.$u->id.'" title="Reset Password"><i class="fas fa-key"></i></button>';
                    if (!$isSelf) {
                        $buttons .= '<button class="btn btn-sm btn-light text-danger shadow-sm btn-delete-user" data-id="'.$u->id.'" title="Deactivate User"><i class="fas fa-trash"></i></button>';
                    }
                }
                $actionsHtml = $buttons !== ''
                    ? '<div class="btn-group">'.$buttons.'</div>'
                    : '<span class="text-muted small">—</span>';

                return [
                    'rank' => '<strong>'.e($u->rank).'</strong>',
                    'profile' => '
                        <div class="d-flex align-items-center gap-3">
                            <div class="user-avatar-circle mr-3">'.e($initials).'</div>
                            <div>
                                <div class="user-main-name">'.e($fullname).'</div>
                                <div class="user-sub-info">'.e($email).'</div>
                                '.$creatorLine.'
                            </div>
                        </div>',
                    'account_type' => '<span class="badge '.$badgeClass.'" style="font-size: 11px; padding: 6px 10px;">'.e($u->account_type).'</span>',
                    'badge_number' => '<span style="font-family: monospace; font-weight: 700;">'.e($u->badge_number).'</span>',
                    'unit_station' => '<small class="d-block font-weight-bold" style="color: #334155;">'.e($unitStr).'</small><small class="text-muted">'.e($stationStr).'</small>',
                    'status' => $statusHtml,
                    'connection' => '<div class="text-center">'.$connectionHtml.'</div>',
                    'actions' => '<div class="text-center">'.$actionsHtml.'</div>',
                ];
            });

            return response()->json([
                'draw' => intval($request->draw),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
                'stats' => $stats
            ]);
        }

        $allowedTypes = $this->manageableAccountTypes($role);

        $accountTypes = AccountType::orderBy('level')->get();
        if (!empty($allowedTypes)) {
            $accountTypes = $accountTypes->whereIn('type', $allowedTypes)->values();
        }

        $ranks = Rank::all();

        // Unit/Station pickers in the Add/Edit modals are scoped the same way
        // as write access, so a scoped admin physically cannot select an
        // out-of-scope assignment (the controller re-checks this regardless).
        if ($role === self::ROLE_UNIT_ADMIN) {
            $units = Unit::where('id', $authUser->unit_id)->get();
            $stations = Station::where('unit_id', $authUser->unit_id)->get();
        } elseif ($role === self::ROLE_STATION_ADMIN) {
            $units = Unit::where('id', $authUser->unit_id)->get();
            $stations = Station::where('id', $authUser->station_id)->get();
        } else {
            $units = Unit::all();
            $stations = Station::all();
        }

        return view('users.index', compact('accountTypes', 'ranks', 'units', 'stations', 'allowedTypes'));
    }

    public function store(Request $request)
    {
        $authUser = Auth::user();
        $role = $this->role($authUser);

        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string|max:255',
            'middlename' => 'nullable|string|max:255',
            'lastname' => 'required|string|max:255',
            'qlfr' => 'nullable|string|max:50',
            'email' => 'required|email|max:255',
            'badge_number' => 'required|string|max:255',
            'account_type' => 'required|string|exists:account_types,type',
            'rank' => 'nullable|string|exists:ranks,rank_abbvr',
            'unit_id' => 'nullable|integer|exists:units,id',
            'station_id' => 'nullable|integer|exists:stations,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $accountType = strtoupper(trim($request->account_type));
        $unitId = $request->filled('unit_id') ? (int) $request->unit_id : null;
        $stationId = $request->filled('station_id') ? (int) $request->station_id : null;

        if ($error = $this->authorizationError($authUser, $role, $accountType, $unitId, $stationId)) {
            return response()->json(['success' => false, 'message' => $error]);
        }

        $fields = [
            'account_type' => $accountType,
            'rank' => $request->rank,
            'firstname' => $request->firstname,
            'middlename' => $request->middlename,
            'lastname' => $request->lastname,
            'qlfr' => $request->qlfr,
            'badge_number' => $request->badge_number,
            'email' => $request->email,
            'unit_id' => $unitId,
            'station_id' => $stationId,
            'fullname' => trim($request->firstname . ' ' . $request->middlename . ' ' . $request->lastname . ' ' . $request->qlfr),
        ];

        $existingUser = User::where('badge_number', $request->badge_number)
            ->when($request->email, function ($q) use ($request) {
                return $q->orWhere('email', $request->email);
            })->first();

        if ($existingUser) {
            if ($existingUser->is_active == "1") {
                return response()->json([
                    'success' => false,
                    'clear_form' => true,
                    'message' => 'Cannot save record. An active user with this badge number or email already exists.'
                ]);
            }

            $fields['is_active'] = "1";
            $fields['created_by'] = $authUser->id;
            $existingUser->update($fields);

            return response()->json([
                'success' => true,
                'message' => 'An inactive account was found and successfully updated/reactivated.'
            ]);
        }

        $fields['password'] = Hash::make('P@ssw0rd12345');
        $fields['is_active'] = "1";
        $fields['is_online'] = "0";
        $fields['is_password_changed'] = "0";
        $fields['created_by'] = $authUser->id;

        User::create($fields);

        return response()->json(['success' => true, 'message' => 'User account created successfully.']);
    }

    public function editData(User $user)
    {
        $authUser = Auth::user();
        $role = $this->role($authUser);

        if (!$this->canManageExisting($authUser, $role, $user)) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to view this user.'], 403);
        }

        return response()->json(['success' => true, 'data' => $user]);
    }

    public function update(Request $request, User $user)
    {
        $authUser = Auth::user();
        $role = $this->role($authUser);

        if (!$this->canManageExisting($authUser, $role, $user)) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to edit this user.']);
        }

        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string|max:255',
            'middlename' => 'nullable|string|max:255',
            'lastname' => 'required|string|max:255',
            'qlfr' => 'nullable|string|max:50',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'badge_number' => 'required|string|max:255|unique:users,badge_number,'.$user->id,
            'account_type' => 'required|string|exists:account_types,type',
            'rank' => 'nullable|string|exists:ranks,rank_abbvr',
            'unit_id' => 'nullable|integer|exists:units,id',
            'station_id' => 'nullable|integer|exists:stations,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $accountType = strtoupper(trim($request->account_type));
        $unitId = $request->filled('unit_id') ? (int) $request->unit_id : null;
        $stationId = $request->filled('station_id') ? (int) $request->station_id : null;

        if ($error = $this->authorizationError($authUser, $role, $accountType, $unitId, $stationId)) {
            return response()->json(['success' => false, 'message' => $error]);
        }

        $data = [
            'account_type' => $accountType,
            'rank' => $request->rank,
            'firstname' => $request->firstname,
            'middlename' => $request->middlename,
            'lastname' => $request->lastname,
            'qlfr' => $request->qlfr,
            'badge_number' => $request->badge_number,
            'email' => $request->email,
            'unit_id' => $unitId,
            'station_id' => $stationId,
            'fullname' => trim($request->firstname . ' ' . $request->middlename . ' ' . $request->lastname . ' ' . $request->qlfr),
        ];

        $user->update($data);

        return response()->json(['success' => true, 'message' => 'User account updated successfully.']);
    }

    public function destroy(User $user)
    {
        $authUser = Auth::user();
        $role = $this->role($authUser);

        if ($user->id === $authUser->id) {
            return response()->json(['success' => false, 'message' => 'You cannot deactivate your own account.']);
        }

        if (!$this->canManageExisting($authUser, $role, $user)) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to deactivate this user.']);
        }

        $user->is_active = "0";
        $user->save();

        return response()->json(['success' => true, 'message' => 'User account deactivated successfully.']);
    }

    public function resetPassword(Request $request, User $user)
    {
        $authUser = Auth::user();
        $role = $this->role($authUser);

        if (!$this->canManageExisting($authUser, $role, $user)) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to reset this user\'s password.']);
        }

        if (!Hash::check($request->admin_password, $authUser->password)) {
            return response()->json(['success' => false, 'message' => 'Authentication failed. Incorrect admin password.']);
        }

        $user->password = Hash::make('P@ssw0rd12345');
        $user->is_password_changed = "0";
        $user->save();

        return response()->json(['success' => true, 'message' => 'User password has been reset to default.']);
    }
}
