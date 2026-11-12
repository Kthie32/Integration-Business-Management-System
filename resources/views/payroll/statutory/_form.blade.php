@php
    $rules = $rate->rules ?? [];
    $code = $rate->code;
    $valueKey = $code === App\Enums\StatutoryRateCode::Lst ? 'annual_amount' : 'rate';
    $bands = old('bands', $rules['bands'] ?? []);
    $bandRows = max(count($bands) + 2, 6);
    $surcharge = $rules['surcharges'][0] ?? null;
    $months = array_map('intval', old('months', $rules['months'] ?? []));
@endphp

@if ($code === App\Enums\StatutoryRateCode::Nssf)
    <div class="row">
        <div class="col-sm-6">
            <x-form.input name="employee_rate" label="Employee contribution (%)" type="number" step="0.01" min="0" max="100" :value="$rules['employee_rate'] ?? null" required help="Deducted from pay." />
        </div>
        <div class="col-sm-6">
            <x-form.input name="employer_rate" label="Employer contribution (%)" type="number" step="0.01" min="0" max="100" :value="$rules['employer_rate'] ?? null" required help="Paid by the employer on top of pay." />
        </div>
    </div>
@else
    <fieldset class="mb-3">
        <legend class="form-label fs-6">{{ $code === App\Enums\StatutoryRateCode::Lst ? 'Monthly gross pay bands and the yearly LST for each' : 'Monthly taxable pay bands and the rate on the part of pay inside each band' }}</legend>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-1">
                <thead>
                    <tr>
                        <th scope="col">Above</th>
                        <th scope="col">Up to (empty = no limit)</th>
                        <th scope="col">{{ $valueKey === 'rate' ? 'Rate %' : 'LST per year' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < $bandRows; $i++)
                        <tr>
                            <td><input type="number" step="0.01" min="0" name="bands[{{ $i }}][from]" value="{{ $bands[$i]['from'] ?? '' }}" class="form-control form-control-sm" aria-label="Band {{ $i + 1 }} lower limit"></td>
                            <td><input type="number" step="0.01" min="0" name="bands[{{ $i }}][to]" value="{{ $bands[$i]['to'] ?? '' }}" class="form-control form-control-sm" aria-label="Band {{ $i + 1 }} upper limit"></td>
                            <td><input type="number" step="0.01" min="0" name="bands[{{ $i }}][{{ $valueKey }}]" value="{{ $bands[$i][$valueKey] ?? '' }}" class="form-control form-control-sm" aria-label="Band {{ $i + 1 }} {{ $valueKey === 'rate' ? 'rate' : 'yearly amount' }}"></td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
        <div class="form-text">The first band starts at 0 and each band starts where the previous one ends. Leave unused rows empty.</div>
    </fieldset>

    @if ($code->isPaye())
        <div class="row">
            <div class="col-sm-6">
                <x-form.input name="surcharge_above" label="Additional tax on pay above" type="number" step="0.01" min="0" :value="$surcharge['above'] ?? null" />
            </div>
            <div class="col-sm-6">
                <x-form.input name="surcharge_rate" label="Additional rate (%)" type="number" step="0.01" min="0" max="100" :value="$surcharge['rate'] ?? null" />
            </div>
        </div>
    @else
        <fieldset class="mb-3">
            <legend class="form-label fs-6">Months in which LST is deducted (the yearly amount is split equally)</legend>
            <div class="d-flex flex-wrap gap-3">
                @foreach (range(1, 12) as $month)
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="months[]" value="{{ $month }}" id="month_{{ $month }}" @checked(in_array($month, $months, true))>
                        <label class="form-check-label" for="month_{{ $month }}">{{ now()->startOfYear()->month($month)->format('M') }}</label>
                    </div>
                @endforeach
            </div>
        </fieldset>
    @endif
@endif

<x-form.input name="source_reference" label="Source" maxlength="255" :value="$rate->source_reference" help="Where these rules come from, e.g. the URA or NSSF publication." />
<x-form.textarea name="notes" label="Notes" rows="2" maxlength="500" :value="$rate->notes" />
