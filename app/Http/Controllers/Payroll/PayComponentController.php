<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\PayComponentCalculation;
use App\Enums\PayComponentKind;
use App\Http\Controllers\Controller;
use App\Models\PayComponent;
use App\Services\Payroll\PayrollConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pay components: allowances and deductions (FR-PRL-01). A component in use is deactivated, not deleted.
 */
class PayComponentController extends Controller
{
    public function __construct(private PayrollConfigurationService $configuration) {}

    public function index(): View
    {
        Gate::authorize('viewAny', PayComponent::class);

        return view('payroll.components.index', [
            'components' => PayComponent::query()->withCount('structureComponents')->orderBy('kind')->orderBy('name')->get(),
            'component' => $this->blank(),
            ...$this->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', PayComponent::class);

        $component = $this->configuration->saveComponent(new PayComponent, $this->validated($request, null), $request->user());

        return redirect()->route('pay-components.index')->with('success', "Pay component \"{$component->name}\" created.");
    }

    public function edit(PayComponent $payComponent): View
    {
        Gate::authorize('update', $payComponent);

        return view('payroll.components.edit', ['component' => $payComponent, ...$this->options()]);
    }

    public function update(Request $request, PayComponent $payComponent): RedirectResponse
    {
        Gate::authorize('update', $payComponent);

        $this->configuration->saveComponent($payComponent, $this->validated($request, $payComponent), $request->user());

        return redirect()->route('pay-components.index')->with('success', 'Pay component updated.');
    }

    public function destroy(PayComponent $payComponent): RedirectResponse
    {
        Gate::authorize('delete', $payComponent);

        $this->configuration->deleteComponent($payComponent);

        return redirect()->route('pay-components.index')->with('success', "Pay component \"{$payComponent->name}\" deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?PayComponent $component): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('pay_components', 'code')->ignore($component?->id)],
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::enum(PayComponentKind::class)],
            'calculation' => ['required', Rule::enum(PayComponentCalculation::class)],
            'default_value' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'is_taxable' => ['nullable', 'boolean'],
            'is_nssf_applicable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'code.regex' => 'Use letters, numbers, dashes and underscores only.',
        ]);
    }

    private function blank(): PayComponent
    {
        $component = new PayComponent;
        $component->forceFill([
            'kind' => PayComponentKind::Allowance,
            'calculation' => PayComponentCalculation::Fixed,
            'default_value' => '0',
            'is_taxable' => true,
            'is_nssf_applicable' => true,
            'is_active' => true,
        ]);

        return $component;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function options(): array
    {
        return ['kinds' => PayComponentKind::options(), 'calculations' => PayComponentCalculation::options()];
    }
}
