@php
    use App\Support\InvoiceNakladnayaFormatter;
@endphp
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>{{ $pageTitle }}</title>
    <style>
        @page { margin: 10mm 12mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8pt;
            color: #000;
            line-height: 1.3;
            margin: 0;
        }
        h1 {
            font-size: 12pt;
            margin: 0 0 4px 0;
            text-align: center;
        }
        .meta { font-size: 8pt; margin-bottom: 10px; text-align: center; color: #333; }
        .sums {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 8pt;
        }
        .sums td {
            border: 1px solid #333;
            padding: 6px 8px;
            width: 33%;
            vertical-align: top;
        }
        .sums .lbl { font-weight: bold; background: #e8f5e9; font-size: 7.5pt; }
        .sums .val { font-size: 10pt; font-weight: bold; text-align: right; }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
        }
        table.grid th, table.grid td {
            border: 1px solid #333;
            padding: 3px 4px;
            vertical-align: top;
        }
        table.grid th {
            background: #e8f5e9;
            font-weight: bold;
        }
        table.grid td.num { text-align: right; white-space: nowrap; }
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
    <table class="sums">
        <tr>
            <td>
                <div class="lbl">Выручка</div>
                <div class="val">{{ InvoiceNakladnayaFormatter::formatMoney($revenue) }}</div>
            </td>
            <td>
                <div class="lbl">Себестоимость</div>
                <div class="val">{{ InvoiceNakladnayaFormatter::formatMoney($cost) }}</div>
            </td>
            <td>
                <div class="lbl">Валовая прибыль</div>
                <div class="val">{{ InvoiceNakladnayaFormatter::formatMoney($profit) }}</div>
            </td>
        </tr>
    </table>
    <table class="grid">
        <thead>
            <tr>
                <th style="width: 12%">Артикул</th>
                <th>Товар</th>
                <th style="width: 9%">Кол-во</th>
                <th style="width: 14%">Выручка</th>
                <th style="width: 14%">Себестоимость</th>
                <th style="width: 14%">Прибыль</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $r)
                <tr>
                    <td>{{ $r['article'] !== '' ? $r['article'] : '—' }}</td>
                    <td>{{ $r['name'] }}</td>
                    <td class="num">{{ number_format($r['quantity'], 2, ',', ' ') }}</td>
                    <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney($r['revenue']) }}</td>
                    <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney($r['cost']) }}</td>
                    <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney($r['profit']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center;padding:12px;">Нет продаж товаров за период.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
