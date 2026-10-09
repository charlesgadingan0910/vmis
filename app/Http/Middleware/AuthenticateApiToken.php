<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates every /api/v1/* request (except login) using a bearer
 * token issued by Api\AuthController::login(). Deliberately scoped to
 * DRIVER and VIEWER accounts only — this API exists for the VMIS mobile
 * app, not as a general-purpose API for every account type. A DRIVER logs
 * trips/fuel/accidents for their own assigned vehicle; a VIEWER gets the
 * same broad, read-only visibility they already have on the desktop site
 * (see each Api\*Controller's VIEWER branch) but can never submit anything.
 *
 * On success, sets the resolved User on the default ("web") guard so any
 * reused controller logic that calls auth()->user() keeps working exactly
 * as it does on the desktop site, and stashes the ApiToken row itself on
 * the request (api_token attribute) for Api\AuthController::logout().
 */
class AuthenticateApiToken
{
    protected const ALLOWED_ACCOUNT_TYPES = ['DRIVER', 'VIEWER'];

    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();

        if (! $plain) {
            return response()->json(['success' => false, 'message' => 'Missing or invalid API token.'], 401);
        }

        $apiToken = ApiToken::findValidByPlain($plain);

        if (! $apiToken) {
            return response()->json(['success' => false, 'message' => 'Your session has expired. Please log in again.'], 401);
        }

        $user = $apiToken->user;

        if (! $user || (string) $user->is_active !== '1') {
            return response()->json(['success' => false, 'message' => 'This account has been deactivated. Please contact your system administrator.'], 401);
        }

        if (! in_array(strtoupper(trim((string) $user->account_type)), self::ALLOWED_ACCOUNT_TYPES, true)) {
            return response()->json(['success' => false, 'message' => 'This app is for driver and viewer accounts only.'], 403);
        }

        $apiToken->forceFill(['last_used_at' => now()])->save();

        Auth::guard('web')->setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('api_token', $apiToken);

        return $next($request);
    }
}
