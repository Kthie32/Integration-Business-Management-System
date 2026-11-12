@inject('settings', App\Services\System\SettingsService::class)
@php
    $money = fn ($amount) => $settings->formatMoney((string) $amount);
    $quantity = App\Support\Quantity::class;
    $color = $organization['brand_color'];
    $status = $requisition->status;
    $watermark = match ($status) {
        App\Enums\RequisitionStatus::Draft => 'DRAFT',
        App\Enums\RequisitionStatus::Rejected => 'REJECTED',
        App\Enums\RequisitionStatus::Cancelled => 'CANCELLED',
        App\Enums\RequisitionStatus::Submitted, App\Enums\RequisitionStatus::InApproval, App\Enums\RequisitionStatus::Returned => 'NOT APPROVED',
        default => null,
    };
    $round = $requisition->currentApprovalRound();
    $trail = $requisition->approvals->where('round', $round);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Requisition {{ $requisition->displayNumber() }} · {{ $organization['name'] }}</title>
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
        .lines th { background: {{ $color }}; color: #fff; text-align: left; padding: 5px 6px; font-weight: bold; }
        .lines td { padding: 5px 6px; border-bottom: 1px solid #e6e9ef; vertical-align: top; }
        .lines .num { text-align: right; white-space: nowrap; }
        .lines tfoot td { font-weight: bold; border-bottom: 0; }
        .box { border: 1px solid #dde2ea; padding: 8px 10px; }
        .signatures td { width: 33%; padding: 0 10px 0 0; vertical-align: bottom; }
        .signatures .line { border-top: 1px solid #1b2430; padding-top: 4px; margin-top: 34px; }
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
                        @if ($organization['phone'])Tel: {{ $organization['phone'] }}<br>@endif
                        @if ($organization['email']){{ $organization['email'] }}@endif
                    </div>
                </td>
                <td style="width: 45%;">
                    <div class="title">REQUISITION</div>
                    <table class="meta" style="margin-top: 8px;">
                        <tr><td class="label">Number</td><td><strong>{{ $requisition->displayNumber() }}</strong></td></tr>
                        <tr><td class="label">Status</td><td>{{ $status->label() }}</td></tr>
                        <tr><td class="label">Department</td><td>{{ $requisition->department?->name }}</td></tr>
                        <tr><td class="label">Requested by</td><td>{{ $requisition->requester?->name }}</td></tr>
                        @if ($requisition->submitted_at)
                            <tr><td class="label">Submitted</td><td>{{ $settings->formatDate($requisition->submitted_at) }}</td></tr>
                        @endif
                        <tr><td class="label">Priority</td><td>{{ $requisition->priority->label() }}</td></tr>
                        @if ($requisition->required_by_date)
                            <tr><td class="label">Needed by</td><td>{{ $settings->formatDate($requisition->required_by_date) }}</td></tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>

        <div class="section">
            <h2>Purpose</h2>
            <div class="box" style="white-space: pre-line">{{ $requisition->purpose }}</div>
        </div>

        <div class="section">
            <table class="lines">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th>Description</th>
                        <th class="num">Quantity</th>
                        @if ($requisition->approved_at)<th class="num">Approved</th>@endif
                        <th class="num">Est. unit cost</th>
                        <th class="num">Est. total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requisition->items as $line)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $line->description }}</td>
                            <td class="num">{{ $quantity::format($line->quantity) }} {{ $line->unit }}</td>
                            @if ($requisition->approved_at)<td class="num">{{ $quantity::format($line->approved_quantity) }}</td>@endif
                            <td class="num">{{ $money($line->estimated_unit_cost) }}</td>
                            <td class="num">{{ $money($line->estimated_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="{{ $requisition->approved_at ? 5 : 4 }}" class="num">Estimated total</td>
                        <td class="num">{{ $money($requisition->estimated_total) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="section">
            <h2>Approval trail</h2>
            @if ($trail->isEmpty())
                <div class="muted">Not submitted for approval.</div>
            @else
                <table class="lines">
                    <thead>
                        <tr><th>Step</th><th>Decided by</th><th>Decision</th><th>Date</th><th>Comment</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($trail as $approval)
                            <tr>
                                <td>{{ $approval->sequence }}. {{ $approval->name }}</td>
                                <td>{{ $approval->decider?->name ?? '—' }}</td>
                                <td>{{ $approval->status->label() }}</td>
                                <td>{{ $approval->decided_at ? $settings->formatDate($approval->decided_at) : '—' }}</td>
                                <td>{{ $approval->comment }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <table class="signatures section" style="margin-top: 26px;">
            <tr>
                <td><div class="line">{{ $requisition->requester?->name }}<br><span class="muted">Requested by</span></div></td>
                <td><div class="line">&nbsp;<br><span class="muted">Approved by</span></div></td>
                <td><div class="line">&nbsp;<br><span class="muted">Received by</span></div></td>
            </tr>
        </table>

        @if ($organization['document_footer'])
            <div class="footer">{{ $organization['document_footer'] }}</div>
        @endif
    </div>
</body>
</html>
