@inject('settings', App\Services\System\SettingsService::class)
@php($rules = $rate->rules ?? [])
@php($amount = fn ($value) => $value === null ? '…' : $settings->formatMoney((string) $value))
@switch($rate->code)
    @case(App\Enums\StatutoryRateCode::Nssf)
        Employee {{ $rules['employee_rate'] ?? '?' }} % · employer {{ $rules['employer_rate'] ?? '?' }} %
        @break
    @case(App\Enums\StatutoryRateCode::Lst)
        <ul class="list-unstyled mb-1">
            @foreach ($rules['bands'] ?? [] as $band)
                <li>{{ $amount($band['from']) }} – {{ $amount($band['to']) }}: {{ $amount($band['annual_amount']) }} a year</li>
            @endforeach
        </ul>
        Deducted in {{ collect($rules['months'] ?? [])->map(fn ($month) => now()->startOfYear()->month((int) $month)->format('M'))->implode(', ') }}
        @break
    @default
        <ul class="list-unstyled mb-0">
            @foreach ($rules['bands'] ?? [] as $band)
                <li>{{ $amount($band['from']) }} – {{ $amount($band['to']) }}: {{ $band['rate'] }} %</li>
            @endforeach
            @foreach ($rules['surcharges'] ?? [] as $surcharge)
                <li>plus {{ $surcharge['rate'] }} % of pay above {{ $amount($surcharge['above']) }}</li>
            @endforeach
        </ul>
@endswitch
