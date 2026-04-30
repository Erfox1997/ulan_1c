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
            line-height: 1.35;
            margin: 0;
        }
        h1 {
            font-size: 13pt;
            margin: 0 0 6px 0;
            text-align: center;
        }
        .meta { font-size: 9pt; margin-bottom: 12px; text-align: center; color: #333; }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            font-size: 8.5pt;
        }
        table.grid th, table.grid td {
            border: 1px solid #333;
            padding: 5px 6px;
            vertical-align: top;
        }
        table.grid th {
            background: #e8f5e9;
            font-weight: bold;
            text-align: left;
        }
        table.grid td.num { text-align: right; white-space: nowrap; }
        tfoot td { font-weight: bold; background: #f5f5f5; }
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
    <table class="grid">
        <thead>
            <tr>
                <th>Клиент / сегмент</th>
                <th style="width: 24%">Выручка</th>
                <th style="width: 24%">Себестоимость</th>
                <th style="width: 24%">Валовая прибыль</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r['label'] }}</td>
                    <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney($r['revenue']) }}</td>
                    <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney($r['cost']) }}</td>
                    <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney($r['profit']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align:center;padding:14px;">Нет данных за период.</td>
                </tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr>
                    <td>Итого</td>
                    <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney($totals['revenue']) }}</td>
                    <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney($totals['cost']) }}</td>
                    <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney($totals['profit']) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
