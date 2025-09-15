<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>Výpis obchodních vztahů - {{ $client->company_name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
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
        
        .report-info {
            float: right;
            width: 40%;
            text-align: right;
        }
        
        .report-title {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .clearfix {
            clear: both;
        }
        
        .client-section {
            margin: 30px 0;
            background-color: #f8f9fa;
            padding: 15px;
            border: 1px solid #ddd;
        }
        
        .period-info {
            margin: 20px 0;
            background-color: #e9ecef;
            padding: 15px;
            border: 1px solid #ddd;
        }
        
        .section-title {
            font-weight: bold;
            font-size: 16px;
            margin: 25px 0 10px 0;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 3px;
        }
        
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .summary-table th,
        .summary-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        
        .summary-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        .summary-table .number-cell {
            text-align: right;
            font-weight: bold;
        }
        
        .summary-table .highlight-row {
            background-color: #e9ecef;
            font-weight: bold;
        }
        
        .invoices-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 11px;
        }
        
        .invoices-table th,
        .invoices-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .invoices-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            text-align: center;
        }
        
        .invoices-table .number-cell {
            text-align: right;
        }
        
        .invoices-table .center-cell {
            text-align: center;
        }
        
        .status-paid {
            color: #28a745;
            font-weight: bold;
        }
        
        .status-sent {
            color: #ffc107;
            font-weight: bold;
        }
        
        .status-draft {
            color: #6c757d;
            font-style: italic;
        }
        
        .status-overdue {
            color: #dc3545;
            font-weight: bold;
        }
        
        .currency {
            font-weight: bold;
        }
        
        .footer {
            margin-top: 50px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
            font-size: 10px;
            color: #666;
        }
        
        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        .info-box {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
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
        
        <div class="report-info">
            <div class="report-title">VÝPIS OBCHODNÍCH VZTAHŮ</div>
            <div style="font-size: 14px; margin: 10px 0;">
                {{ $client->company_name }}
            </div>
            <div style="font-size: 12px; margin: 5px 0;">
                Období: {{ $period['start'] }} - {{ $period['end'] }}
            </div>
            <div style="font-size: 12px;">
                Vytvořeno: {{ now()->format('d.m.Y H:i') }}
            </div>
        </div>
        <div class="clearfix"></div>
    </div>

    <!-- Client Information -->
    <div class="client-section">
        <div class="section-title">Údaje o odběrateli</div>
        <div style="float: left; width: 60%;">
            <div style="font-weight: bold; font-size: 16px; margin-bottom: 8px;">
                {{ $client->company_name }}
            </div>
            @if($client->contact_name)
                <div><strong>Kontaktní osoba:</strong> {{ $client->contact_name }}</div>
            @endif
            <div><strong>Adresa:</strong> {{ $client->full_address }}</div>
            @if($client->email)
                <div><strong>Email:</strong> {{ $client->email }}</div>
            @endif
            @if($client->phone)
                <div><strong>Telefon:</strong> {{ $client->phone }}</div>
            @endif
        </div>
        <div style="float: right; width: 35%;">
            @if($client->company_id)
                <div><strong>IČO:</strong> {{ $client->company_id }}</div>
            @endif
            @if($client->vat_id)
                <div><strong>DIČ/IČ DPH:</strong> {{ $client->vat_id }}</div>
            @endif
            <div style="margin-top: 10px;">
                <div><strong>Status:</strong> 
                    @if($client->is_active)
                        <span style="color: #28a745; font-weight: bold;">Aktivní</span>
                    @else
                        <span style="color: #dc3545; font-weight: bold;">Neaktivní</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="clearfix"></div>
    </div>

    <div class="period-info">
        <strong>Období výpisu:</strong> {{ $period['start'] }} - {{ $period['end'] }}
        <br><strong>Datum sestavení:</strong> {{ now()->format('d.m.Y H:i') }}
    </div>

    <!-- Summary Section -->
    <div class="section-title">Souhrn obchodních vztahů</div>
    <table class="summary-table">
        <tr>
            <td><strong>Celkově vyfakturováno:</strong></td>
            <td class="number-cell currency">{{ number_format($summary['total_invoiced'], 2, ',', ' ') }} Kč</td>
        </tr>
        <tr>
            <td><strong>Celkově zaplaceno:</strong></td>
            <td class="number-cell currency" style="color: #28a745;">{{ number_format($summary['total_paid'], 2, ',', ' ') }} Kč</td>
        </tr>
        <tr>
            <td><strong>Nezaplaceno (aktuální):</strong></td>
            <td class="number-cell currency" style="color: #ffc107;">{{ number_format($summary['total_outstanding'], 2, ',', ' ') }} Kč</td>
        </tr>
        @if($summary['overdue_amount'] > 0)
        <tr style="background-color: #f8d7da;">
            <td><strong>Po splatnosti:</strong></td>
            <td class="number-cell currency" style="color: #dc3545;">{{ number_format($summary['overdue_amount'], 2, ',', ' ') }} Kč</td>
        </tr>
        @endif
    </table>

    @if($summary['overdue_amount'] > 0)
        <div class="warning-box">
            <strong>Upozornění:</strong> Klient má po splatnosti částku {{ number_format($summary['overdue_amount'], 2, ',', ' ') }} Kč. 
            Doporučujeme neprodleně kontaktovat klienta ohledně úhrady.
        </div>
    @elseif($summary['total_outstanding'] > 0)
        <div class="info-box">
            <strong>Info:</strong> Klient má aktuálně nezaplaceno {{ number_format($summary['total_outstanding'], 2, ',', ' ') }} Kč 
            v rámci splatnosti.
        </div>
    @else
        <div class="info-box">
            <strong>Výborně:</strong> Klient má vyrovnané všechny závazky.
        </div>
    @endif

    @if(count($invoices) > 0)
    <!-- Invoice Details -->
    <div class="section-title">Seznam faktur v období</div>
    <table class="invoices-table">
        <thead>
            <tr>
                <th style="width: 12%;">Číslo faktury</th>
                <th style="width: 10%;">Datum vystavení</th>
                <th style="width: 10%;">Datum splatnosti</th>
                <th style="width: 10%;">Celkem</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 8%;">Dní po splatnosti</th>
                <th style="width: 40%;">Poznámky</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalInvoiced = 0;
                $totalPaid = 0;
                $totalOutstanding = 0;
            @endphp
            @foreach($invoices as $invoice)
            @php
                $totalInvoiced += $invoice->total;
                if ($invoice->status === 'paid') {
                    $totalPaid += $invoice->total;
                } else {
                    $totalOutstanding += $invoice->total;
                }
                $daysOverdue = $invoice->due_date->isPast() && in_array($invoice->status, ['sent', 'draft']) 
                    ? now()->diffInDays($invoice->due_date) 
                    : 0;
            @endphp
            <tr>
                <td>{{ $invoice->invoice_number }}</td>
                <td class="center-cell">{{ $invoice->issue_date->format('d.m.Y') }}</td>
                <td class="center-cell">{{ $invoice->due_date->format('d.m.Y') }}</td>
                <td class="number-cell">{{ number_format($invoice->total, 2, ',', ' ') }} Kč</td>
                <td class="center-cell">
                    @switch($invoice->status)
                        @case('paid')
                            <span class="status-paid">Zaplaceno</span>
                            @break
                        @case('sent')
                            @if($daysOverdue > 0)
                                <span class="status-overdue">Po splatnosti</span>
                            @else
                                <span class="status-sent">Odesláno</span>
                            @endif
                            @break
                        @case('draft')
                            <span class="status-draft">Koncept</span>
                            @break
                        @default
                            <span>{{ ucfirst($invoice->status) }}</span>
                    @endswitch
                </td>
                <td class="center-cell">
                    @if($daysOverdue > 0)
                        <span style="color: #dc3545; font-weight: bold;">{{ $daysOverdue }}</span>
                    @else
                        -
                    @endif
                </td>
                <td>{{ $invoice->notes ?? '-' }}</td>
            </tr>
            @endforeach
            <tr style="background-color: #e9ecef; font-weight: bold;">
                <td colspan="3"><strong>CELKEM ZA OBDOBÍ:</strong></td>
                <td class="number-cell">{{ number_format($totalInvoiced, 2, ',', ' ') }} Kč</td>
                <td colspan="3"></td>
            </tr>
        </tbody>
    </table>
    @else
        <div class="info-box">
            <strong>Info:</strong> V zadaném období nebyly pro tohoto klienta vystaveny žádné faktury.
        </div>
    @endif

    <!-- Payment Analysis -->
    @if(count($invoices) > 0)
    <div class="section-title">Analýza platební morálky</div>
    @php
        $paidInvoices = $invoices->where('status', 'paid');
        $overdueInvoices = $invoices->filter(function($invoice) {
            return $invoice->due_date->isPast() && in_array($invoice->status, ['sent', 'draft']);
        });
        $paymentScore = $paidInvoices->count() > 0 ? 
            ($paidInvoices->count() / $invoices->count()) * 100 : 0;
    @endphp
    
    <table class="summary-table">
        <tr>
            <td><strong>Celkový počet faktur:</strong></td>
            <td class="number-cell">{{ $invoices->count() }}</td>
        </tr>
        <tr>
            <td><strong>Zaplacených faktur:</strong></td>
            <td class="number-cell" style="color: #28a745;">{{ $paidInvoices->count() }}</td>
        </tr>
        <tr>
            <td><strong>Po splatnosti:</strong></td>
            <td class="number-cell" style="color: #dc3545;">{{ $overdueInvoices->count() }}</td>
        </tr>
        <tr class="highlight-row">
            <td><strong>Skóre platební morálky:</strong></td>
            <td class="number-cell" 
                style="color: {{ $paymentScore >= 80 ? '#28a745' : ($paymentScore >= 60 ? '#ffc107' : '#dc3545') }};">
                {{ number_format($paymentScore, 1) }}%
            </td>
        </tr>
    </table>

    @if($paymentScore >= 80)
        <div class="info-box">
            <strong>Výborný klient:</strong> Platební morálka {{ number_format($paymentScore, 1) }}%. 
            Doporučujeme zachovat současné obchodní podmínky.
        </div>
    @elseif($paymentScore >= 60)
        <div class="warning-box">
            <strong>Problematický klient:</strong> Platební morálka {{ number_format($paymentScore, 1) }}%. 
            Zvažte změnu obchodních podmínek nebo kratší splatnost.
        </div>
    @else
        <div class="warning-box" style="background-color: #f8d7da; border-color: #f5c6cb;">
            <strong>Rizikový klient:</strong> Platební morálka {{ number_format($paymentScore, 1) }}%. 
            Doporučujeme přehodnotit obchodní vztah a požadovat platbu předem.
        </div>
    @endif
    @endif

    <div class="footer">
        <div style="float: left;">
            <div>Vygenerováno systémem: {{ config('company.name') }}</div>
            <div>Datum a čas: {{ now()->format('d.m.Y H:i:s') }}</div>
            <div>IČO: {{ config('company.ico') }} | DIČ: {{ config('company.dic') }}</div>
        </div>
        <div style="float: right;">
            <div>Výpis byl vygenerován elektronicky.</div>
            <div>Obsahuje obchodně citlivé informace.</div>
        </div>
        <div class="clearfix"></div>
    </div>
</body>
</html>