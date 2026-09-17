<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Redirect to the forced password-change screen if the signed-in user
     * is still using a temporary/admin-issued password.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && (string) $user->is_password_changed !== '1') {
            return redirect()->route('password.change')
                ->with('status', 'For your security, please set a new password before continuing.');
        }

        return $next($request);
    }
}
