<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin list of Driver-app logins ("devices") with a Revoke button, so a
 * lost or stolen phone can be cut off immediately — the next request from
 * that phone gets a 401 and it lands on the login screen.
 */
class DeviceController extends Controller
{
    protected function authorizeAccess(): void
    {
        $role = strtoupper(trim((string) auth()->user()->account_type));

        if (! in_array($role, ['SUPER ADMINISTRATOR', 'ADMINISTRATOR'], true)) {
            abort(403, 'Only administrators can manage mobile devices.');
        }
    }

    public function index(): View
    {
        $this->authorizeAccess();

        $tokens = ApiToken::with('user')->orderByDesc('last_used_at')->orderByDesc('id')->get();

        return view('devices.index', compact('tokens'));
    }

    public function revoke(ApiToken $apiToken): RedirectResponse
    {
        $this->authorizeAccess();

        $owner = $apiToken->user;
        $label = $apiToken->device_name ?: 'Unnamed device';
        $apiToken->delete();

        ActivityLog::record(
            'updated',
            'Mobile Device',
            "Revoked mobile login '{$label}'" . ($owner ? ' of ' . ActivityLog::actorLabel($owner) : '') . '.',
            $owner
        );

        return back()->with('success', "Revoked: {$label}.");
    }

    public function revokeUser(User $user): RedirectResponse
    {
        $this->authorizeAccess();

        $count = ApiToken::where('user_id', $user->id)->delete();

        ActivityLog::record(
            'updated',
            'Mobile Device',
            "Revoked all {$count} mobile login(s) of " . ActivityLog::actorLabel($user) . '.',
            $user
        );

        return back()->with('success', "Revoked {$count} device(s) of " . ActivityLog::actorLabel($user) . '.');
    }
}
