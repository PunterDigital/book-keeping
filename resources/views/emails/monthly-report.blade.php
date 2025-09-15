<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Měsíční přehled účetnictví</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .content {
            padding: 20px 0;
        }
        .period-info {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 14px;
            color: #6c757d;
        }
        .highlight {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 10px;
            border-radius: 5px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 Měsíční přehled účetnictví</h1>
        <p>Automaticky generovaný přehled pro účetní zpracování</p>
    </div>

    <div class="content">
        <p>Dobrý den,</p>
        
        <p>zasíláme Vám měsíční přehled účetnictví pro následující období:</p>
        
        <div class="period-info">
            <strong>📅 Účetní období:</strong> {{ $period_start }} - {{ $period_end }}<br>
            <strong>🕐 Vygenerováno:</strong> {{ $generated_at }}
        </div>

        <div class="highlight">
            <strong>📎 Příloha obsahuje:</strong>
            <ul>
                <li><strong>vydaje.csv</strong> - Kompletní seznam všech výdajů včetně DPH</li>
                <li><strong>faktury.csv</strong> - Kompletní seznam všech vystavených faktur</li>
                <li><strong>faktury_pdf/</strong> - PDF kopie všech faktur pro archivaci</li>
                <li><strong>uctenky_pdf/</strong> - PDF kopie všech účtenek a dokladů</li>
                <li><strong>prehled.txt</strong> - Popis obsahu archivu</li>
            </ul>
        </div>

        <p>Všechny údaje jsou ve formátu vhodném pro přímé zpracování v účetním software. 
           CSV soubory používají českou lokalizaci (čárka jako oddělovač desetinných míst, 
           mezera jako oddělovač tisíců).</p>

        <p><strong>Důležité informace:</strong></p>
        <ul>
            <li>DPH sazby: 21% (standardní), 12% (snížená), 0% (osvobozeno)</li>
            <li>Všechny částky jsou v českých korunách (CZK)</li>
            <li>Účetní období: 16. den předchozího měsíce - 15. den aktuálního měsíce</li>
        </ul>

        <p>V případě jakýchkoliv dotazů nebo problémů nás prosím kontaktujte.</p>

        <p>S pozdravem,<br>
        <strong>Účetní systém</strong></p>
    </div>

    <div class="footer">
        <hr>
        <p><small>
            Tento email byl automaticky vygenerován systémem pro správu účetnictví.<br>
            Generováno: {{ $generated_at }}<br>
            Pro technickou podporu kontaktujte: {{ config('mail.from.address') }}
        </small></p>
    </div>
</body>
</html>