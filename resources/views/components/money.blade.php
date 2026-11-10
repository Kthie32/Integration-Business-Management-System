@props(['amount'])

{{-- Displays an amount in the organization's base currency, e.g. "UGX 1,250,000". --}}
@inject('settings', App\Services\System\SettingsService::class)

<span {{ $attributes->class(['text-nowrap', 'text-danger' => App\Support\Money::of($amount ?? 0)->isNegative()]) }}>{{ $settings->formatMoney($amount) }}</span>
