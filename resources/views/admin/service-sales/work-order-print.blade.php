@php
    use App\Support\InvoiceNakladnayaFormatter;
    $cp = $serviceOrder->counterparty;
    $veh = $serviceOrder->customerVehicle;
    $master = $serviceOrder->leadMasterEmployee;
    $orgName = $organization?->name ?? ($branch?->name ?? '—');
    $orgAddress = $organization?->legal_address ?? '';
    $clientLabel = $cp ? (string) ($cp->full_name ?: $cp->name) : '—';
    $clientPhone = $cp?->phone ? (string) $cp->phone : '—';
    $contactFio = $serviceOrder->contact_name !== null && trim((string) $serviceOrder->contact_name) !== ''
        ? trim((string) $serviceOrder->contact_name)
        : ($cp ? trim((string) ($cp->name !== '' ? $cp->name : $cp->full_name)) : '—');
    $mileage = $serviceOrder->mileage_km !== null ? number_format((float) $serviceOrder->mileage_km, 0, ',', ' ') : '—';
    $yearEngine = '';
    if ($veh) {
        $ye = [];
        if ($veh->vehicle_year) {
            $ye[] = (string) $veh->vehicle_year;
        }
        if ($veh->engine_volume) {
            $ev = trim((string) $veh->engine_volume);
            if ($ev !== '' && ! preg_match('/^V/i', $ev)) {
                $ev = 'V-'.$ev;
            }
            $ye[] = $ev;
        }
        $yearEngine = $ye !== [] ? implode(' ', $ye) : '—';
    } else {
        $yearEngine = '—';
    }
    $plate = $veh?->plate_number ? (string) $veh->plate_number : '—';
    $vin = $veh?->vin ? (string) $veh->vin : '—';
    $brand = $veh?->vehicle_brand ? (string) $veh->vehicle_brand : '—';
    $receivedAt = $serviceOrder->document_date?->format('d.m.Y') ?? $serviceOrder->created_at?->format('d.m.Y') ?? '—';
    $deadlineAt = $serviceOrder->deadline_date?->format('d.m.Y') ?? '—';
    $masterName = $master?->full_name ?? '—';
