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
            line-height: 1.35;
            margin: 0;
        }
        h1 {
            font-size: 12pt;
            margin: 0 0 6px 0;
            text-align: center;
        }
        .meta { font-size: 8pt; margin-bottom: 12px; text-align: center; color: #333; }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
        }
        table.grid th, table.grid td {
            border: 1px solid #333;
            padding: 4px 6px;
            vertical-align: top;
        }
        table.grid thead th {
            background: #e8f5e9;
            font-weight: bold;
            text-align: left;
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
        Период: {{ $periodLabel }}<br>
        Кассир: {{ $cashierLabel }}
    </div>
    <table class="grid">
        <thead>
            <tr>
                <th style="width: 8%">Смена</th>
                <th>Кассир</th>
                <th>Открыта</th>
                <th>Закрыта</th>
                <th class="num">На начало</th>
                <th class="num">Движение</th>
                <th>Статус</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($shifts as $shift)
                @php
                    $s = $summaries[$shift->id] ?? ['opening_total' => 0.0, 'movement_total' => 0.0];
                @endphp
                <tr>
                    <td>№ {{ $shift->id }}</td>
                    <td>{{ $shift->user?->name ?? '—' }}</td>
                    <td>{{ $shift->opened_at?->format('d.m.Y H:i') }}</td>
                    <td>{{ $shift->closed_at ? $shift->closed_at->format('d.m.Y H:i') : '—' }}</td>
                    <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney($s['opening_total']) }}</td>
                    <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney($s['movement_total']) }}</td>
                    <td>{{ $shift->closed_at ? 'Закрыта' : 'Открыта' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center;padding:14px;">Нет смен за выбранный период.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
