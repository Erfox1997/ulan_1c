@php
    use App\Support\InvoiceNakladnayaFormatter;
    $org = $organization;
    $orgName = $org?->name ?? ($branch->name ?? '—');
    $amountWords = InvoiceNakladnayaFormatter::amountInWordsKgs((float) $totalSum);
    $saleInvoiceAggregates = $saleInvoiceAggregates ?? [];
    $invoiceFormat = $invoiceFormat ?? 'summary';
    $mergedGoods = $mergedGoods ?? '0';
    $mergedServices = $mergedServices ?? '0';
    $useMergedSummaryAggregateRows = $useMergedSummaryAggregateRows ?? false;
    $linesCount = 0;
    if ($invoiceFormat === 'summary') {
        if ($useMergedSummaryAggregateRows) {
            if (bccomp($mergedGoods, '0', 2) === 1) {
                $linesCount++;
            }
            if (bccomp($mergedServices, '0', 2) === 1) {
                $linesCount++;
            }
        } else {
            foreach ($salesOrdered as $s) {
                $linesCount += $s->lines->count();
            }
        }
    } else {
        foreach ($salesOrdered as $s) {
            $linesCount += $s->lines->count();
        }
    }
@endphp
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle }} — {{ config('app.name') }}</title>
    <style>
        @page { margin: 14mm 16mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #000;
            line-height: 1.4;
            margin: 0;
            padding: {{ ($forPdf ?? false) ? '0' : '16px' }};
        }
        .doc-title {
            font-size: 11pt;
            font-weight: bold;
            text-align: center;
            color: #1a1a1a;
            margin: 0 0 12px 0;
            padding: 10px 8px 10px;
            border-top: 3px solid #2563eb;
            border-bottom: 1px solid #000;
            line-height: 1.35;
        }
        .meta-block {
            margin: 14px 0 18px 0;
            font-size: 10pt;
        }
        .meta-row {
            margin-bottom: 6px;
        }
        .meta-row .lbl {
            display: inline-block;
            min-width: 9em;
            font-weight: normal;
            vertical-align: top;
        }
        .meta-row .val {
            font-weight: normal;
        }
        .requisites {
            margin: 16px 0 18px 0;
            padding: 10px 12px;
            border: 1px solid #000;
            font-size: 9pt;
        }
        .requisites h2 {
            margin: 0 0 8px 0;
            font-size: 10pt;
            font-weight: bold;
        }
        .requisites p { margin: 4px 0; }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            margin: 0 0 12px 0;
        }
        table.grid th,
        table.grid td {
            border: 1px solid #000;
            padding: 5px 6px;
            vertical-align: middle;
        }
        table.grid th {
            font-weight: bold;
            text-align: left;
            background: #f8fafc;
        }
        table.grid th.c,
        table.grid td.c { text-align: center; }
        table.grid th.num,
        table.grid td.num { text-align: right; }
        table.grid tr.subsection td {
            font-weight: bold;
            background: #f1f5f9;
        }
        .totals-wrap {
            display: table;
            width: 100%;
            margin-top: 4px;
        }
        .totals-left { display: table-cell; width: 52%; }
        .totals-right {
            display: table-cell;
            width: 48%;
            vertical-align: top;
            text-align: right;
            font-size: 10pt;
        }
        .totals-right .row { margin-bottom: 4px; }
        .footer-line {
            margin-top: 16px;
            font-size: 10pt;
        }
        .amount-words {
            margin-top: 10px;
            font-weight: bold;
            font-size: 10pt;
        }
        .sale-block {
            margin: 18px 0 20px 0;
            padding-top: 12px;
            border-top: 1px solid #cbd5e1;
        }
        .sale-block:first-of-type {
            border-top: none;
            padding-top: 0;
            margin-top: 8px;
        }
        .sale-heading {
            margin: 0 0 10px 0;
            font-size: 10pt;
            font-weight: bold;
            color: #0f172a;
        }
        .sale-sub {
            margin: 0 0 8px 0;
            font-size: 9.5pt;
            line-height: 1.45;
        }
        .sale-sub .lbl {
            display: inline-block;
            min-width: 8.5em;
            font-weight: normal;
            color: #334155;
        }
        .no-print {
            margin: 16px 0;
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
        </div>
        @if (isset($printOrganizations) && $printOrganizations->count() > 1)
            @php
                $idsJoined = implode(',', $saleIds);
                $printUrlBase = route('admin.trade-invoices.merged-print');
                $pdfUrlBase = route('admin.trade-invoices.merged-pdf');
                $fmtPart = $invoiceFormat === 'detail' ? '&invoice_format=detail' : '';
                $pdfOrgSuffix = $organization ? '&organization_id='.$organization->id : '';
            @endphp
            <div class="no-print" style="margin:10px 0 12px;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;background:#f8fafc;font-size:10pt;">
                <span style="color:#334155;margin-right:8px;">Организация в шапке:</span>
                <select
                    onchange="var v=this.value||''; var q='sale_ids={{ $idsJoined }}' + (v?'&organization_id='+v:'') + '{{ $fmtPart }}'; location.href='{{ $printUrlBase }}?' + q"
                    style="max-width:min(28rem,100%);padding:4px 8px;font-size:10pt;border:1px solid #94a3b8;border-radius:4px;"
                >
                    @foreach ($printOrganizations as $o)
                        <option value="{{ $o->id }}" @selected($organization && (int) $organization->id === (int) $o->id)>{{ $o->name }}</option>
                    @endforeach
                </select>
                <a
                    href="{{ $pdfUrlBase }}?sale_ids={{ $idsJoined }}{{ $pdfOrgSuffix }}{{ $fmtPart }}"
                    style="margin-left:12px;font-size:10pt;color:#059669;text-decoration:underline;"
                >Скачать PDF</a>
            </div>
        @endif
    @endif

    <h1 class="doc-title">{{ $documentTitle }}</h1>

    <div class="meta-block">
        <div class="meta-row">
            <span class="lbl">Поставщик:</span>
            <span class="val">{{ $orgName }}</span>
        </div>
        @if ($org && trim((string) $org->inn) !== '')
            <div class="meta-row">
                <span class="lbl">ИНН:</span>
                <span class="val">{{ $org->inn }}</span>
            </div>
        @endif
        @if ($org && trim((string) $org->legal_address) !== '')
            <div class="meta-row">
                <span class="lbl">Адрес:</span>
                <span class="val">{{ $org->legal_address }}</span>
            </div>
        @endif
        @if ($org && trim((string) $org->phone) !== '')
            <div class="meta-row">
                <span class="lbl">Телефон:</span>
                <span class="val">{{ $org->phone }}</span>
            </div>
        @endif
        <div class="meta-row">
            <span class="lbl">Покупатель:</span>
            <span class="val">{{ trim((string) $buyerName) !== '' ? $buyerName : '—' }}</span>
        </div>
        @if (isset($periodFrom, $periodTo) && $periodFrom && $periodTo)
            <div class="meta-row">
                <span class="lbl">Период:</span>
                <span class="val">
                    @if ($periodFrom->isSameDay($periodTo))
                        {{ $periodFrom->format('d.m.Y') }}
                    @else
                        с {{ $periodFrom->format('d.m.Y') }} по {{ $periodTo->format('d.m.Y') }}
                    @endif
                </span>
            </div>
        @endif
    </div>

    @if ($bankAccount && $bankAccount->isBank())
        <div class="requisites">
            <h2>Банковские реквизиты для оплаты</h2>
            @if (trim((string) $bankAccount->bank_name) !== '')
                <p><strong>Банк:</strong> {{ $bankAccount->bank_name }}</p>
            @endif
            @if (trim((string) $bankAccount->bik) !== '')
                <p><strong>БИК:</strong> {{ $bankAccount->bik }}</p>
            @endif
            @if (trim((string) $bankAccount->account_number) !== '')
                <p><strong>Расчётный счёт:</strong> {{ $bankAccount->account_number }}</p>
            @endif
            @if (trim((string) $bankAccount->currency) !== '')
                <p><strong>Валюта:</strong> {{ $bankAccount->currency }}</p>
            @endif
        </div>
    @elseif ($org)
        <div class="requisites">
            <h2>Реквизиты</h2>
            <p>Банковский счёт в карточке организации не заполнен — укажите счёт в настройках организации.</p>
        </div>
    @endif

    @if ($invoiceFormat === 'summary')
        <table class="grid">
            <thead>
                <tr>
                    <th style="width:2.2rem;" class="c">№</th>
                    <th>Наименование</th>
                    <th style="width:8rem;">Количество</th>
                    <th style="width:5rem;" class="num">Цена</th>
                    <th style="width:5.5rem;" class="num">Сумма</th>
                </tr>
            </thead>
            <tbody>
                @if ($useMergedSummaryAggregateRows)
                    <tr class="subsection">
                        <td colspan="5">Техническое обслуживание и ремонт автомобилей</td>
                    </tr>
                    @php $rowNum = 0; @endphp
                    @if (bccomp($mergedGoods, '0', 2) === 1)
                        @php $rowNum++; @endphp
                        <tr>
                            <td class="c">{{ $rowNum }}</td>
                            <td>Оплата за запасные части</td>
                            <td>{{ InvoiceNakladnayaFormatter::formatQuantityWithUnit('1', 'шт.') }}</td>
                            <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney((float) $mergedGoods) }}</td>
                            <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney((float) $mergedGoods) }}</td>
                        </tr>
                    @endif
                    @if (bccomp($mergedServices, '0', 2) === 1)
                        @php $rowNum++; @endphp
                        <tr>
                            <td class="c">{{ $rowNum }}</td>
                            <td>Оплата за услуги ремонта</td>
                            <td>{{ InvoiceNakladnayaFormatter::formatQuantityWithUnit('1', 'шт.') }}</td>
                            <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney((float) $mergedServices) }}</td>
                            <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney((float) $mergedServices) }}</td>
                        </tr>
                    @endif
                @else
                    @php
                        $rowNum = 0;
                    @endphp
                    @foreach ($salesOrdered as $sale)
                        @foreach ($sale->lines->sortBy('id') as $line)
                            @php $rowNum++; @endphp
                            <tr>
                                <td class="c">{{ $rowNum }}</td>
                                <td>{{ $line->name }}</td>
                                <td>{{ InvoiceNakladnayaFormatter::formatQuantityWithUnit($line->quantity, $line->unit) }}</td>
                                <td class="num">{{ $line->unit_price !== null ? InvoiceNakladnayaFormatter::formatMoney((float) $line->unit_price) : '—' }}</td>
                                <td class="num">{{ $line->line_sum !== null ? InvoiceNakladnayaFormatter::formatMoney((float) $line->line_sum) : '—' }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                @endif
            </tbody>
        </table>
    @else
        @foreach ($salesOrdered as $sale)
            @php
                $saleLines = $sale->lines->sortBy('id')->values();
                $saleSum = '0';
                foreach ($saleLines as $ln) {
                    if ($ln->line_sum !== null) {
                        $saleSum = bcadd($saleSum, (string) $ln->line_sum, 2);
                    }
                }
                $agg = $saleInvoiceAggregates[$sale->id] ?? ['goods' => '0', 'services' => '0'];
                $useAggregateInvoiceRows = $saleLines->isNotEmpty()
                    && (bccomp($agg['goods'], '0', 2) === 1 || bccomp($agg['services'], '0', 2) === 1);
            @endphp
            <section class="sale-block">
                <h2 class="sale-heading">Реализация № {{ $sale->id }} от {{ $sale->document_date->format('d.m.Y') }}</h2>
                <div class="sale-sub">
                    <span class="lbl">Склад:</span>
                    <span>{{ $sale->warehouse->name }}</span>
                </div>
                <table class="grid">
                    <thead>
                        <tr>
                            <th style="width:2.2rem;" class="c">№</th>
                            <th>Наименование</th>
                            <th style="width:8rem;">Количество</th>
                            <th style="width:5rem;" class="num">Цена</th>
                            <th style="width:5.5rem;" class="num">Сумма</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($useAggregateInvoiceRows)
                            <tr class="subsection">
                                <td colspan="5">Техническое обслуживание и ремонт автомобилей</td>
                            </tr>
                            @php $rowNum = 0; @endphp
                            @if (bccomp($agg['goods'], '0', 2) === 1)
                                @php $rowNum++; @endphp
                                <tr>
                                    <td class="c">{{ $rowNum }}</td>
                                    <td>Оплата за запасные части</td>
                                    <td>{{ InvoiceNakladnayaFormatter::formatQuantityWithUnit('1', 'шт.') }}</td>
                                    <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney((float) $agg['goods']) }}</td>
                                    <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney((float) $agg['goods']) }}</td>
                                </tr>
                            @endif
                            @if (bccomp($agg['services'], '0', 2) === 1)
                                @php $rowNum++; @endphp
                                <tr>
                                    <td class="c">{{ $rowNum }}</td>
                                    <td>Оплата за услуги ремонта</td>
                                    <td>{{ InvoiceNakladnayaFormatter::formatQuantityWithUnit('1', 'шт.') }}</td>
                                    <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney((float) $agg['services']) }}</td>
                                    <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney((float) $agg['services']) }}</td>
                                </tr>
                            @endif
                        @else
                            @foreach ($saleLines as $i => $line)
                                <tr>
                                    <td class="c">{{ $i + 1 }}</td>
                                    <td>{{ $line->name }}</td>
                                    <td>{{ InvoiceNakladnayaFormatter::formatQuantityWithUnit($line->quantity, $line->unit) }}</td>
                                    <td class="num">{{ $line->unit_price !== null ? InvoiceNakladnayaFormatter::formatMoney((float) $line->unit_price) : '—' }}</td>
                                    <td class="num">{{ $line->line_sum !== null ? InvoiceNakladnayaFormatter::formatMoney((float) $line->line_sum) : '—' }}</td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
                <div class="totals-wrap" style="margin-top:6px;">
                    <div class="totals-left"></div>
                    <div class="totals-right">
                        <div class="row">
                            <span>Сумма по реализации:</span>
                            <strong>{{ InvoiceNakladnayaFormatter::formatMoney((float) $saleSum) }}</strong>
                        </div>
                    </div>
                </div>
            </section>
        @endforeach
    @endif

    <div class="totals-wrap">
        <div class="totals-left"></div>
        <div class="totals-right">
            <div class="row">
                <span>К оплате:</span>
                <strong>{{ InvoiceNakladnayaFormatter::formatMoney((float) $totalSum) }}</strong>
            </div>
        </div>
    </div>

    <div class="footer-line">
        Всего наименований {{ $linesCount }}, на сумму {{ InvoiceNakladnayaFormatter::formatMoney((float) $totalSum) }}
    </div>
    <div class="amount-words">{{ $amountWords }}</div>
</body>
</html>
