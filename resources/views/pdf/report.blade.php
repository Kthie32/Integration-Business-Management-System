@inject('settings', App\Services\System\SettingsService::class)
@php
    $color = $organization['brand_color'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $report->title() }} · {{ $organization['name'] }}</title>
    <style>
        @page { margin: 12mm 10mm 14mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8.5px; color: #1b2430; margin: 0; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .org-name { font-size: 14px; font-weight: bold; color: {{ $color }}; }
        .title { font-size: 13px; font-weight: bold; margin: 6px 0 2px; }
        .muted { color: #5b6575; }
        .summary { margin: 8px 0 10px; }
        .summary td { border: 1px solid #e6e9ef; padding: 5px 6px; width: 1%; }
        .summary .label { color: #5b6575; font-size: 7.5px; text-transform: uppercase; }
        .summary .value { font-size: 10px; font-weight: bold; }
        .lines th { background: {{ $color }}; color: #fff; text-align: left; padding: 4px 5px; font-weight: bold; }
        .lines td { padding: 4px 5px; border-bottom: 1px solid #e6e9ef; }
        .lines .num { text-align: right; white-space: nowrap; }
        .lines tfoot td { font-weight: bold; border-top: 1px solid #1b2430; border-bottom: 0; }
        .notes { margin-top: 8px; }
        .footer { margin-top: 12px; border-top: 1px solid #e6e9ef; padding-top: 4px; }
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

    <table class="header">
        <tr>
            <td>
                <div class="org-name">{{ $organization['name'] }}</div>
                @if ($organization['address'])<div class="muted">{{ $organization['address'] }}</div>@endif
            </td>
            @if ($organization['logo'])
                <td style="text-align: right;"><img src="{{ $organization['logo'] }}" alt="" style="max-height: 40px;"></td>
            @endif
        </tr>
    </table>

    <div class="title">{{ $report->title() }}</div>
    <div class="muted">{{ $subtitle }} · printed {{ $settings->formatDate(now()) }} by {{ auth()->user()?->name }}</div>

    @if ($result->summary !== [])
        <table class="summary">
            <tr>
                @foreach ($result->summary as $stat)
                    <td>
                        <div class="label">{{ $stat['label'] }}</div>
                        <div class="value">{{ $formatter->value($stat['type'], $stat['value']) }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    <table class="lines">
        <thead>
            <tr>
                @foreach ($result->columns as $column)
                    <th @class(['num' => $column->isNumeric()])>{{ $column->label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($result->rows as $row)
                <tr>
                    @foreach ($result->columns as $column)
                        <td @class(['num' => $column->isNumeric()])>{{ $formatter->cell($column, $row[$column->key] ?? null) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($result->columns) }}" class="muted">No records match these filters.</td></tr>
            @endforelse
        </tbody>
        @if ($result->totals !== null && $result->rows !== [])
            <tfoot>
                <tr>
                    @foreach ($result->columns as $column)
                        <td @class(['num' => $column->isNumeric()])>
                            @if (array_key_exists($column->key, $result->totals))
                                {{ $column->isNumeric() ? $formatter->cell($column, $result->totals[$column->key]) : $result->totals[$column->key] }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>

    @foreach ($result->notes as $note)
        <div class="notes muted">{{ $note }}</div>
    @endforeach

    @if ($organization['document_footer'])
        <div class="footer muted">{{ $organization['document_footer'] }}</div>
    @endif
</body>
</html>
