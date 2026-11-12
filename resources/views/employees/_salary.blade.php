@php($salaryErrors = $errors->getBag('salary'))
<div class="col-12" id="salary">
    <div class="card">
        <div class="card-header fw-semibold"><i class="bi bi-lock me-1" aria-hidden="true"></i>Salary</div>
        @if ($salaryStructures->isEmpty())
            <div class="card-body small text-secondary">No salary recorded. The employee is skipped by payroll until one is.</div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Effective</th>
                            <th scope="col" class="text-end">Basic salary</th>
                            <th scope="col">Allowances &amp; deductions</th>
                            <th scope="col">Recorded by</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($salaryStructures as $structure)
                            <tr>
                                <td class="text-nowrap">
                                    {{ $settings->formatDate($structure->effective_from) }} – {{ $structure->effective_to ? $settings->formatDate($structure->effective_to) : 'now' }}
                                    @if ($loop->first && $structure->effective_to === null)<x-status-badge label="Current" color="success" class="ms-1" />@endif
                                </td>
                                <td class="text-end"><x-money :amount="(string) $structure->basic_salary" /></td>
                                <td>
                                    @forelse ($structure->components as $line)
                                        <div>
                                            {{ $line->payComponent->name }}:
                                            @if ($line->payComponent->calculation->isPercentage())
                                                {{ rtrim(rtrim((string) $line->value, '0'), '.') }} {{ $line->payComponent->calculation->label() }}
                                            @else
                                                <x-money :amount="(string) $line->value" />
                                            @endif
                                            <span class="text-secondary">({{ strtolower($line->payComponent->kind->label()) }})</span>
                                        </div>
                                    @empty
                                        <span class="text-secondary">Basic salary only</span>
                                    @endforelse
                                    @if ($structure->notes)<div class="text-secondary">{{ $structure->notes }}</div>@endif
                                </td>
                                <td>{{ $structure->creator?->name ?? '—' }}</td>
                                <td class="text-end">
                                    @if ($loop->first)
                                        @can('manageSalary', $employee)
                                            <form method="POST" action="{{ route('employees.salary-structures.destroy', [$employee, $structure]) }}" data-confirm="Remove this salary structure? The previous one becomes current again.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Remove salary structure"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                            </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @can('manageSalary', $employee)
            <div class="card-body border-top">
                <h3 class="h6">{{ $salaryStructures->isEmpty() ? 'Record salary' : 'Record a salary change' }}</h3>
                <form method="POST" action="{{ route('employees.salary-structures.store', $employee) }}" novalidate>
                    @csrf
                    <div class="row">
                        <div class="col-md-4">
                            <x-form.input name="basic_salary" label="Basic salary (monthly)" type="number" step="0.01" min="0" bag="salary" id="salary_basic"
                                :value="$salaryStructures->first()?->basic_salary" required />
                        </div>
                        <div class="col-md-4">
                            <x-form.input name="effective_from" label="Effective from" type="date" bag="salary" id="salary_effective_from"
                                :value="$salaryStructures->isEmpty() ? $employee->hire_date?->toDateString() : today()->startOfMonth()->addMonth()->toDateString()" required />
                        </div>
                        <div class="col-md-4">
                            <x-form.input name="notes" label="Notes" maxlength="500" bag="salary" id="salary_notes" />
                        </div>
                    </div>
                    @if ($payComponents->isNotEmpty())
                        @php($current = $salaryStructures->first()?->components->keyBy('pay_component_id') ?? collect())
                        <div class="row g-2 mb-3">
                            @foreach ($payComponents as $component)
                                <div class="col-sm-6 col-lg-4">
                                    <label for="component_{{ $component->id }}" class="form-label small mb-0">
                                        {{ $component->name }} <span class="text-secondary">({{ strtolower($component->kind->label()) }}, {{ $component->calculation->isPercentage() ? $component->calculation->label() : 'amount' }})</span>
                                    </label>
                                    <input type="number" step="0.0001" min="0" name="components[{{ $component->id }}]" id="component_{{ $component->id }}"
                                        value="{{ old('components.'.$component->id, isset($current[$component->id]) ? rtrim(rtrim((string) $current[$component->id]->value, '0'), '.') : '') }}"
                                        class="form-control form-control-sm @if ($salaryErrors->has('components.'.$component->id)) is-invalid @endif" placeholder="Not assigned">
                                </div>
                            @endforeach
                        </div>
                        <div class="form-text mb-3">Leave a component empty if it does not apply. Values are copied from the current salary for convenience.</div>
                    @endif
                    <button type="submit" class="btn btn-outline-primary">Save salary</button>
                </form>
            </div>
        @endcan
    </div>
</div>
