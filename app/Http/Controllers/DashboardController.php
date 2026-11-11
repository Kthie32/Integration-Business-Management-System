<?php

namespace App\Http\Controllers;

use App\Enums\QuotationStatus;
use App\Models\Invoice;
use App\Models\Payment;
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

        if ($user->can('invoices.view')) {
            $stats['overdueInvoices'] = Invoice::query()->overdue()->count();

            if ($user->can('dashboard.view_financials')) {
                $stats['receivables'] = $settings->formatMoney((string) (Invoice::query()->open()->sum('balance_due') ?: '0'));
            }
        }

        if ($user->can('payments.view') && $user->can('dashboard.view_financials')) {
            $stats['collectedThisMonth'] = $settings->formatMoney((string) (Payment::query()
                ->completed()
                ->whereBetween('payment_date', [today()->startOfMonth()->toDateString(), today()->toDateString()])
                ->sum('amount') ?: '0'));
        }

        if ($user->can('users.view')) {
            $stats['activeUsers'] = User::query()->active()->count();
        }

        return view('dashboard', ['user' => $user, 'stats' => $stats]);
    }
}
