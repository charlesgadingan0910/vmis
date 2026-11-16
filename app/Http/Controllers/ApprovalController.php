<?php

namespace App\Http\Controllers;

use App\Models\ChangeRequest;
use App\Services\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Approvals page (P2-01): what is waiting for me, what I asked for, and
 * recent decisions. Approve/reject run through ApprovalService.
 */
class ApprovalController extends Controller
{
    protected function guard(): void
    {
        if (ApprovalService::rank(ApprovalService::role(auth()->user())) < 1) {
            abort(403, 'Your account type does not use approvals.');
        }
    }

    public function index(): View
    {
        $this->guard();
        $user = auth()->user();
        $isSuper = ApprovalService::role($user) === 'SUPER ADMINISTRATOR';

        $all = ChangeRequest::with(['vehicle', 'driver', 'requester', 'decider'])->latest('id')->limit(300)->get();

        $waiting = $all->filter(fn ($r) => ApprovalService::canApprove($user, $r))->values();
        $mine = $all->filter(fn ($r) => $r->requested_by === $user->id)->values();
        $history = $all
            ->filter(fn ($r) => ! $r->isPending() && ($isSuper || $r->requested_by === $user->id || $r->decided_by === $user->id))
            ->take(50)->values();

        return view('approvals.index', compact('waiting', 'mine', 'history'));
    }

    public function approve(Request $request, ChangeRequest $changeRequest, ApprovalService $approvals): RedirectResponse
    {
        $this->guard();
        $result = $approvals->approve($changeRequest, auth()->user(), $request->input('note'));

        return back()->with($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Approved and applied: ' . $result['message'] : 'Could not apply: ' . $result['message']);
    }

    public function reject(Request $request, ChangeRequest $changeRequest, ApprovalService $approvals): RedirectResponse
    {
        $this->guard();
        $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        $approvals->reject($changeRequest, auth()->user(), $request->input('note'));

        return back()->with('success', 'Request rejected. Nothing was changed.');
    }

    public function cancel(ChangeRequest $changeRequest, ApprovalService $approvals): RedirectResponse
    {
        $this->guard();
        $approvals->cancel($changeRequest, auth()->user());

        return back()->with('success', 'Request cancelled.');
    }
}
