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
        table.grid td.section {
            font-size: 8pt;
            font-weight: bold;
            background: #f5f5f5;
            color: #333;
        }
        tfoot th, tfoot td {
            font-weight: bold;
            background: #e8f5e9;
            font-size: 10pt;
        }
        .sub td { font-size: 8.5pt; color: #444; }
        .sub td:first-child { padding-left: 14px; }
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
        Отчёт о финансовых результатах (упрощённо): выручка и себестоимость по документам продаж и возвратов;
        операционные расходы и прочие доходы — по дате записи в журнале «Банк и касса». Не является отчётом о движении денежных средств.
        Итог — прибыль до налога на прибыль (налог в системе не ведётся). Детализация по строкам — в экранной версии отчёта.
    </p>
    <table class="grid">
        <thead>
            <tr>
                <th>Показатель</th>
                <th style="width: 38%">Сумма</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Выручка — реализация товаров</td>
                <td class="num">+ {{ InvoiceNakladnayaFormatter::formatMoney($summary['revenue_goods']) }}</td>
            </tr>
            <tr>
                <td>Выручка — услуги</td>
                <td class="num">+ {{ InvoiceNakladnayaFormatter::formatMoney($summary['revenue_services']) }}</td>
            </tr>
            <tr>
                <td>Возвраты от покупателей</td>
                <td class="num">− {{ InvoiceNakladnayaFormatter::formatMoney($summary['returns_revenue']) }}</td>
            </tr>
            <tr>
                <td><strong>Итого выручка</strong></td>
                <td class="num"><strong>{{ InvoiceNakladnayaFormatter::formatMoney($summary['revenue_net']) }}</strong></td>
            </tr>
            <tr>
                <td colspan="2" class="section">Себестоимость</td>
            </tr>
            @if ($summary['cogs_goods_sold'] > 0.00001 || $summary['cogs_returns_reversal'] > 0.00001)
                <tr class="sub">
                    <td>в т.ч. себестоимость проданных товаров</td>
                    <td class="num">− {{ InvoiceNakladnayaFormatter::formatMoney($summary['cogs_goods_sold']) }}</td>
                </tr>
                <tr class="sub">
                    <td>в т.ч. уменьшение на возвраты на склад</td>
                    <td class="num">+ {{ InvoiceNakladnayaFormatter::formatMoney($summary['cogs_returns_reversal']) }}</td>
                </tr>
            @endif
            <tr>
                <td>Себестоимость продаж (нетто)</td>
                <td class="num">− {{ InvoiceNakladnayaFormatter::formatMoney($summary['cogs_net']) }}</td>
            </tr>
            <tr>
                <td><strong>Валовая прибыль</strong></td>
                <td class="num"><strong>{{ InvoiceNakladnayaFormatter::formatMoney($summary['gross_profit']) }}</strong></td>
            </tr>
            <tr>
                <td colspan="2" class="section">Операционные расходы (факт оплаты)</td>
            </tr>
            <tr>
                <td>Зарплата</td>
                <td class="num">− {{ InvoiceNakladnayaFormatter::formatMoney($summary['opex_salary']) }}</td>
            </tr>
            <tr>
                <td>Прочие операционные расходы</td>
                <td class="num">− {{ InvoiceNakladnayaFormatter::formatMoney($summary['opex_other']) }}</td>
            </tr>
            <tr>
                <td><strong>Прибыль от продаж</strong></td>
                <td class="num"><strong>{{ InvoiceNakladnayaFormatter::formatMoney($summary['operating_profit']) }}</strong></td>
            </tr>
            <tr>
                <td>Прочие доходы</td>
                <td class="num">+ {{ InvoiceNakladnayaFormatter::formatMoney($summary['other_income']) }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <th>Чистая прибыль (до налога на прибыль)</th>
                <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney($summary['net_profit']) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
