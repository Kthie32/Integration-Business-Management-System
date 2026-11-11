@inject('settings', App\Services\System\SettingsService::class)
@php
    $money = fn ($amount) => $settings->formatMoney((string) $amount);
    $color = $organization['brand_color'];
    $payment = $receipt->payment;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Receipt {{ $receipt->number }} · {{ $organization['name'] }}</title>
    <style>
        @page { margin: 12mm 12mm 14mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 10.5px; color: #1b2430; margin: 0; line-height: 1.45; }
        .page { max-width: 560px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .logo { max-height: 50px; max-width: 160px; margin-bottom: 6px; }
        .org-name { font-size: 15px; font-weight: bold; color: {{ $color }}; }
        .muted { color: #5b6575; }
        .title { font-size: 20px; font-weight: bold; color: {{ $color }}; text-align: right; letter-spacing: 1px; }
        .meta td { padding: 1px 0; }
        .meta td.label { color: #5b6575; text-align: right; padding-right: 8px; }
        .amount-box { margin-top: 16px; border: 2px solid {{ $color }}; padding: 10px 12px; text-align: center; }
        .amount-box .value { font-size: 20px; font-weight: bold; }
        .details { margin-top: 14px; }
        .details td { padding: 5px 6px; border-bottom: 1px solid #e6e9ef; }
        .details td.label { color: #5b6575; width: 38%; }
        .signature { margin-top: 30px; width: 55%; }
        .signature .line { border-top: 1px solid #1b2430; padding-top: 4px; margin-top: 34px; }
        .footer { margin-top: 20px; border-top: 1px solid #dde2ea; padding-top: 6px; font-size: 9px; color: #5b6575; text-align: center; white-space: pre-line; }
        .watermark { position: fixed; top: 38%; left: 0; width: 100%; text-align: center; font-size: 80px; color: rgba(192, 57, 43, 0.14); transform: rotate(-30deg); z-index: -1; }
        .toolbar { background: #f4f6f9; border-bottom: 1px solid #dde2ea; padding: 10px; text-align: center; margin-bottom: 16px; font-family: system-ui, sans-serif; }
        .toolbar button { background: {{ $color }}; color: #fff; border: 0; border-radius: 6px; padding: 8px 18px; font-size: 14px; cursor: pointer; }
        @media print { .toolbar { display: none; } }
        @media screen { body { background: #fff; } .page { padding: 0 16px 32px; } }
    </style>
</head>
<body>
    @unless ($forPdf)
        <div class="toolbar">
            <button type="button" onclick="window.print()">Print</button>
        </div>
    @endunless

    @unless ($receipt->isValid())
        <div class="watermark">VOID</div>
    @endunless

    <div class="page">
        <table class="header">
            <tr>
                <td style="width: 55%;">
                    @if ($organization['logo'])
                        <img src="{{ $organization['logo'] }}" alt="" class="logo"><br>
                    @endif
                    <div class="org-name">{{ $organization['name'] }}</div>
                    <div class="muted">
                        @if ($organization['address']){!! nl2br(e($organization['address'])) !!}<br>@endif
                        @if ($organization['phone'])Tel: {{ $organization['phone'] }}<br>@endif
                        @if ($organization['email']){{ $organization['email'] }}<br>@endif
                        @if ($organization['tin'])TIN: {{ $organization['tin'] }}@endif
                    </div>
                </td>
                <td style="width: 45%;">
                    <div class="title">RECEIPT</div>
                    <table class="meta" style="margin-top: 8px;">
                        <tr><td class="label">Number</td><td><strong>{{ $receipt->number }}</strong></td></tr>
                        <tr><td class="label">Date</td><td>{{ $settings->formatDate($receipt->receipt_date) }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="amount-box">
            <div class="muted">Amount received</div>
            <div class="value">{{ $money($receipt->amount) }}</div>
        </div>

        <table class="details">
            <tr><td class="label">Received from</td><td><strong>{{ $payment->payer_name ?? $receipt->customer->name }}</strong>@if ($payment->payer_name)<br><span class="muted">on behalf of {{ $receipt->customer->name }}</span>@endif</td></tr>
            <tr><td class="label">For invoice</td><td>{{ $receipt->invoice->displayNumber() }}</td></tr>
            <tr><td class="label">Payment method</td><td>{{ $payment->method->label() }}</td></tr>
            @if ($payment->reference)
                <tr><td class="label">Reference</td><td>{{ $payment->reference }}</td></tr>
            @endif
            <tr><td class="label">Payment number</td><td>{{ $payment->number }}</td></tr>
            @if ($receipt->isValid())
                <tr><td class="label">Invoice balance now</td><td>{{ $money($receipt->invoice->balance_due) }}</td></tr>
            @else
                <tr><td class="label">Status</td><td><strong>VOID</strong> — payment reversed{{ $receipt->void_reason ? ': '.$receipt->void_reason : '' }}</td></tr>
            @endif
        </table>

        <div class="signature">
            <div class="line">
                {{ $receipt->issuer?->name ?? ($organization['signatory_name'] ?: 'Received by') }}
                <br><span class="muted">Received by</span>
            </div>
        </div>

        @if ($organization['document_footer'])
            <div class="footer">{{ $organization['document_footer'] }}</div>
        @endif
    </div>
</body>
</html>
