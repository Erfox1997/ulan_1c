@php
    use App\Support\InvoiceNakladnayaFormatter;
@endphp
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>{{ $pageTitle }}</title>
    <style>
        @page { margin: 12mm 14mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #000;
            line-height: 1.4;
            margin: 0;
        }
        h1 {
            font-size: 13pt;
            margin: 0 0 6px 0;
            text-align: center;
        }
        .meta { font-size: 9pt; margin-bottom: 10px; text-align: center; color: #333; }
        .hint {
            font-size: 8pt;
            color: #444;
            margin-bottom: 12px;
            padding: 8px;
            border: 1px solid #ccc;
            background: #fafafa;
        }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
        }
        table.grid th, table.grid td {
            border: 1px solid #333;
            padding: 6px 8px;
            vertical-align: top;
        }
        table.grid th {
            background: #e8f5e9;
            font-weight: bold;
            text-align: left;
        }
        table.grid td.num { text-align: right; white-space: nowrap; }
        tfoot th, tfoot td {
            font-weight: bold;
            background: #e8f5e9;
            font-size: 10pt;
        }
    </style>
</head>
<body>
    <h1>{{ $pageTitle }}</h1>
    <div class="meta">
        @if (! empty($branchName))
            Филиал: {{ $branchName }}<br>
        @endif
        Период: {{ $periodLabel }}
    </div>
    <p class="hint">
        Свод по статьям движения денег: поступления минус выплаты (переводы между счетами не учитываются).
        Детализация по строкам доступна только на экране отчёта.
    </p>
    <table class="grid">
        <thead>
            <tr>
                <th>Статья</th>
                <th style="width: 35%">Сумма</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Приход от покупателя</td>
                <td class="num">+ {{ InvoiceNakladnayaFormatter::formatMoney($summary['income_client']) }}</td>
            </tr>
            <tr>
                <td>Приход прочие</td>
                <td class="num">+ {{ InvoiceNakladnayaFormatter::formatMoney($summary['income_other']) }}</td>
            </tr>
            <tr>
                <td>Расход поставщику</td>
                <td class="num">− {{ InvoiceNakladnayaFormatter::formatMoney($summary['expense_supplier']) }}</td>
            </tr>
            <tr>
                <td>Прочие расходы (без зарплаты)</td>
                <td class="num">− {{ InvoiceNakladnayaFormatter::formatMoney($summary['expense_other']) }}</td>
            </tr>
            <tr>
                <td>Продажа физлицам (оплаты по чекам)</td>
                <td class="num">+ {{ InvoiceNakladnayaFormatter::formatMoney($summary['retail_payments']) }}</td>
            </tr>
            <tr>
                <td>Возврат покупателю (розница)</td>
                <td class="num">− {{ InvoiceNakladnayaFormatter::formatMoney($summary['retail_refunds']) }}</td>
            </tr>
            <tr>
                <td>Выплаты зарплаты и авансы</td>
                <td class="num">− {{ InvoiceNakladnayaFormatter::formatMoney($summary['payroll_advances_total']) }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <th>Чистая прибыль</th>
                <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney($summary['net_profit']) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
