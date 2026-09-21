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
    public function index(Request $request)
    {
        $authUser = Auth::user();

        if ($request->ajax()) {
            $query = User::query();

            if ($authUser->account_type === 'ADMINISTRATOR') {
                $query->whereIn('account_type', ['ADMINISTRATOR', 'UNIT ADMINISTRATOR', 'STATION ADMINISTRATOR', 'VIEWER']);
            } elseif ($authUser->account_type === 'UNIT ADMINISTRATOR') {
                $query->where('unit_id', $authUser->unit_id);
            } elseif ($authUser->account_type === 'STATION ADMINISTRATOR') {
                $query->where('station_id', $authUser->station_id);
            }

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
                $query->where(function($q) use ($search) {
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
                $orderDirection = $request->input('order.0.dir');
                $columns = ['rank', 'firstname', 'account_type', 'badge_number', 'unit_id', 'is_active', 'is_online', 'id'];
                $orderColumn = $columns[$orderColumnIndex] ?? 'id';
                $query->orderBy($orderColumn, $orderDirection);
            } else {
                $query->latest();
            }

            if ($request->has('start') && $request->length != -1) {
                $query->skip($request->start)->take($request->length);
            }

            $users = $query->get();

            $data = $users->map(function($u) {
                $initials = substr($u->firstname, 0, 1) . substr($u->lastname, 0, 1);
                $fullname = $u->fullname ?? ($u->firstname . ' ' . $u->lastname);
                $email = $u->email ?? 'No email provided';
                
                $unitStr = $u->unit_id ? 'Unit ID: '.$u->unit_id : 'N/A';
                $stationStr = $u->station_id ? 'Station ID: '.$u->station_id : 'N/A';

                $badgeClass = match (strtoupper($u->account_type)) {
                    'SUPER ADMINISTRATOR' => 'badge-danger',
                    'ADMINISTRATOR'       => 'badge-warning text-dark',
                    'UNIT ADMINISTRATOR'  => 'badge-primary',
                    'STATION ADMINISTRATOR'=> 'badge-info',
                    'VIEWER'              => 'badge-secondary',
                    default               => 'badge-dark'
                };

                $statusHtml = $u->is_active == '1'
                    ? '<span class="status-pill status-active"><div class="dot"></div> Active</span>'
                    : '<span class="status-pill status-inactive"><div class="dot"></div> Inactive</span>';

                $connectionHtml = $u->is_online == '1'
                    ? '<span class="badge badge-success px-2 py-1"><i class="fas fa-wifi mr-1"></i> Online</span>'
                    : '<span class="badge badge-light border text-muted px-2 py-1"><i class="fas fa-globe mr-1"></i> Offline</span>';

                $actionsHtml = '<div class="btn-group">
                    <button class="btn btn-sm btn-light text-primary shadow-sm btn-edit-user" data-id="'.$u->id.'" title="Edit Profile"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-light text-warning shadow-sm mx-1 btn-reset-password" data-id="'.$u->id.'" title="Reset Password"><i class="fas fa-key"></i></button>
                    <button class="btn btn-sm btn-light text-danger shadow-sm btn-delete-user" data-id="'.$u->id.'" title="Deactivate User"><i class="fas fa-trash"></i></button>
                </div>';

                return [
                    'rank' => '<strong>'.$u->rank.'</strong>',
                    'profile' => '
                        <div class="d-flex align-items-center gap-3">
                            <div class="user-avatar-circle mr-3">'.$initials.'</div>
                            <div>
                                <div class="user-main-name">'.$fullname.'</div>
                                <div class="user-sub-info">'.$email.'</div>
                            </div>
                        </div>',
                    'account_type' => '<span class="badge '.$badgeClass.'" style="font-size: 11px; padding: 6px 10px;">'.$u->account_type.'</span>',
                    'badge_number' => '<span style="font-family: monospace; font-weight: 700;">'.$u->badge_number.'</span>',
                    'unit_station' => '<small class="d-block font-weight-bold" style="color: #334155;">'.$unitStr.'</small><small class="text-muted">'.$stationStr.'</small>',
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

        $accountTypes = AccountType::orderBy('level')->get();
        $ranks = Rank::all(); // Restored to default/original order
        $units = Unit::all();
        $stations = Station::all();

        return view('users.index', compact('accountTypes', 'ranks', 'units', 'stations'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'required|email|max:255', 
            'badge_number' => 'required|string|max:255',
            'account_type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $existingUser = User::where('badge_number', $request->badge_number)
            ->when($request->email, function($q) use ($request) {
                return $q->orWhere('email', $request->email);
            })->first();

        if ($existingUser) {
            if ($existingUser->is_active == "1") {
                return response()->json([
                    'success' => false, 
                    'clear_form' => true,
                    'message' => 'Cannot save record. An active user with this badge number or email already exists.'
                ]);
            } else {
                $existingUser->update($request->except(['password']));
                $existingUser->is_active = "1";
                $existingUser->save();

                return response()->json([
                    'success' => true, 
                    'message' => 'An inactive account was found and successfully updated/reactivated.'
                ]);
            }
        }

        $data = $request->all();
        $data['password'] = Hash::make('P@ssw0rd12345');
        $data['is_active'] = "1";
        $data['is_online'] = "0";
        $data['is_password_changed'] = "0";
        $data['fullname'] = trim($request->firstname . ' ' . $request->middlename . ' ' . $request->lastname . ' ' . $request->qlfr);

        User::create($data);

        return response()->json(['success' => true, 'message' => 'User account created successfully.']);
    }

    public function editData(User $user)
    {
        return response()->json(['success' => true, 'data' => $user]);
    }

    public function update(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'badge_number' => 'required|string|max:255|unique:users,badge_number,'.$user->id,
            'account_type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        $data = $request->except(['password']);
        $data['fullname'] = trim($request->firstname . ' ' . $request->middlename . ' ' . $request->lastname . ' ' . $request->qlfr);

        $user->update($data);

        return response()->json(['success' => true, 'message' => 'User account updated successfully.']);
    }

    public function destroy(User $user)
    {
        $user->is_active = "0";
        $user->save();

        return response()->json(['success' => true, 'message' => 'User account deactivated successfully.']);
    }

    public function resetPassword(Request $request, User $user)
    {
        if (!Hash::check($request->admin_password, Auth::user()->password)) {
            return response()->json(['success' => false, 'message' => 'Authentication failed. Incorrect admin password.']);
        }

        $user->password = Hash::make('P@ssw0rd12345');
        $user->is_password_changed = "0";
        $user->save();

        return response()->json(['success' => true, 'message' => 'User password has been reset to default.']);
    }
}