<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>Přehled výdajů - {{ $period['start'] }} - {{ $period['end'] }}</title>
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
        
        .period-info {
            margin: 30px 0;
            background-color: #f8f9fa;
            padding: 15px;
            border: 1px solid #ddd;
        }
        
        .summary-section {
            margin: 20px 0;
        }
        
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .summary-table th,
        .summary-table td {
            border: 1px solid #ddd;
            padding: 8px;
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
        
        .expenses-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 10px;
        }
        
        .expenses-table th,
        .expenses-table td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        
        .expenses-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            text-align: center;
        }
        
        .expenses-table .number-cell {
            text-align: right;
        }
        
        .expenses-table .center-cell {
            text-align: center;
        }
        
        .breakdown-section {
            margin-top: 30px;
            page-break-inside: avoid;
        }
        
        .breakdown-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 11px;
        }
        
        .breakdown-table th,
        .breakdown-table td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        
        .breakdown-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            text-align: center;
        }
        
        .breakdown-table .number-cell {
            text-align: right;
        }
        
        .section-title {
            font-weight: bold;
            font-size: 16px;
            margin: 25px 0 10px 0;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 3px;
        }
        
        .footer {
            margin-top: 50px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
            font-size: 10px;
            color: #666;
        }
        
        .page-break {
            page-break-after: always;
        }
        
        .currency {
            font-weight: bold;
        }
        
        .highlight-row {
            background-color: #e9ecef;
            font-weight: bold;
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
                <strong>DIČ:</strong> {{ config('company.dic') }}
            </div>
        </div>
        
        <div class="report-info">
            <div class="report-title">PŘEHLED VÝDAJŮ</div>
            <div style="font-size: 14px; margin: 10px 0;">
                Období: {{ $period['start'] }} - {{ $period['end'] }}
            </div>
            <div style="font-size: 12px;">
                Vytvořeno: {{ now()->format('d.m.Y H:i') }}
            </div>
        </div>
        <div class="clearfix"></div>
    </div>

    <div class="period-info">
        <strong>Reportované období:</strong> {{ $period['start'] }} - {{ $period['end'] }}
        @if(isset($period['year']))
            <br><strong>Rok:</strong> {{ $period['year'] }}
        @endif
        @if(isset($period['month_year']))
            <br><strong>Měsíc/Rok:</strong> {{ $period['month_year'] }}
        @endif
    </div>

    <!-- Summary Section -->
    <div class="section-title">Souhrn</div>
    <table class="summary-table">
        <tr>
            <td><strong>Celkový počet výdajů:</strong></td>
            <td class="number-cell">{{ $summary['total_expenses'] }}</td>
        </tr>
        <tr>
            <td><strong>Celková částka (bez DPH):</strong></td>
            <td class="number-cell currency">{{ number_format($summary['total_amount'], 2, ',', ' ') }} Kč</td>
        </tr>
        <tr>
            <td><strong>DPH celkem:</strong></td>
            <td class="number-cell currency">{{ number_format($summary['total_vat'], 2, ',', ' ') }} Kč</td>
        </tr>
        <tr class="highlight-row">
            <td><strong>Celková částka (s DPH):</strong></td>
            <td class="number-cell currency">{{ number_format($summary['total_with_vat'], 2, ',', ' ') }} Kč</td>
        </tr>
        <tr>
            <td><strong>Průměrná částka výdaje:</strong></td>
            <td class="number-cell currency">{{ number_format($summary['average_amount'], 2, ',', ' ') }} Kč</td>
        </tr>
    </table>

    @if(count($expenses) > 0)
    <!-- Expenses Detail -->
    <div class="section-title">Detail výdajů</div>
    <table class="expenses-table">
        <thead>
            <tr>
                <th style="width: 8%;">Datum</th>
                <th style="width: 32%;">Popis</th>
                <th style="width: 18%;">Kategorie</th>
                <th style="width: 12%;">Částka</th>
                <th style="width: 8%;">DPH %</th>
                <th style="width: 10%;">DPH</th>
                <th style="width: 12%;">Celkem</th>
            </tr>
        </thead>
        <tbody>
            @foreach($expenses as $expense)
            <tr>
                <td class="center-cell">{{ \Carbon\Carbon::parse($expense->expense_date)->format('d.m.Y') }}</td>
                <td>{{ $expense->description }}</td>
                <td>{{ $expense->category->name ?? 'Nezařazeno' }}</td>
                <td class="number-cell">{{ number_format($expense->amount, 2, ',', ' ') }} Kč</td>
                <td class="center-cell">{{ number_format($expense->vat_rate, 0) }}%</td>
                <td class="number-cell">{{ number_format($expense->amount * ($expense->vat_rate / 100), 2, ',', ' ') }} Kč</td>
                <td class="number-cell">{{ number_format($expense->amount + ($expense->amount * ($expense->vat_rate / 100)), 2, ',', ' ') }} Kč</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- Category Breakdown -->
    @if(count($category_breakdown) > 0)
    <div class="breakdown-section">
        <div class="section-title">Přehled dle kategorií</div>
        <table class="breakdown-table">
            <thead>
                <tr>
                    <th>Kategorie</th>
                    <th>Počet</th>
                    <th>Částka (bez DPH)</th>
                    <th>DPH</th>
                    <th>Celkem (s DPH)</th>
                    <th>Podíl %</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalAmount = $summary['total_amount'];
                @endphp
                @foreach($category_breakdown as $category => $data)
                <tr>
                    <td>{{ $category }}</td>
                    <td class="center-cell">{{ $data['count'] }}</td>
                    <td class="number-cell">{{ number_format($data['total_amount'], 2, ',', ' ') }} Kč</td>
                    <td class="number-cell">{{ number_format($data['total_vat'], 2, ',', ' ') }} Kč</td>
                    <td class="number-cell">{{ number_format($data['total_with_vat'], 2, ',', ' ') }} Kč</td>
                    <td class="number-cell">{{ $totalAmount > 0 ? number_format(($data['total_amount'] / $totalAmount) * 100, 1) : 0 }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- VAT Breakdown -->
    @if(count($vat_breakdown) > 0)
    <div class="breakdown-section">
        <div class="section-title">Přehled dle sazby DPH</div>
        <table class="breakdown-table">
            <thead>
                <tr>
                    <th>Sazba DPH</th>
                    <th>Počet</th>
                    <th>Základ daně</th>
                    <th>DPH</th>
                    <th>Celkem</th>
                </tr>
            </thead>
            <tbody>
                @foreach($vat_breakdown as $vatRate => $data)
                <tr>
                    <td class="center-cell">{{ number_format($vatRate, 0) }}%</td>
                    <td class="center-cell">{{ $data['count'] }}</td>
                    <td class="number-cell">{{ number_format($data['total_amount'], 2, ',', ' ') }} Kč</td>
                    <td class="number-cell">{{ number_format($data['total_vat'], 2, ',', ' ') }} Kč</td>
                    <td class="number-cell">{{ number_format($data['total_with_vat'], 2, ',', ' ') }} Kč</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Monthly Breakdown (if available) -->
    @if(isset($monthly_breakdown) && count($monthly_breakdown) > 0)
    <div class="breakdown-section">
        <div class="section-title">Měsíční přehled</div>
        <table class="breakdown-table">
            <thead>
                <tr>
                    <th>Měsíc</th>
                    <th>Počet</th>
                    <th>Částka (bez DPH)</th>
                    <th>DPH</th>
                    <th>Celkem (s DPH)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($monthly_breakdown as $month)
                <tr>
                    <td>{{ $month['month_name'] }}</td>
                    <td class="center-cell">{{ $month['count'] }}</td>
                    <td class="number-cell">{{ number_format($month['total_amount'], 2, ',', ' ') }} Kč</td>
                    <td class="number-cell">{{ number_format($month['total_vat'], 2, ',', ' ') }} Kč</td>
                    <td class="number-cell">{{ number_format($month['total_with_vat'], 2, ',', ' ') }} Kč</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        <div style="float: left;">
            <div>Vygenerováno systémem: {{ config('company.name') }}</div>
            <div>Datum a čas: {{ now()->format('d.m.Y H:i:s') }}</div>
        </div>
        <div style="float: right;">
            <div>Přehled byl vygenerován elektronicky.</div>
        </div>
        <div class="clearfix"></div>
    </div>
</body>
</html>