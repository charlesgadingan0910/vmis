<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Show the login screen.
     * If someone who is already signed in lands here, send them where they belong
     * instead of showing the login form again.
     */
    public function login(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectAfterAuth(Auth::user());
        }

        return view('auth.login');
    }

    /**
     * Handle a login attempt.
     * Accepts either badge number or e-mail in a single "login" field.
     */
    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'login.required'    => 'Please enter your badge number or e-mail address.',
            'password.required' => 'Please enter your password.',
        ]);

        $user = User::where('badge_number', $credentials['login'])
            ->orWhere('email', $credentials['login'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            // Distinguish "no such account" from "wrong password for a real
            // account" — the latter is tied to that user so repeated failed
            // attempts against one account are visible on the activity log,
            // not just an anonymous stream of failures.
            ActivityLog::record(
                'login_failed',
                'Authentication',
                $user
                    ? "Failed login attempt (incorrect password) for '{$credentials['login']}'."
                    : "Failed login attempt: no account found for '{$credentials['login']}'.",
                $user
            );

            return back()
                ->withErrors(['login' => 'The badge number/e-mail or password you entered is incorrect.'])
                ->withInput($request->except('password'));
        }

        if ((string) $user->is_active !== '1') {
            ActivityLog::record(
                'login_failed',
                'Authentication',
                "Blocked login attempt: account is deactivated.",
                $user
            );

            return back()
                ->withErrors(['login' => 'This account has been deactivated. Please contact your system administrator.'])
                ->withInput($request->except('password'));
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->forceFill(['is_online' => '1'])->save();

        return $this->redirectAfterAuth($user, true);
    }

    /**
     * Log the current user out.
     */
    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user) {
            $user->forceFill(['is_online' => '0'])->save();
            ActivityLog::record('logout', 'Authentication', ActivityLog::actorLabel($user).' logged out.', $user);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been logged out successfully.');
    }

    /**
     * Decide where a signed-in user should land: the forced password-change
     * screen if they haven't set their own password yet, otherwise the dashboard.
     */
    protected function redirectAfterAuth(User $user, bool $justLoggedIn = false): RedirectResponse
    {
        if ((string) $user->is_password_changed !== '1') {
            return redirect()->route('password.change')
                ->with('status', 'For your security, please set a new password before continuing.');
        }

        $redirect = redirect()->intended(route('dashboard'));

        if ($justLoggedIn) {
            $name = trim(($user->rank ? $user->rank.' ' : '').$user->firstname.' '.$user->lastname);
            $redirect->with('success', 'Welcome back, '.$name.'.');
            ActivityLog::record('login', 'Authentication', ActivityLog::actorLabel($user).' logged in.', $user);
        }

        return $redirect;
    }
}
