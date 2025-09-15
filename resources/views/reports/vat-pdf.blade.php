<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>Přehled DPH - {{ $period['start'] }} - {{ $period['end'] }}</title>
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
        
        .section-title {
            font-weight: bold;
            font-size: 16px;
            margin: 25px 0 10px 0;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 3px;
        }
        
        .vat-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .vat-table th,
        .vat-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        
        .vat-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            text-align: center;
        }
        
        .vat-table .number-cell {
            text-align: right;
            font-weight: bold;
        }
        
        .vat-table .center-cell {
            text-align: center;
        }
        
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background-color: #f8f9fa;
        }
        
        .summary-table th,
        .summary-table td {
            border: 1px solid #ddd;
            padding: 12px;
        }
        
        .summary-table th {
            background-color: #e9ecef;
            font-weight: bold;
            text-align: center;
        }
        
        .summary-table .number-cell {
            text-align: right;
            font-weight: bold;
        }
        
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 10px;
        }
        
        .detail-table th,
        .detail-table td {
            border: 1px solid #ddd;
            padding: 6px;
        }
        
        .detail-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            text-align: center;
        }
        
        .detail-table .number-cell {
            text-align: right;
        }
        
        .detail-table .center-cell {
            text-align: center;
        }
        
        .highlight-positive {
            color: #28a745;
            font-weight: bold;
        }
        
        .highlight-negative {
            color: #dc3545;
            font-weight: bold;
        }
        
        .footer {
            margin-top: 50px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
            font-size: 10px;
            color: #666;
        }
        
        .currency {
            font-weight: bold;
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
            <div class="report-title">PŘEHLED DPH</div>
            <div style="font-size: 14px; margin: 10px 0;">
                @if($quarter)
                    {{ $quarter }}. čtvrtletí {{ $year }}
                @else
                    Rok {{ $year }}
                @endif
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

    <div class="period-info">
        <strong>Období přehledu:</strong> {{ $period['start'] }} - {{ $period['end'] }}
        @if($quarter)
            <br><strong>Čtvrtletí:</strong> {{ $quarter }}/{{ $year }}
        @else
            <br><strong>Celý rok:</strong> {{ $year }}
        @endif
        <br><strong>Datum sestavení:</strong> {{ now()->format('d.m.Y') }}
    </div>

    @if(config('company.vat_number'))
        <div class="info-box">
            <strong>Plátce DPH:</strong> Ano (IČ DPH: {{ config('company.vat_number') }})
        </div>
    @else
        <div class="warning-box">
            <strong>Upozornění:</strong> Nejste registrováni jako plátce DPH. Tento přehled slouží pouze pro interní účely.
        </div>
    @endif

    <!-- VAT Summary -->
    <div class="section-title">Souhrn DPH</div>
    <table class="summary-table">
        <thead>
            <tr>
                <th>Sazba DPH</th>
                <th>DPH na výstupu<br>(z faktur)</th>
                <th>DPH na vstupu<br>(z výdajů)</th>
                <th>Rozdíl DPH<br>(k doplatku/vrácení)</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalOutput = 0;
                $totalInput = 0;
                $totalNet = 0;
            @endphp
            @foreach([0, 12, 21] as $rate)
                @php
                    $outputVat = $vat_summary['output_vat'][$rate]['vat'] ?? 0;
                    $inputVat = $vat_summary['input_vat'][$rate]['vat'] ?? 0;
                    $netVat = $vat_summary['net_vat'][$rate] ?? 0;
                    $totalOutput += $outputVat;
                    $totalInput += $inputVat;
                    $totalNet += $netVat;
                @endphp
                <tr>
                    <td class="center-cell">{{ $rate }}%</td>
                    <td class="number-cell currency">{{ number_format($outputVat, 2, ',', ' ') }} Kč</td>
                    <td class="number-cell currency">{{ number_format($inputVat, 2, ',', ' ') }} Kč</td>
                    <td class="number-cell currency {{ $netVat >= 0 ? 'highlight-positive' : 'highlight-negative' }}">
                        {{ number_format($netVat, 2, ',', ' ') }} Kč
                    </td>
                </tr>
            @endforeach
            <tr style="background-color: #e9ecef; font-weight: bold; font-size: 14px;">
                <td class="center-cell">CELKEM</td>
                <td class="number-cell currency">{{ number_format($totalOutput, 2, ',', ' ') }} Kč</td>
                <td class="number-cell currency">{{ number_format($totalInput, 2, ',', ' ') }} Kč</td>
                <td class="number-cell currency {{ $totalNet >= 0 ? 'highlight-positive' : 'highlight-negative' }}">
                    {{ number_format($totalNet, 2, ',', ' ') }} Kč
                </td>
            </tr>
        </tbody>
    </table>

    @if($totalNet > 0)
        <div class="info-box">
            <strong>K doplatku:</strong> {{ number_format($totalNet, 2, ',', ' ') }} Kč<br>
            <small>Kladná hodnota znamená, že máte doplatit DPH finančnímu úřadu.</small>
        </div>
    @elseif($totalNet < 0)
        <div class="info-box">
            <strong>K vrácení:</strong> {{ number_format(abs($totalNet), 2, ',', ' ') }} Kč<br>
            <small>Záporná hodnota znamená, že máte nárok na vrácení DPH z finančního úřadu.</small>
        </div>
    @else
        <div class="info-box">
            <strong>Vyrovnáno:</strong> Nemáte žádnou povinnost ani nárok na vrácení DPH.
        </div>
    @endif

    <!-- Detailed VAT Breakdown -->
    <div class="section-title">Detailní přehled DPH na výstupu (faktury)</div>
    @if(count($vat_summary['output_vat']) > 0)
        <table class="vat-table">
            <thead>
                <tr>
                    <th>Sazba DPH</th>
                    <th>Základ daně</th>
                    <th>DPH</th>
                    <th>Celkem s DPH</th>
                </tr>
            </thead>
            <tbody>
                @foreach($vat_summary['output_vat'] as $rate => $data)
                <tr>
                    <td class="center-cell">{{ number_format($rate, 0) }}%</td>
                    <td class="number-cell">{{ number_format($data['base'], 2, ',', ' ') }} Kč</td>
                    <td class="number-cell">{{ number_format($data['vat'], 2, ',', ' ') }} Kč</td>
                    <td class="number-cell">{{ number_format($data['base'] + $data['vat'], 2, ',', ' ') }} Kč</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p><em>V tomto období nebyly vystaveny žádné faktury.</em></p>
    @endif

    <div class="section-title">Detailní přehled DPH na vstupu (výdaje)</div>
    @if(count($vat_summary['input_vat']) > 0)
        <table class="vat-table">
            <thead>
                <tr>
                    <th>Sazba DPH</th>
                    <th>Základ daně</th>
                    <th>DPH</th>
                    <th>Celkem s DPH</th>
                </tr>
            </thead>
            <tbody>
                @foreach($vat_summary['input_vat'] as $rate => $data)
                <tr>
                    <td class="center-cell">{{ number_format($rate, 0) }}%</td>
                    <td class="number-cell">{{ number_format($data['base'], 2, ',', ' ') }} Kč</td>
                    <td class="number-cell">{{ number_format($data['vat'], 2, ',', ' ') }} Kč</td>
                    <td class="number-cell">{{ number_format($data['base'] + $data['vat'], 2, ',', ' ') }} Kč</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p><em>V tomto období nebyly evidovány žádné výdaje s DPH.</em></p>
    @endif

    <!-- Invoice Details -->
    @if(count($invoices) > 0)
        <div class="section-title">Seznam faktur v období</div>
        <table class="detail-table">
            <thead>
                <tr>
                    <th>Číslo faktury</th>
                    <th>Datum</th>
                    <th>Odběratel</th>
                    <th>Základ</th>
                    <th>DPH</th>
                    <th>Celkem</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->invoice_number }}</td>
                    <td class="center-cell">{{ $invoice->issue_date->format('d.m.Y') }}</td>
                    <td>{{ $invoice->client->company_name }}</td>
                    <td class="number-cell">{{ number_format($invoice->subtotal, 2, ',', ' ') }} Kč</td>
                    <td class="number-cell">{{ number_format($invoice->vat_amount, 2, ',', ' ') }} Kč</td>
                    <td class="number-cell">{{ number_format($invoice->total, 2, ',', ' ') }} Kč</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- Expense Details -->
    @if(count($expenses) > 0)
        <div class="section-title">Seznam výdajů v období</div>
        <table class="detail-table">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Popis</th>
                    <th>Kategorie</th>
                    <th>Sazba DPH</th>
                    <th>Základ</th>
                    <th>DPH</th>
                    <th>Celkem</th>
                </tr>
            </thead>
            <tbody>
                @foreach($expenses as $expense)
                <tr>
                    <td class="center-cell">{{ \Carbon\Carbon::parse($expense->expense_date)->format('d.m.Y') }}</td>
                    <td>{{ $expense->description }}</td>
                    <td>{{ $expense->category->name ?? 'Nezařazeno' }}</td>
                    <td class="center-cell">{{ number_format($expense->vat_rate, 0) }}%</td>
                    <td class="number-cell">{{ number_format($expense->amount, 2, ',', ' ') }} Kč</td>
                    <td class="number-cell">{{ number_format($expense->amount * ($expense->vat_rate / 100), 2, ',', ' ') }} Kč</td>
                    <td class="number-cell">{{ number_format($expense->amount + ($expense->amount * ($expense->vat_rate / 100)), 2, ',', ' ') }} Kč</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="warning-box" style="margin-top: 40px;">
        <strong>Upozornění:</strong> Tento přehled slouží pouze pro informativní účely. 
        Pro podání daňového přiznání či kontrolního hlášení je nutné ověřit údaje 
        a případně konzultovat s daňovým poradcem. Údaje v tomto přehledu mohou 
        podléhat dalším úpravám dle platné legislativy.
    </div>

    <div class="footer">
        <div style="float: left;">
            <div>Vygenerováno systémem: {{ config('company.name') }}</div>
            <div>Datum a čas: {{ now()->format('d.m.Y H:i:s') }}</div>
        </div>
        <div style="float: right;">
            <div>Přehled byl vygenerován elektronicky.</div>
            <div>IČO: {{ config('company.ico') }} | DIČ: {{ config('company.dic') }}</div>
        </div>
        <div class="clearfix"></div>
    </div>
</body>
</html>