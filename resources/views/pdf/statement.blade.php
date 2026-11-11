@inject('settings', App\Services\System\SettingsService::class)
@php
    $money = fn ($amount) => $settings->formatMoney((string) $amount);
    $color = $organization['brand_color'];
    $customer = $statement['customer'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Statement · {{ $customer->name }} · {{ $organization['name'] }}</title>
    <style>
        @page { margin: 18mm 15mm 20mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 10px; color: #1b2430; margin: 0; line-height: 1.45; }
        .page { max-width: 800px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .logo { max-height: 60px; max-width: 200px; margin-bottom: 6px; }
        .org-name { font-size: 16px; font-weight: bold; color: {{ $color }}; }
        .muted { color: #5b6575; }
        .title { font-size: 20px; font-weight: bold; color: {{ $color }}; text-align: right; letter-spacing: 1px; }
        .meta td { padding: 1px 0; }
        .meta td.label { color: #5b6575; text-align: right; padding-right: 8px; }
        .box { border: 1px solid #dde2ea; padding: 8px 10px; }
        .section-title { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #5b6575; margin-bottom: 3px; }
        .lines { margin-top: 16px; }
        .lines th { background: {{ $color }}; color: #fff; font-weight: bold; padding: 6px; text-align: left; font-size: 9px; }
        .lines td { padding: 5px 6px; border-bottom: 1px solid #e6e9ef; vertical-align: top; }
        .lines tr.edge td { background: #f4f6f9; font-weight: bold; }
        .num { text-align: right; white-space: nowrap; }
        .summary { width: 50%; margin-left: 50%; margin-top: 12px; }
        .summary td { padding: 4px 6px; }
        .summary .grand td { font-size: 12px; font-weight: bold; border-top: 2px solid {{ $color }}; }
        .footer { margin-top: 24px; border-top: 1px solid #dde2ea; padding-top: 6px; font-size: 9px; color: #5b6575; text-align: center; white-space: pre-line; }
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
                    <div class="title">STATEMENT OF ACCOUNT</div>
                    <table class="meta" style="margin-top: 8px;">
                        <tr><td class="label">Period</td><td>{{ $settings->formatDate($statement['from']) }} – {{ $settings->formatDate($statement['to']) }}</td></tr>
                        <tr><td class="label">Customer code</td><td>{{ $customer->code }}</td></tr>
                        <tr><td class="label">Printed</td><td>{{ $settings->formatDate(now()) }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table style="margin-top: 16px;">
            <tr>
                <td style="width: 55%; vertical-align: top;">
                    <div class="box">
                        <div class="section-title">Customer</div>
                        <strong>{{ $customer->name }}</strong><br>
                        @if ($customer->contact_person)Attn: {{ $customer->contact_person }}<br>@endif
                        @if ($customer->address){!! nl2br(e($customer->address)) !!}<br>@endif
                        @if ($customer->city){{ $customer->city }}<br>@endif
                        @if ($customer->phone)Tel: {{ $customer->phone }}<br>@endif
                        @if ($customer->tin)TIN: {{ $customer->tin }}@endif
                    </div>
                </td>
                <td></td>
            </tr>
        </table>

        <table class="lines">
            <thead>
                <tr>
                    <th style="width: 12%;">Date</th>
                    <th style="width: 14%;">Type</th>
                    <th style="width: 15%;">Reference</th>
                    <th>Description</th>
                    <th class="num" style="width: 13%;">Debit</th>
                    <th class="num" style="width: 13%;">Credit</th>
                    <th class="num" style="width: 14%;">Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr class="edge">
                    <td>{{ $settings->formatDate($statement['from']) }}</td>
                    <td colspan="5">Opening balance</td>
                    <td class="num">{{ $money($statement['opening']) }}</td>
                </tr>
                @forelse ($statement['lines'] as $line)
                    <tr>
                        <td>{{ $settings->formatDate($line['date']) }}</td>
                        <td>{{ $line['type'] }}</td>
                        <td>{{ $line['reference'] }}</td>
                        <td class="muted">{{ $line['description'] }}</td>
                        <td class="num">{{ $line['debit'] !== null ? $money($line['debit']) : '' }}</td>
                        <td class="num">{{ $line['credit'] !== null ? $money($line['credit']) : '' }}</td>
                        <td class="num">{{ $money($line['balance']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted" style="text-align: center;">No transactions in this period.</td></tr>
                @endforelse
                <tr class="edge">
                    <td>{{ $settings->formatDate($statement['to']) }}</td>
                    <td colspan="5">Closing balance</td>
                    <td class="num">{{ $money($statement['closing']) }}</td>
                </tr>
            </tbody>
        </table>

        <table class="summary">
            <tr><td class="muted">Opening balance</td><td class="num">{{ $money($statement['opening']) }}</td></tr>
            <tr><td class="muted">Invoiced and charged</td><td class="num">{{ $money($statement['total_debit']) }}</td></tr>
            <tr><td class="muted">Paid and credited</td><td class="num">-{{ $money($statement['total_credit']) }}</td></tr>
            <tr class="grand"><td>Amount due</td><td class="num">{{ $money($statement['closing']) }}</td></tr>
        </table>

        @if ($organization['bank_details'])
            <div style="margin-top: 18px;">
                <div class="section-title">Payment details</div>
                <div style="white-space: pre-line;">{{ $organization['bank_details'] }}</div>
            </div>
        @endif

        @if ($organization['document_footer'])
            <div class="footer">{{ $organization['document_footer'] }}</div>
        @endif
    </div>
</body>
</html>
