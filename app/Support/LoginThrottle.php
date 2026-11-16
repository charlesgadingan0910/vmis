<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Brute-force guard for the web and mobile login forms. Failed attempts are
 * counted per login name + IP address, so one person mistyping a password
 * never locks anyone else out (a whole station behind one IP is unaffected
 * unless they all hammer the same account).
 */
class LoginThrottle
{
    public const MAX_ATTEMPTS = 5;

    public const LOCK_SECONDS = 300;

    public static function key(Request $request, string $login): string
    {
        return 'login|' . Str::lower(trim($login)) . '|' . $request->ip();
    }

    public static function locked(string $key): bool
    {
        return RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS);
    }

    public static function fail(string $key): void
    {
        RateLimiter::hit($key, self::LOCK_SECONDS);
    }

    public static function clear(string $key): void
    {
        RateLimiter::clear($key);
    }

    public static function message(string $key): string
    {
        $minutes = max(1, (int) ceil(RateLimiter::availableIn($key) / 60));

        return "Too many failed login attempts. Please try again in {$minutes} minute" . ($minutes === 1 ? '' : 's') . '.';
    }
}
