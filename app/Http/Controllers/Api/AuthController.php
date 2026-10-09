<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Login/logout/profile for the VMIS mobile app. Mirrors the web
 * AuthController's badge-number-or-email + password check exactly, so an
 * existing VMIS account's credentials work unchanged in the app — but
 * issues a bearer ApiToken instead of starting a session, and only ever
 * accepts DRIVER or VIEWER accounts (every other account type still signs
 * in on the desktop site only).
 */
class AuthController extends Controller
{
    protected const ALLOWED_ACCOUNT_TYPES = ['DRIVER', 'VIEWER'];

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], [
            'login.required' => 'Please enter your badge number or e-mail address.',
            'password.required' => 'Please enter your password.',
        ]);

        $user = User::where('badge_number', $credentials['login'])
            ->orWhere('email', $credentials['login'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            ActivityLog::record(
                'login_failed',
                'Authentication',
                $user
                    ? "Failed mobile app login attempt (incorrect password) for '{$credentials['login']}'."
                    : "Failed mobile app login attempt: no account found for '{$credentials['login']}'.",
                $user
            );

            return response()->json([
                'success' => false,
                'message' => 'The badge number/e-mail or password you entered is incorrect.',
            ], 401);
        }

        // ActivityLog::record() attributes every entry to auth()->user() —
        // set it on the (stateless) web guard for the rest of this request
        // so login/deactivated/wrong-account-type entries below are
        // correctly attributed instead of logged as "no user" (there's no
        // session here the way there is for the desktop AuthController, so
        // this doesn't happen automatically).
        Auth::guard('web')->setUser($user);

        if ((string) $user->is_active !== '1') {
            ActivityLog::record(
                'login_failed',
                'Authentication',
                'Blocked mobile app login attempt: account is deactivated.',
                $user
            );

            return response()->json([
                'success' => false,
                'message' => 'This account has been deactivated. Please contact your system administrator.',
            ], 401);
        }

        if (! in_array(strtoupper(trim((string) $user->account_type)), self::ALLOWED_ACCOUNT_TYPES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'This app is for driver and viewer accounts only. Please sign in on the VMIS website instead.',
            ], 403);
        }

        if ((string) $user->is_password_changed !== '1') {
            return response()->json([
                'success' => false,
                'reason' => 'password_change_required',
                'message' => 'For your security, please set a new password before using the app.',
            ], 403);
        }

        $issued = ApiToken::generate($user, $credentials['device_name'] ?? null);

        ActivityLog::record(
            'login',
            'Authentication',
            ActivityLog::actorLabel($user) . ' logged in (mobile app).',
            $user
        );

        return response()->json([
            'success' => true,
            'token' => $issued['plain'],
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $apiToken = $request->attributes->get('api_token');
        $user = $request->user();

        if ($apiToken) {
            $apiToken->delete();
        }

        if ($user) {
            ActivityLog::record('logout', 'Authentication', ActivityLog::actorLabel($user) . ' logged out (mobile app).', $user);
        }

        return response()->json(['success' => true, 'message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'user' => $this->userPayload($request->user())]);
    }

    public function userPayload(User $user): array
    {
        $driver = $user->driver_id ? $user->driver()->first() : null;

        return [
            'id' => $user->id,
            'account_type' => strtoupper(trim((string) $user->account_type)),
            'badge_number' => $user->badge_number,
            'rank' => $user->rank,
            'firstname' => $user->firstname,
            'middlename' => $user->middlename,
            'lastname' => $user->lastname,
            'fullname' => $user->fullname ?: trim(($user->rank ? $user->rank . ' ' : '') . $user->firstname . ' ' . $user->lastname),
            'email' => $user->email,
            'has_driver_profile' => (bool) $driver,
            'driver' => $driver ? [
                'id' => $driver->id,
                'firstname' => $driver->firstname,
                'lastname' => $driver->lastname,
                'license_number' => $driver->license_number,
                'license_expiration_date' => optional($driver->license_expiration_date)->toDateString(),
                'contact_number' => $driver->contact_number,
                'status' => $driver->status,
            ] : null,
        ];
    }
}
