<?php

namespace App\Http\Controllers;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;
use App\Services\System\SettingsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Landing page after sign-in. Module widgets are added as each module is built.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, SettingsService $settings): View
    {
        $user = $request->user()->load('roles');

        $stats = [
            'unreadNotifications' => $user->unreadNotifications()->count(),
            'lastLogin' => $user->last_login_at !== null ? $settings->formatDate($user->last_login_at) : 'First visit',
        ];

        if ($user->can('quotations.view')) {
            $stats['openQuotations'] = Quotation::query()
                ->whereIn('status', [QuotationStatus::PendingApproval->value, QuotationStatus::Approved->value, QuotationStatus::Sent->value])
                ->count();
        }

        if ($user->can('users.view')) {
            $stats['activeUsers'] = User::query()->active()->count();
        }

        return view('dashboard', ['user' => $user, 'stats' => $stats]);
    }
}
