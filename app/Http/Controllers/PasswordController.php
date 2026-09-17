<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    /**
     * Show the "set a new password" screen.
     * If the user already went through this, there's nothing to force — send them on.
     */
    public function edit(): View|RedirectResponse
    {
        $user = Auth::user();

        if ((string) $user->is_password_changed === '1') {
            return redirect()->route('dashboard');
        }

        return view('auth.change-password');
    }

    /**
     * Update the password and mark the account as no longer using a temporary one.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'confirmed',
                'different:current_password',
                // Strong-password policy: 8+ chars, upper + lower case, at least one number, one symbol.
                // Add ->uncompromised() below if this server has outbound internet access — it checks the
                // password against known data-breach lists via the HaveIBeenPwned API.
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ], [
            'current_password.current_password' => 'The current password you entered is incorrect.',
            'password.different' => 'Your new password must be different from your current one.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        // The User model casts 'password' as 'hashed', so assigning the plain
        // value here is enough — it's hashed automatically on save.
        $user->password = $request->password;
        $user->is_password_changed = '1';
        $user->save();

        return redirect()->route('dashboard')
            ->with('success', 'Password updated successfully. Welcome to VMIS.');
    }
}
