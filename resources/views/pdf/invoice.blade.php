@inject('settings', App\Services\System\SettingsService::class)
@php
    $quantity = App\Support\Quantity::class;
    $money = fn ($amount) => $settings->formatMoney((string) $amount);
    $color = $organization['brand_color'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Invoice {{ $invoice->displayNumber() }} · {{ $organization['name'] }}</title>
    <style>
        @page { margin: 18mm 15mm 20mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 10.5px; color: #1b2430; margin: 0; line-height: 1.45; }
        .page { max-width: 800px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .logo { max-height: 60px; max-width: 200px; margin-bottom: 6px; }
        .org-name { font-size: 16px; font-weight: bold; color: {{ $color }}; }
        .muted { color: #5b6575; }
        .title { font-size: 22px; font-weight: bold; color: {{ $color }}; text-align: right; letter-spacing: 1px; }
        .meta td { padding: 1px 0; }
        .meta td.label { color: #5b6575; text-align: right; padding-right: 8px; }
        .box { border: 1px solid #dde2ea; padding: 8px 10px; }
        .section-title { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #5b6575; margin-bottom: 3px; }
        .lines { margin-top: 16px; }
        .lines th { background: {{ $color }}; color: #fff; font-weight: bold; padding: 6px; text-align: left; font-size: 9.5px; }
        .lines td { padding: 6px; border-bottom: 1px solid #e6e9ef; vertical-align: top; }
        .lines tr:nth-child(even) td { background: #f7f9fc; }
        .num { text-align: right; white-space: nowrap; }
        .totals { width: 45%; margin-left: 55%; margin-top: 10px; }
        .totals td { padding: 4px 6px; }
        .totals .grand td { font-size: 13px; font-weight: bold; border-top: 2px solid {{ $color }}; }
        .notes { margin-top: 18px; }
        .notes p { white-space: pre-line; margin: 0 0 8px; }
        .signature { margin-top: 36px; width: 45%; }
        .signature .line { border-top: 1px solid #1b2430; padding-top: 4px; margin-top: 40px; }
        .footer { margin-top: 24px; border-top: 1px solid #dde2ea; padding-top: 6px; font-size: 9px; color: #5b6575; text-align: center; white-space: pre-line; }
        .watermark.paid { color: rgba(25, 135, 84, 0.14); }
        .watermark { position: fixed; top: 38%; left: 0; width: 100%; text-align: center; font-size: 90px; color: rgba(192, 57, 43, 0.12); transform: rotate(-30deg); z-index: -1; }
        .toolbar { background: #f4f6f9; border-bottom: 1px solid #dde2ea; padding: 10px; text-align: center; margin-bottom: 16px; font-family: system-ui, sans-serif; }
        .toolbar button { background: {{ $color }}; color: #fff; border: 0; border-radius: 6px; padding: 8px 18px; font-size: 14px; cursor: pointer; }
        @media print { .toolbar { display: none; } body { font-size: 11px; } }
        @media screen { body { background: #fff; } .page { padding: 0 16px 32px; } }
    </style>
</head>
<body>
    @unless ($forPdf)
        <div class="toolbar">
            <button type="button" onclick="window.print()">Print</button>
        </div>
    @endunless

    @if ($invoice->isDraft())
        <div class="watermark">DRAFT</div>
    @elseif ($invoice->status === App\Enums\InvoiceStatus::Void)
        <div class="watermark">VOID</div>
    @elseif ($invoice->status === App\Enums\InvoiceStatus::Paid)
        <div class="watermark paid">PAID</div>
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
                        @if ($organization['email']){{ $organization['email'] }}<br>@endif
                        @if ($organization['website']){{ $organization['website'] }}<br>@endif
                        @if ($organization['tin'])TIN: {{ $organization['tin'] }}@endif
                    </div>
                </td>
                <td style="width: 45%;">
                    <div class="title">{{ App\Support\Money::of((string) $invoice->tax_total)->isPositive() ? 'TAX INVOICE' : 'INVOICE' }}</div>
                    <table class="meta" style="margin-top: 8px;">
                        <tr><td class="label">Number</td><td><strong>{{ $invoice->displayNumber() }}</strong></td></tr>
                        <tr><td class="label">Date</td><td>{{ $settings->formatDate($invoice->invoice_date) }}</td></tr>
                        <tr><td class="label">Due date</td><td>{{ $settings->formatDate($invoice->due_date) }}</td></tr>
                        @if ($invoice->quotation?->number)
                            <tr><td class="label">Quotation</td><td>{{ $invoice->quotation->number }}</td></tr>
                        @endif
                        <tr><td class="label">Currency</td><td>{{ $invoice->currency }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table style="margin-top: 16px;">
            <tr>
                <td style="width: 55%; vertical-align: top;">
                    <div class="box">
                        <div class="section-title">Bill to</div>
                        <strong>{{ $invoice->customer->name }}</strong><br>
                        @if ($invoice->customer->contact_person)Attn: {{ $invoice->customer->contact_person }}<br>@endif
                        @if ($invoice->customer->address){!! nl2br(e($invoice->customer->address)) !!}<br>@endif
                        @if ($invoice->customer->city){{ $invoice->customer->city }}<br>@endif
                        @if ($invoice->customer->phone)Tel: {{ $invoice->customer->phone }}<br>@endif
                        @if ($invoice->customer->email){{ $invoice->customer->email }}<br>@endif
                        @if ($invoice->customer->tin)TIN: {{ $invoice->customer->tin }}@endif
                    </div>
                </td>
                <td></td>
            </tr>
        </table>

        <table class="lines">
            <thead>
                <tr>
                    <th style="width: 4%;">#</th>
                    <th>Description</th>
                    <th class="num" style="width: 11%;">Qty</th>
                    <th class="num" style="width: 15%;">Unit price</th>
                    <th class="num" style="width: 12%;">Discount</th>
                    <th class="num" style="width: 8%;">Tax</th>
                    <th class="num" style="width: 16%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $line)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $line->description }}</td>
                        <td class="num">{{ $quantity::format($line->quantity) }} {{ $line->unit }}</td>
                        <td class="num">{{ $money($line->unit_price) }}</td>
                        <td class="num">{{ App\Support\Money::of((string) $line->discount_amount)->plus((string) $line->document_discount_share)->isZero() ? '—' : $money(App\Support\Money::of((string) $line->discount_amount)->plus((string) $line->document_discount_share)) }}</td>
                        <td class="num">{{ rtrim(rtrim((string) $line->tax_rate, '0'), '.') }}%</td>
                        <td class="num">{{ $money($line->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr><td class="muted">Subtotal</td><td class="num">{{ $money($invoice->subtotal) }}</td></tr>
            @unless (App\Support\Money::of((string) $invoice->discount_total)->isZero())
                <tr><td class="muted">Discounts</td><td class="num">-{{ $money($invoice->discount_total) }}</td></tr>
            @endunless
            <tr><td class="muted">Tax</td><td class="num">{{ $money($invoice->tax_total) }}</td></tr>
            <tr class="grand"><td>Total</td><td class="num">{{ $money($invoice->total) }}</td></tr>
            @if (in_array($invoice->status, [App\Enums\InvoiceStatus::Issued, App\Enums\InvoiceStatus::PartiallyPaid, App\Enums\InvoiceStatus::Paid], true))
                <tr><td class="muted">Amount paid</td><td class="num">{{ $money($invoice->amount_paid) }}</td></tr>
                <tr><td><strong>Balance due</strong></td><td class="num"><strong>{{ $money($invoice->balance_due) }}</strong></td></tr>
            @endif
        </table>

        <div class="notes">
            @if ($invoice->notes)
                <div class="section-title">Notes</div>
                <p>{{ $invoice->notes }}</p>
            @endif
            @if ($invoice->terms)
                <div class="section-title">Terms and conditions</div>
                <p>{{ $invoice->terms }}</p>
            @endif
            @if ($organization['bank_details'])
                <div class="section-title">Payment details</div>
                <p>{{ $organization['bank_details'] }}</p>
            @endif
        </div>

        <div class="signature">
            <div class="line">
                {{ $organization['signatory_name'] ?: 'Authorised signature' }}
                @if ($organization['signatory_title'])<br><span class="muted">{{ $organization['signatory_title'] }}</span>@endif
            </div>
        </div>

        @if ($organization['document_footer'])
            <div class="footer">{{ $organization['document_footer'] }}</div>
        @endif
    </div>
</body>
</html>
