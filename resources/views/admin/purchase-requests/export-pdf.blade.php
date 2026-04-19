<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Заявки на закупку</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 14px; margin: 0 0 6px 0; }
        .meta { font-size: 9px; color: #444; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 5px 8px; text-align: left; vertical-align: top; }
        th { background: #f0f0f0; font-weight: bold; font-size: 9px; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .empty { padding: 12px; color: #666; }
    </style>
</head>
<body>
    <h1>Заявки на закупку</h1>
    <div class="meta">{{ $branchName }} — сформировано {{ $generatedAt->format('d.m.Y H:i') }}</div>
    @if (! empty($requestTitles))
        <div class="meta" style="margin-bottom:10px;">Выбрано: {{ $requestTitles }}</div>
    @endif
    @if ($rows->isEmpty())
        <p class="empty">Нет позиций в заявках.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width:58%">Наименование</th>
                    <th style="width:14%">К закупке</th>
                    <th style="width:28%">ОЭМ</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $r)
                    <tr>
                        <td>{{ $r['name'] }}</td>
                        <td class="num">{{ number_format($r['qty'], 4, ',', ' ') }}</td>
                        <td>{{ $r['oem'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
