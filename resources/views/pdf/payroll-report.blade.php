@inject('settings', App\Services\System\SettingsService::class)
@php
    $color = $organization['brand_color'];
    $cell = fn (int $index, string $value) => $index >= $report['numeric_from'] && is_numeric($value) ? $settings->formatMoney($value) : $value;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $report['title'] }} · {{ $organization['name'] }}</title>
    <style>
        @page { margin: 12mm 10mm 14mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8.5px; color: #1b2430; margin: 0; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }
        .org-name { font-size: 14px; font-weight: bold; color: {{ $color }}; }
        .title { font-size: 13px; font-weight: bold; margin: 4px 0 2px; }
        .muted { color: #5b6575; }
        .lines th { background: {{ $color }}; color: #fff; text-align: left; padding: 4px 5px; font-weight: bold; }
        .lines td { padding: 4px 5px; border-bottom: 1px solid #e6e9ef; }
        .lines .num { text-align: right; white-space: nowrap; }
        .lines tfoot td { font-weight: bold; border-top: 1px solid #1b2430; border-bottom: 0; }
        .watermark { position: fixed; top: 38%; left: 0; width: 100%; text-align: center; font-size: 60px; color: rgba(192, 57, 43, 0.14); transform: rotate(-20deg); z-index: -1; }
        .toolbar { background: #f4f6f9; border-bottom: 1px solid #dde2ea; padding: 10px; text-align: center; margin-bottom: 16px; font-family: system-ui, sans-serif; }
        .toolbar button { background: {{ $color }}; color: #fff; border: 0; border-radius: 6px; padding: 8px 18px; font-size: 14px; cursor: pointer; }
        @media print { .toolbar { display: none; } }
        @media screen { body { padding: 0 16px 32px; font-size: 12px; } }
    </style>
</head>
<body>
    @unless ($forPdf)
        <div class="toolbar"><button type="button" onclick="window.print()">Print</button></div>
    @endunless

    @unless ($run->isLocked())
        <div class="watermark">NOT APPROVED</div>
    @endunless

    <div class="org-name">{{ $organization['name'] }}</div>
    <div class="title">{{ $report['title'] }}</div>
    <div class="muted" style="margin-bottom: 8px;">
        Run {{ $run->displayNumber() }} · {{ $run->status->label() }}
        @if ($run->approved_at) · approved {{ $settings->formatDate($run->approved_at) }}@endif
        · printed {{ $settings->formatDate(now()) }}
    </div>

    <table class="lines">
        <thead>
            <tr>
                @foreach ($report['headings'] as $index => $heading)
                    <th @class(['num' => $index >= $report['numeric_from']])>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($report['rows'] as $row)
                <tr>
                    @foreach ($row as $index => $value)
                        <td @class(['num' => $index >= $report['numeric_from']])>{{ $cell($index, $value) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                @foreach ($report['totals'] as $index => $value)
                    <td @class(['num' => $index >= $report['numeric_from']])>{{ $cell($index, $value) }}</td>
                @endforeach
            </tr>
        </tfoot>
    </table>
</body>
</html>
