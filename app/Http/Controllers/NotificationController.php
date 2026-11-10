<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The signed-in user's in-app notifications. Users only ever see and change their own.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(20),
        ]);
    }

    /**
     * Mark one notification as read and follow its link when it points inside this application.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($notification);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        if (is_string($url) && $this->isInternalUrl($url)) {
            return redirect()->to($url);
        }

        return redirect()->route('notifications.index');
    }

    /**
     * Only same-site links are followed, so a notification can never redirect a user to another website.
     */
    private function isInternalUrl(string $url): bool
    {
        if (str_contains($url, '\\')) {
            return false;
        }

        return Str::startsWith($url, url('/').'/')
            || (Str::startsWith($url, '/') && ! Str::startsWith($url, '//'));
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
