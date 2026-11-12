@inject('settings', App\Services\System\SettingsService::class)
@php
    $money = fn ($amount) => $settings->formatMoney((string) $amount);
    $color = $organization['brand_color'];
    $run = $item->payrollRun;
    $watermark = $run->isLocked() ? null : 'NOT APPROVED';
    $earnings = $item->earnings();
    $deductions = $item->deductions();
    $rows = max($earnings->count(), $deductions->count());
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Payslip {{ $item->displayNumber() }} · {{ $item->employee_name }} · {{ $organization['name'] }}</title>
    <style>
        @page { margin: 14mm 12mm 16mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 10px; color: #1b2430; margin: 0; line-height: 1.4; }
        .page { max-width: 760px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .logo { max-height: 55px; max-width: 170px; margin-bottom: 6px; }
        .org-name { font-size: 15px; font-weight: bold; color: {{ $color }}; }
        .muted { color: #5b6575; }
        .title { font-size: 20px; font-weight: bold; color: {{ $color }}; text-align: right; letter-spacing: 1px; }
        .meta td { padding: 1px 0; }
        .meta td.label { color: #5b6575; text-align: right; padding-right: 8px; }
        .section { margin-top: 14px; }
        .section h2 { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: {{ $color }}; margin: 0 0 4px; }
        .box { border: 1px solid #dde2ea; padding: 8px 10px; }
        .lines th { background: {{ $color }}; color: #fff; text-align: left; padding: 5px 6px; font-weight: bold; }
        .lines td { padding: 5px 6px; border-bottom: 1px solid #e6e9ef; vertical-align: top; }
        .lines .num { text-align: right; white-space: nowrap; }
        .lines .total td { font-weight: bold; border-top: 1px solid #1b2430; border-bottom: 0; }
        .net { margin-top: 12px; border: 2px solid {{ $color }}; padding: 8px 12px; font-size: 14px; font-weight: bold; }
        .net span { float: right; }
        .footer { margin-top: 20px; border-top: 1px solid #dde2ea; padding-top: 6px; font-size: 9px; color: #5b6575; text-align: center; white-space: pre-line; }
        .watermark { position: fixed; top: 38%; left: 0; width: 100%; text-align: center; font-size: 70px; color: rgba(192, 57, 43, 0.14); transform: rotate(-30deg); z-index: -1; }
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

    @if ($watermark)
        <div class="watermark">{{ $watermark }}</div>
    @endif

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
                        @if ($organization['tin'])TIN: {{ $organization['tin'] }}@endif
                    </div>
                </td>
                <td style="width: 45%;">
                    <div class="title">PAYSLIP</div>
                    <table class="meta" style="margin-top: 8px;">
                        <tr><td class="label">Payslip no.</td><td><strong>{{ $item->displayNumber() }}</strong></td></tr>
                        <tr><td class="label">Pay period</td><td>{{ $run->periodLabel() }}</td></tr>
                        @if ($run->payment_date || $item->paid_at)
                            <tr><td class="label">Payment date</td><td>{{ $settings->formatDate($item->paid_at ?? $run->payment_date) }}</td></tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>

        <div class="section box">
            <table class="meta">
                <tr>
                    <td class="label" style="text-align: left; width: 18%;">Employee</td><td style="width: 32%;"><strong>{{ $item->employee_name }}</strong></td>
                    <td class="label" style="text-align: left; width: 18%;">Employee no.</td><td>{{ $item->employee_number }}</td>
                </tr>
                <tr>
                    <td class="label" style="text-align: left;">Department</td><td>{{ $item->department_name ?? '—' }}</td>
                    <td class="label" style="text-align: left;">Job title</td><td>{{ $item->job_title ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label" style="text-align: left;">TIN</td><td>{{ $item->tin ?? '—' }}</td>
                    <td class="label" style="text-align: left;">NSSF no.</td><td>{{ $item->nssf_number ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label" style="text-align: left;">Paid by</td><td colspan="3">{{ $item->payment_method->label() }}@if ($item->paymentDestination() !== 'Cash') · {{ $item->paymentDestination() }}@endif</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <table class="lines">
                <thead>
                    <tr>
                        <th style="width: 32%;">Earnings</th><th class="num" style="width: 18%;">Amount</th>
                        <th style="width: 32%;">Deductions</th><th class="num" style="width: 18%;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < $rows; $i++)
                        <tr>
                            <td>{{ $earnings[$i]->description ?? '' }}</td>
                            <td class="num">{{ isset($earnings[$i]) ? $money($earnings[$i]->amount) : '' }}</td>
                            <td>{{ $deductions[$i]->description ?? '' }}</td>
                            <td class="num">{{ isset($deductions[$i]) ? $money($deductions[$i]->amount) : '' }}</td>
                        </tr>
                    @endfor
                    <tr class="total">
                        <td>Gross pay</td><td class="num">{{ $money($item->gross_pay) }}</td>
                        <td>Total deductions</td><td class="num">{{ $money($item->total_deductions) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="net">NET PAY <span>{{ $money($item->net_pay) }}</span></div>
        </div>

        <div class="section">
            <h2>Tax and contributions</h2>
            <table class="meta">
                <tr><td class="label" style="text-align: left; width: 40%;">Taxable pay</td><td>{{ $money($item->taxable_pay) }}</td></tr>
                <tr><td class="label" style="text-align: left;">Pay subject to NSSF</td><td>{{ $money($item->nssf_pay) }}</td></tr>
                @foreach ($item->employerContributions() as $line)
                    <tr><td class="label" style="text-align: left;">{{ $line->description }} (paid by employer)</td><td>{{ $money($line->amount) }}</td></tr>
                @endforeach
            </table>
        </div>

        <div class="footer">This payslip was generated by {{ $organization['name'] }}'s management system.@if ($organization['document_footer'])

{{ $organization['document_footer'] }}@endif</div>
    </div>
</body>
</html>
