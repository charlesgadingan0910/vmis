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
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Lets a DRIVER set a new password from inside the mobile app — mirrors the
 * web PasswordController's rules (current_password check + the same strong
 * password policy) exactly, so a password set here is accepted by the
 * website too and vice versa.
 *
 * Deliberately a public (no bearer token required) endpoint, not just one
 * guarded by AuthenticateApiToken: a driver who has never changed their
 * temporary password is blocked by AuthController::login() *before* any
 * token is ever issued, so there is no token to authenticate this call
 * with. Identity + the right to change the password are instead proven the
 * same way login() proves them — badge number/e-mail plus the current
 * (possibly temporary) password. That also means this same endpoint works
 * for an already-logged-in driver who just wants to change their password
 * from a settings screen; the app only ever needs this one code path.
 *
 * On success it behaves like a successful login: it issues a bearer token
 * and returns the user payload, so the app can go straight into the
 * dashboard without asking the driver to log in a second time.
 */
class PasswordController extends Controller
{
    protected const ALLOWED_ACCOUNT_TYPES = ['DRIVER', 'VIEWER'];

    public function update(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'current_password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], [
            'login.required' => 'Please enter your badge number or e-mail address.',
            'current_password.required' => 'Please enter your current password.',
        ]);

        $user = User::where('badge_number', $credentials['login'])
            ->orWhere('email', $credentials['login'])
            ->first();

        if (! $user || ! Hash::check($credentials['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'The badge number/e-mail or current password you entered is incorrect.',
            ], 401);
        }

        // Attribute the ActivityLog entries below correctly — there's no
        // session here, so auth()->user() would otherwise be null.
        Auth::guard('web')->setUser($user);

        if ((string) $user->is_active !== '1') {
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

        try {
            $validated = $request->validate([
                'password' => [
                    'required',
                    'confirmed',
                    'different:current_password',
                    // Same strong-password policy as the website's PasswordController:
                    // 8+ chars, upper + lower case, at least one number, one symbol.
                    Password::min(8)->mixedCase()->numbers()->symbols(),
                ],
            ], [
                'password.different' => 'Your new password must be different from your current one.',
                'password.confirmed' => 'Password confirmation does not match.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Please check your new password and try again.',
                'errors' => $e->errors(),
            ], 422);
        }

        $wasForced = (string) $user->is_password_changed !== '1';

        // The User model casts 'password' as 'hashed', so assigning the
        // plain value here is enough — it's hashed automatically on save.
        $user->password = $validated['password'];
        $user->is_password_changed = '1';
        $user->save();

        ActivityLog::record(
            'password_changed',
            'Authentication',
            ActivityLog::actorLabel($user) . ($wasForced
                ? ' set a new password from the mobile app (first-time forced change).'
                : ' changed their password from the mobile app.'),
            $user
        );

        $issued = ApiToken::generate($user, $credentials['device_name'] ?? null);

        ActivityLog::record(
            'login',
            'Authentication',
            ActivityLog::actorLabel($user) . ' logged in (mobile app).',
            $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully. Welcome to VMIS.',
            'token' => $issued['plain'],
            'token_type' => 'Bearer',
            'user' => (new AuthController())->userPayload($user),
        ]);
    }
}
