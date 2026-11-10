<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreTaxRateRequest;
use App\Models\TaxRate;
use App\Services\Settings\TaxRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Tax rates are never deleted (past documents refer to them); they are deactivated instead.
 */
class TaxRateController extends Controller
{
    public function __construct(private TaxRateService $taxRates) {}

    public function index(): View
    {
        return view('settings.taxes.index', [
            'taxRates' => TaxRate::query()->orderByDesc('is_default')->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('settings.taxes.create', ['taxRate' => new TaxRate(['is_active' => true])]);
    }

    public function store(StoreTaxRateRequest $request): RedirectResponse
    {
        $taxRate = $this->taxRates->create($request->validated());

        return redirect()->route('settings.taxes.index')->with('success', "Tax rate \"{$taxRate->name}\" created.");
    }

    public function edit(TaxRate $taxRate): View
    {
        return view('settings.taxes.edit', ['taxRate' => $taxRate]);
    }

    public function update(StoreTaxRateRequest $request, TaxRate $taxRate): RedirectResponse
    {
        $this->taxRates->update($taxRate, $request->validated());

        return redirect()->route('settings.taxes.index')->with('success', "Tax rate \"{$taxRate->name}\" updated.");
    }
}
