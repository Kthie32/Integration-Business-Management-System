<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\StatutoryRateCode;
use App\Http\Controllers\Controller;
use App\Models\StatutoryRate;
use App\Services\Payroll\PayrollConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Statutory rule sets (FR-PRL-03): PAYE bands, NSSF rates and the LST schedule, as effective-dated versions that
 * authorised users add, correct (until used by an approved run) and mark as verified (docs/07 D2).
 */
class StatutoryRateController extends Controller
{
    public function __construct(private PayrollConfigurationService $configuration) {}

    public function index(): View
    {
        Gate::authorize('viewAny', StatutoryRate::class);

        return view('payroll.statutory.index', [
            'groups' => collect(StatutoryRateCode::cases())->mapWithKeys(fn (StatutoryRateCode $code): array => [
                $code->value => [
                    'code' => $code,
                    'versions' => StatutoryRate::query()->where('code', $code->value)->with('verifier')->orderByDesc('effective_from')->get(),
                ],
            ]),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', StatutoryRate::class);

        $code = StatutoryRateCode::tryFrom((string) $request->query('code')) ?? abort(404);
        $current = StatutoryRate::query()->where('code', $code->value)->orderByDesc('effective_from')->first();

        $rate = new StatutoryRate;
        $rate->forceFill([
            'code' => $code,
            'rules' => $current?->rules ?? PayrollConfigurationService::defaultRules($code),
            'effective_from' => $current !== null ? today()->addMonth()->startOfMonth() : today()->startOfMonth(),
        ]);

        return view('payroll.statutory.create', ['rate' => $rate]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', StatutoryRate::class);

        $data = $request->validate([
            'code' => ['required', Rule::enum(StatutoryRateCode::class)],
            'effective_from' => ['required', 'date'],
            ...$this->ruleFields(),
        ]);

        $rate = $this->configuration->createRate(StatutoryRateCode::from($data['code']), $data, $request->user());

        return redirect()->route('statutory-rates.index')->with('success', "{$rate->code->label()} from {$rate->effective_from->toDateString()} added. Mark it verified once checked against the official publication.");
    }

    public function edit(StatutoryRate $statutoryRate): View
    {
        Gate::authorize('update', $statutoryRate);

        return view('payroll.statutory.edit', ['rate' => $statutoryRate]);
    }

    public function update(Request $request, StatutoryRate $statutoryRate): RedirectResponse
    {
        Gate::authorize('update', $statutoryRate);

        $data = $request->validate($this->ruleFields());
        $this->configuration->updateRate($statutoryRate, $data);

        return redirect()->route('statutory-rates.index')->with('success', "{$statutoryRate->code->label()} updated.");
    }

    public function verify(Request $request, StatutoryRate $statutoryRate): RedirectResponse
    {
        Gate::authorize('verify', $statutoryRate);

        $data = $request->validate(['source_reference' => ['required', 'string', 'min:3', 'max:255']]);
        $this->configuration->verifyRate($statutoryRate, $request->user(), $data['source_reference']);

        return redirect()->route('statutory-rates.index')->with('success', "{$statutoryRate->code->label()} marked as verified.");
    }

    /**
     * @return array<string, list<string>>
     */
    private function ruleFields(): array
    {
        return [
            'bands' => ['nullable', 'array', 'max:30'],
            'bands.*.from' => ['nullable', 'numeric', 'min:0'],
            'bands.*.to' => ['nullable', 'numeric', 'min:0'],
            'bands.*.rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bands.*.annual_amount' => ['nullable', 'numeric', 'min:0'],
            'surcharge_above' => ['nullable', 'numeric', 'min:0'],
            'surcharge_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'employee_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'employer_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'months' => ['nullable', 'array'],
            'months.*' => ['integer', 'min:1', 'max:12'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
