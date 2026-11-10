<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Account administration actions on a user: activation, password links and session control.
 */
class UserAccountController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('deactivate', $user);

        $this->userService->deactivate($user, $request->user());

        return back()->with('success', "{$user->name} has been deactivated and signed out.");
    }

    public function activate(User $user): RedirectResponse
    {
        Gate::authorize('activate', $user);

        $this->userService->activate($user);

        return back()->with('success', "{$user->name} has been reactivated.");
    }

    public function sendPasswordLink(User $user): RedirectResponse
    {
        Gate::authorize('sendPasswordLink', $user);

        $this->userService->sendPasswordSetupLink($user);

        return back()->with('success', "A password link was emailed to {$user->email}.");
    }

    public function endSession(User $user, string $session): RedirectResponse
    {
        Gate::authorize('manageSessions', $user);

        return $this->userService->endSession($user, $session)
            ? back()->with('success', 'The session was ended.')
            : back()->with('error', 'That session has already ended.');
    }

    public function endAllSessions(User $user): RedirectResponse
    {
        Gate::authorize('manageSessions', $user);

        $ended = $this->userService->endAllSessions($user);

        return back()->with('success', "Ended {$ended} session(s) for {$user->name}.");
    }
}
