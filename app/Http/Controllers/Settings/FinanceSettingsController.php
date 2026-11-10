<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateFinanceSettingsRequest;
use App\Services\System\SettingsService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Base currency and finance defaults (quotation validity, payment terms, approval threshold).
 */
class FinanceSettingsController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function edit(): View
    {
        return view('settings.finance', [
            'currency' => $this->settings->group('currency'),
            'finance' => $this->settings->group('finance'),
        ]);
    }

    public function update(UpdateFinanceSettingsRequest $request): RedirectResponse
    {
        $threshold = $request->validated('quotation_approval_threshold');

        DB::transaction(function () use ($request, $threshold): void {
            $this->settings->update('currency', [
                'code' => $request->validated('currency_code'),
                'symbol' => $request->validated('currency_symbol'),
                'decimals' => (int) $request->validated('currency_decimals'),
            ]);

            $this->settings->update('finance', [
                'quotation_validity_days' => (int) $request->validated('quotation_validity_days'),
                'invoice_payment_terms_days' => (int) $request->validated('invoice_payment_terms_days'),
                'quotation_approval_threshold' => $threshold !== null ? Money::of((string) $threshold)->toDecimal() : null,
            ]);
        });

        return redirect()->route('settings.finance.edit')->with('success', 'Currency and finance defaults saved.');
    }
}