@endphp
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle }} — {{ config('app.name') }}</title>
    <style>
        @page { margin: 12mm 14mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10pt;
            color: #000;
            line-height: 1.35;
            margin: 0;
            padding: {{ ($forPdf ?? false) ? '0' : '16px' }};
        }
        .supplier {
            margin-bottom: 10px;
            font-size: 10pt;
        }
        .supplier strong { font-weight: bold; }
        .doc-title {
            font-size: 12pt;
            font-weight: bold;
            text-align: center;
            margin: 0 0 12px 0;
            padding-bottom: 8px;
            border-bottom: 1px solid #000;
        }
        table.meta {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
            margin-bottom: 14px;
        }
        table.meta td {
            border: 1px solid #000;
            padding: 5px 8px;
            vertical-align: top;
        }
        table.meta td.lbl {
            width: 11em;
            background: #f8fafc;
            font-weight: bold;
        }
        h2.section {
            font-size: 10.5pt;
            font-weight: bold;
            margin: 14px 0 6px 0;
            text-align: center;
        }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            margin: 0 0 10px 0;
        }
        table.grid th,
        table.grid td {
            border: 1px solid #000;
            padding: 4px 6px;
            vertical-align: middle;
        }
        table.grid th {
            font-weight: bold;
            text-align: center;
            background: #fff;
        }
        table.grid td.c { text-align: center; }
        table.grid td.num { text-align: right; }
        .subtotal {
            margin: 6px 0 4px 0;
            font-size: 9.5pt;
        }
        .subtotal .words {
            font-weight: bold;
            margin-top: 4px;
        }
        .grand {
            margin-top: 12px;
            padding-top: 8px;
            border-top: 2px solid #000;
            font-size: 10.5pt;
            font-weight: bold;
        }
        .grand .words {
            font-weight: bold;
            font-size: 10pt;
            margin-top: 6px;
        }
        .warranty {
            margin-top: 16px;
            font-size: 8.5pt;
            line-height: 1.4;
            color: #111;
        }
        .signatures {
            margin-top: 28px;
            width: 100%;
            font-size: 10pt;
        }
        .signatures .row { margin-bottom: 14px; }
        .no-print {
            margin: 0 0 16px 0;
            padding: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        .no-print button {
            font-family: inherit;
            font-size: 11pt;
            padding: 8px 16px;
            margin-right: 8px;
            cursor: pointer;
            border-radius: 6px;
            border: 1px solid #64748b;
            background: #fff;
        }
        .no-print button.primary {
            background: #059669;
            border-color: #059669;
            color: #fff;
        }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    @if (! ($forPdf ?? false))
        <div class="no-print">
            <button type="button" class="primary" onclick="window.print()">Печать</button>
            <button type="button" onclick="window.close()">Закрыть</button>
            <span style="margin-left:8px;font-size:10pt;color:#64748b;">В диалоге печати можно сохранить как PDF.</span>
        </div>
    @endif

    <div class="supplier">
        <strong>ПОСТАВЩИК:</strong> {{ $orgName }}<br>
        @if ($orgAddress !== '')
            {{ $orgAddress }}
        @endif
    </div>

    <h1 class="doc-title">{{ $documentTitle }}</h1>

    <table class="meta" aria-label="Реквизиты">
        <tr>
            <td class="lbl">Принят</td>
            <td>{{ $receivedAt }}</td>
            <td class="lbl">Клиент</td>
            <td>{{ $clientLabel }}</td>
        </tr>
        <tr>
            <td class="lbl">Марка</td>
            <td>{{ $brand }}</td>
            <td class="lbl">VIN</td>
            <td style="font-family: DejaVu Sans Mono, monospace;">{{ $vin }}</td>
        </tr>
        <tr>
            <td class="lbl">Год выпуска и объём</td>
            <td>{{ $yearEngine }}</td>
            <td class="lbl">Гос. номер</td>
            <td>{{ $plate }}</td>
        </tr>
        <tr>
            <td class="lbl">ФИО</td>
            <td>{{ $contactFio }}</td>
            <td class="lbl">№ телефона</td>
            <td>{{ $clientPhone }}</td>
        </tr>
        <tr>
            <td class="lbl">Пробег</td>
            <td>{{ $mileage }}</td>
            <td class="lbl">Мастер</td>
            <td>{{ $masterName }}</td>
        </tr>
        <tr>
            <td class="lbl">Срок исполнения</td>
            <td colspan="3">{{ $deadlineAt }}</td>
        </tr>
    </table>

    <h2 class="section">Расходная накладная (запчасти и материалы)</h2>
    <table class="grid">
        <thead>
            <tr>
                <th style="width:2rem;">№</th>
                <th>Наименование</th>
                <th style="width:6rem;">Кол-во</th>
                <th style="width:5rem;">Цена</th>
                <th style="width:5.5rem;">Сумма</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($materialRows as $i => $row)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td class="c">{{ InvoiceNakladnayaFormatter::formatQuantityWithUnit($row['quantity'], $row['unit']) }}</td>
                    <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney((float) ($row['unit_price'] ?? 0)) }}</td>
                    <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney((float) $row['line_sum']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="c" style="color:#64748b;">Нет позиций</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="subtotal">
        <strong>ИТОГО материалов:</strong> {{ InvoiceNakladnayaFormatter::formatMoney((float) $totalMaterials) }}
        <div class="words">{{ $amountWordsMaterials }}</div>
    </div>

    <h2 class="section">Акт выполненных работ (услуги)</h2>
    <table class="grid">
        <thead>
            <tr>
                <th style="width:2rem;">№</th>
                <th>Наименование</th>
                <th style="width:6rem;">Кол-во</th>
                <th style="width:5rem;">Цена</th>
                <th style="width:5.5rem;">Сумма</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($serviceRows as $i => $row)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td class="c">{{ InvoiceNakladnayaFormatter::formatQuantityWithUnit($row['quantity'], $row['unit']) }}</td>
                    <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney((float) ($row['unit_price'] ?? 0)) }}</td>
                    <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney((float) $row['line_sum']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="c" style="color:#64748b;">Нет позиций</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="subtotal">
        <strong>ИТОГО работ:</strong> {{ InvoiceNakladnayaFormatter::formatMoney((float) $totalServices) }}
        <div class="words">{{ $amountWordsServices }}</div>
    </div>

    <div class="grand">
        ИТОГО за материалы и работы: {{ InvoiceNakladnayaFormatter::formatMoney((float) $grandTotal) }}
        <div class="words">Всего по заказ-наряду: {{ $amountWordsGrand }}</div>
    </div>

    <p class="warranty">
        Гарантия на выполненные работы и установленные запасные части действует в течение 30 календарных дней с даты выдачи автомобиля при соблюдении правил эксплуатации и рекомендаций сервиса.
    </p>

    <div class="signatures">
        <div class="row">Мастер-приёмщик ______________________ {{ $masterName !== '—' ? $masterName : '' }}</div>
        <div class="row">Принял представитель {{ $clientLabel }} ______________________</div>
    </div>
</body>
</html>
