<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Faktura {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        
        .header {
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .company-info {
            float: left;
            width: 50%;
        }
        
        .invoice-info {
            float: right;
            width: 40%;
            text-align: right;
        }
        
        .invoice-title {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .clearfix {
            clear: both;
        }
        
        .billing-section {
            margin: 30px 0;
        }
        
        .billing-to {
            float: left;
            width: 50%;
        }
        
        .billing-from {
            float: right;
            width: 45%;
        }
        
        .section-title {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 8px;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 3px;
        }
        
        .invoice-details {
            margin: 30px 0;
            background-color: #f8f9fa;
            padding: 15px;
            border: 1px solid #ddd;
        }
        
        .detail-row {
            margin-bottom: 5px;
        }
        
        .detail-label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 11px;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .items-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            text-align: center;
        }
        
        .items-table .number-cell {
            text-align: right;
        }
        
        .items-table .center-cell {
            text-align: center;
        }
        
        .totals-section {
            float: right;
            width: 350px;
            margin-top: 20px;
        }
        
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .totals-table td {
            padding: 5px 10px;
            border: 1px solid #ddd;
        }
        
        .totals-table .label {
            font-weight: bold;
            background-color: #f8f9fa;
            width: 70%;
        }
        
        .totals-table .amount {
            text-align: right;
            width: 30%;
        }
        
        .total-row {
            font-weight: bold;
            font-size: 14px;
            background-color: #e9ecef !important;
        }
        
        .vat-summary {
            margin-top: 30px;
            clear: both;
        }
        
        .vat-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .vat-table th,
        .vat-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: right;
        }
        
        .vat-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            text-align: center;
        }
        
        .footer {
            margin-top: 50px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
            font-size: 10px;
            color: #666;
        }
        
        .payment-info {
            margin-top: 30px;
            background-color: #f8f9fa;
            padding: 15px;
            border: 1px solid #ddd;
        }
        
        .currency {
            font-weight: bold;
        }
        
        .page-break {
            page-break-after: always;
        }

        .exchange-rate-info {
            margin: 15px 0;
            padding: 10px;
            background-color: #e8f4fd;
            border: 1px solid #b8daff;
            border-radius: 3px;
            font-size: 11px;
        }

        .currency-note {
            font-style: italic;
            color: #666;
            font-size: 10px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    @php
        $currency = $invoice->currency ?? 'CZK';
        $exchangeRate = $invoice->exchange_rate ?? 1.0;
        $currencySymbol = $invoice->getCurrencySymbol();
        $showExchangeRate = $currency !== 'CZK' && $exchangeRate != 1.0;
    @endphp
    <div class="header">
        <div class="company-info">
            <div style="font-size: 16px; font-weight: bold; margin-bottom: 10px;">
                {{ config('company.name') }}
            </div>
            <div>{{ config('company.address') }}</div>
            <div>{{ config('company.city') }}</div>
            <div>{{ config('company.country') }}</div>
            <div style="margin-top: 8px;">
                <strong>IČO:</strong> {{ config('company.ico') }}<br>
                <strong>DIČ:</strong> {{ config('company.dic') }}<br>
                @if(config('company.vat_number'))
                    <strong>IČ DPH:</strong> {{ config('company.vat_number') }}
                @endif
            </div>
        </div>
        
        <div class="invoice-info">
            <div class="invoice-title">FAKTURA – DAŇOVÝ DOKLAD</div>
            <div style="font-size: 16px; font-weight: bold; margin: 10px 0;">
                č. {{ $invoice->invoice_number }}
            </div>
        </div>
        <div class="clearfix"></div>
    </div>

    <div class="billing-section">
        <div class="billing-to">
            <div class="section-title">Odběratel:</div>
            <div style="font-weight: bold; margin-bottom: 5px;">{{ $invoice->client->company_name }}</div>
            @if($invoice->client->contact_name)
                <div>{{ $invoice->client->contact_name }}</div>
            @endif
            <div>{{ $invoice->client->address }}</div>
            @if($invoice->client->city)
                <div>{{ $invoice->client->city }}@if($invoice->client->postal_code), {{ $invoice->client->postal_code }}@endif</div>
            @endif
            @if($invoice->client->country && $invoice->client->country !== 'Czech Republic')
                <div>{{ $invoice->client->country }}</div>
            @endif
            <div style="margin-top: 8px;">
                @if($invoice->client->company_id)
                    <strong>IČO:</strong> {{ $invoice->client->company_id }}<br>
                @endif
                @if($invoice->client->vat_id)
                    @if(str_starts_with(strtoupper($invoice->client->vat_id), 'CZ'))
                        <strong>IČ DPH:</strong> {{ $invoice->client->vat_id }}
                    @else
                        <strong>DIČ:</strong> {{ $invoice->client->vat_id }}
                    @endif
                @endif
            </div>
        </div>
        <div class="clearfix"></div>
    </div>

    <div class="invoice-details">
        <div class="detail-row">
            <span class="detail-label">Datum vystavení:</span>
            {{ $invoice->issue_date->format('d.m.Y') }}
        </div>
        <div class="detail-row">
            <span class="detail-label">Datum splatnosti:</span>
            {{ $invoice->due_date->format('d.m.Y') }}
        </div>
        <div class="detail-row">
            <span class="detail-label">Datum zdanitelného plnění:</span>
            {{ $invoice->issue_date->format('d.m.Y') }}
        </div>
        <div class="detail-row">
            <span class="detail-label">Forma úhrady:</span>
            Bankovní převod
        </div>
        @if($showExchangeRate)
        <div class="detail-row">
            <span class="detail-label">Měna faktury:</span>
            {{ $currency }}
        </div>
        <div class="detail-row">
            <span class="detail-label">Směnný kurz:</span>
            1 {{ $currency }} = {{ number_format($exchangeRate, 4, ',', ' ') }} CZK
        </div>
        @endif
    </div>

    @if($showExchangeRate)
    <div class="exchange-rate-info">
        <strong>Informace o směnném kurzu:</strong><br>
        Faktura je vystavena v měně {{ $currency }}. Směnný kurz {{ number_format($exchangeRate, 4, ',', ' ') }} CZK za 1 {{ $currency }}
        byl stanoven ke dni vystavení faktury podle kurzu České národní banky.
        @if($currency !== 'CZK')
        <div class="currency-note">
            Pro účely DPH a účetnictví je částka přepočtena na CZK: {{ number_format($invoice->getTotalInCzk(), 2, ',', ' ') }} CZK
        </div>
        @endif
    </div>
    @endif

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 45%;">Popis</th>
                <th style="width: 10%;">Množství</th>
                <th style="width: 12%;">Jednotková cena ({{ $currency }})</th>
                <th style="width: 8%;">DPH %</th>
                <th style="width: 10%;">Cena bez DPH ({{ $currency }})</th>
                <th style="width: 10%;">Celkem s DPH ({{ $currency }})</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $index => $item)
            <tr>
                <td class="center-cell">{{ $index + 1 }}</td>
                <td>{{ $item->description }}</td>
                <td class="center-cell">{{ $item->quantity }}</td>
                <td class="number-cell">{{ number_format($item->unit_price, 2, ',', ' ') }} {{ $currencySymbol }}</td>
                <td class="center-cell">{{ number_format($item->vat_rate, 0) }}%</td>
                <td class="number-cell">{{ number_format($item->subtotal, 2, ',', ' ') }} {{ $currencySymbol }}</td>
                <td class="number-cell">{{ number_format($item->total, 2, ',', ' ') }} {{ $currencySymbol }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Reverse Charge Notice -->
    @if($invoice->isReverseChargeApplicable())
    <div style="margin: 20px 0; padding: 15px; background-color: #fff3cd; border: 2px solid #ffeaa7; border-radius: 5px;">
        <div style="font-weight: bold; font-size: 14px; color: #856404; margin-bottom: 5px;">
            PŘENESENÍ DAŇOVÉ POVINNOSTI
        </div>
        <div style="font-size: 12px; color: #856404;">
            {{ $invoice->getReverseChargeStatement() }}
        </div>
    </div>
    @endif

    <!-- VAT Summary -->
    <div class="vat-summary">
        <div class="section-title">Rekapitulace DPH:</div>
        <table class="vat-table">
            <thead>
                <tr>
                    <th>Sazba DPH</th>
                    <th>Základ daně ({{ $currency }})</th>
                    <th>DPH ({{ $currency }})</th>
                    <th>Celkem ({{ $currency }})</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $vatBreakdown = [];
                    foreach($invoice->items as $item) {
                        $rate = $item->vat_rate;
                        if (!isset($vatBreakdown[$rate])) {
                            $vatBreakdown[$rate] = ['base' => 0, 'vat' => 0, 'total' => 0];
                        }
                        $vatBreakdown[$rate]['base'] += $item->subtotal;
                        $vatBreakdown[$rate]['vat'] += $item->vat_amount;
                        $vatBreakdown[$rate]['total'] += $item->total;
                    }
                @endphp
                
                @foreach($vatBreakdown as $rate => $amounts)
                <tr>
                    <td>{{ number_format($rate, 0) }}%</td>
                    <td>{{ number_format($amounts['base'], 2, ',', ' ') }} {{ $currencySymbol }}</td>
                    <td>{{ number_format($amounts['vat'], 2, ',', ' ') }} {{ $currencySymbol }}</td>
                    <td>{{ number_format($amounts['total'], 2, ',', ' ') }} {{ $currencySymbol }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="totals-section">
        <table class="totals-table">
            <tr>
                <td class="label">Cena bez DPH:</td>
                <td class="amount currency">{{ number_format($invoice->subtotal, 2, ',', ' ') }} {{ $currencySymbol }}</td>
            </tr>
            <tr>
                <td class="label">DPH celkem:</td>
                <td class="amount currency">{{ number_format($invoice->vat_amount, 2, ',', ' ') }} {{ $currencySymbol }}</td>
            </tr>
            <tr class="total-row">
                <td class="label">Celkem k úhradě:</td>
                <td class="amount currency">{{ number_format($invoice->total, 2, ',', ' ') }} {{ $currencySymbol }}</td>
            </tr>
            @if($showExchangeRate)
            <tr style="border-top: 2px solid #333;">
                <td class="label" style="font-size: 11px; background-color: #f1f3f4;">Ekvivalent v CZK:</td>
                <td class="amount" style="font-size: 11px; background-color: #f1f3f4;">{{ number_format($invoice->getTotalInCzk(), 2, ',', ' ') }} Kč</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="clearfix"></div>

    @if(config('company.bank_account') || config('company.iban'))
    <div class="payment-info">
        <div class="section-title">Platební údaje:</div>
        @if(config('company.bank_account'))
            <div><strong>Číslo účtu:</strong> {{ config('company.bank_account') }}</div>
        @endif
        @if(config('company.iban'))
            <div><strong>IBAN:</strong> {{ config('company.iban') }}</div>
        @endif
        @if(config('company.swift'))
            <div><strong>SWIFT/BIC:</strong> {{ config('company.swift') }}</div>
        @endif
        <div><strong>Variabilní symbol:</strong> {{ $invoice->invoice_number }}</div>
    </div>
    @endif

    @if($invoice->notes)
    <div style="margin-top: 30px;">
        <div class="section-title">Poznámky:</div>
        <div>{{ $invoice->notes }}</div>
    </div>
    @endif

    <div class="footer">
        <div style="float: left;">
            <div>Vystavil: {{ config('company.name') }}</div>
            <div>Datum: {{ now()->format('d.m.Y') }}</div>
        </div>
        <div style="float: right;">
            <div>Faktura byla vystavena elektronicky a je platná bez podpisu.</div>
        </div>
        <div class="clearfix"></div>
    </div>
</body>
</html>