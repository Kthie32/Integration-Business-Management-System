<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\Users\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in user's own profile and security pages. Profile, password and two-factor changes are
 * submitted to Fortify's endpoints; this controller only renders the pages and manages own sessions.
 */
class AccountController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function profile(Request $request): View
    {
        return view('account.profile', ['user' => $request->user()->load('roles')]);
    }

    public function security(Request $request): View
    {
        return view('account.security', [
            'user' => $request->user(),
            'sessions' => $this->userService->activeSessions($request->user(), $request->session()->getId()),
        ]);
    }

    public function endSession(Request $request, string $session): RedirectResponse
    {
        $this->userService->endSession($request->user(), $session);

        return back()->with('success', 'The session was ended.');
    }

    public function endOtherSessions(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password:web']]);

        $ended = $this->userService->endOtherSessions($request->user(), $request->session()->getId());

        return back()->with('success', "Signed out of {$ended} other session(s).");
    }
}
