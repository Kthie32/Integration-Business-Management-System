<?php

namespace App\Http\Controllers;

use App\Services\Reports\DashboardService;
use App\Services\System\SettingsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Landing page after sign-in: permission-aware summary cards, charts and recent transactions (FR-DSH-01…05).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, SettingsService $settings, DashboardService $dashboard): View
    {
        $user = $request->user()->load('roles');
        $period = $dashboard->period($request->query('period'), $request->query('from'), $request->query('to'));

        return view('dashboard', [
            'user' => $user,
            'period' => $period,
            'periods' => DashboardService::PERIODS,
            ...$dashboard->build($user, $period),
            'personal' => [
                'unreadNotifications' => $user->unreadNotifications()->count(),
                'lastLogin' => $user->last_login_at !== null ? $settings->formatDate($user->last_login_at) : 'First visit',
            ],
        ]);
    }
}
