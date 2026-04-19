@php
    $fmt = static fn (?string $v) => $v === null || $v === '' ? '—' : number_format((float) $v, 2, ',', ' ');
    $fmtSigned = static function (string $v) {
        if (bccomp($v, '0', 2) === 0) {
            return '0,00';
        }
        $sign = $v[0] === '-' ? '−' : '';
        $abs = ltrim($v, '-');

        return $sign.number_format((float) $abs, 2, ',', ' ');
    };
    $isList = $counterparty === null;
    $isBuyers = $mode === 'buyers';
    $branchName = $branchName ?? '—';
@endphp
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Сверка с контрагентами</title>
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
        .meta { font-size: 9pt; margin-bottom: 10px; text-align: center; color: #333; }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 10px 0;
            font-size: 8.5pt;
        }
        table.grid th, table.grid td {
            border: 1px solid #333;
            padding: 4px 6px;
            vertical-align: top;
        }
        table.grid th {
            background: #f0f0f0;
            font-weight: bold;
            text-align: left;
        }
        table.grid td.num { text-align: right; white-space: nowrap; }
        .subhead {
            font-weight: bold;
            font-size: 9.5pt;
            margin: 10px 0 6px 0;
            padding: 4px 0;
            border-bottom: 1px solid #999;
        }
        .pair-title { font-weight: bold; background: #e8eef5; text-align: center; }
        tfoot td { font-weight: bold; background: #f5f5f5; }
        .sum-box {
            margin-top: 12px;
            padding: 8px 10px;
            border: 1px solid #333;
            background: #f9f9f9;
            font-size: 9pt;
        }
        .sum-box strong { font-size: 10pt; }
    </style>
</head>
<body>
    <h1>Сверка с контрагентами</h1>
    <div class="meta">Филиал: {{ $branchName }}</div>

    @if (! $branchHasAnyCounterparty)
        <p>Нет контрагентов в справочнике.</p>
    @elseif ($isList)
        <div class="subhead">{{ $isBuyers ? 'Покупатели' : 'Поставщики' }}</div>
        <table class="grid">
            <thead>
                <tr>
                    <th>Контрагент</th>
                    <th class="num">Начальные долги</th>
                    @if ($isBuyers)
                        <th class="num">Всего купил у нас</th>
                        <th class="num">Всего перевёл</th>
                        <th class="num">Долг нам (сейчас)</th>
                    @else
                        <th class="num">Всего закупили у него</th>
                        <th class="num">Всего оплатили</th>
                        <th class="num">Мы должны (сейчас)</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($summaryRows as $sr)
                    @php
                        $cp = $sr['counterparty'];
                        $cpLabel = trim((string) $cp->full_name) !== '' ? $cp->full_name : $cp->name;
                    @endphp
                    <tr>
                        <td>{{ $cpLabel }}</td>
                        <td class="num">{{ $fmtSigned($sr['opening_debt_card'] ?? '0') }}</td>
                        <td class="num">{{ $fmtSigned($sr['period_purchases']) }}</td>
                        <td class="num">{{ $fmtSigned($sr['paid']) }}</td>
                        <td class="num">{{ $fmtSigned($sr['debt']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            @if ($summaryRows->isNotEmpty())
                <tfoot>
                    <tr>
                        <td>Итого по списку</td>
                        <td class="num">{{ $fmtSigned($totalOpeningCard) }}</td>
                        <td class="num">{{ $fmtSigned($totalPeriodPurchases) }}</td>
                        <td class="num">{{ $fmtSigned($totalPaid) }}</td>
                        <td class="num">{{ $fmtSigned($totalDebt) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    @else
        @php
            $cpLabel = trim((string) $counterparty->full_name) !== '' ? $counterparty->full_name : $counterparty->name;
            if ($isBuyers) {
                $buyerPayV = $buyerPaymentsList->values();
                $buyerDocV = $buyerDocs->values();
                $pairRows = max($buyerPayV->count(), $buyerDocV->count());
            } else {
                $supPayV = $supplierPaymentsList->values();
                $supDocV = $supplierDocs->values();
                $pairRows = max($supPayV->count(), $supDocV->count());
            }
        @endphp
        <div class="meta"><strong>{{ $cpLabel }}</strong> · период {{ $from->format('d.m.Y') }} — {{ $to->format('d.m.Y') }}</div>

        @if ($isBuyers)
            <div class="subhead">Оплаты и продажи</div>
            <table class="grid">
                <thead>
                    <tr>
                        <th colspan="2" class="pair-title">Оплатил</th>
                        <th colspan="3" class="pair-title">Продажи и возвраты</th>
                    </tr>
                    <tr>
                        <th>Дата</th>
                        <th class="num">Сумма оплаты</th>
                        <th>Дата</th>
                        <th>Документ</th>
                        <th class="num">Сумма</th>
                    </tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < $pairRows; $i++)
                        <tr>
                            <td>@isset($buyerPayV[$i]){{ $buyerPayV[$i]['date']->format('d.m.Y') }}@endisset</td>
                            <td class="num">@isset($buyerPayV[$i]){{ $fmt($buyerPayV[$i]['credit']) }}@endisset</td>
                            <td>@isset($buyerDocV[$i]){{ $buyerDocV[$i]['date']->format('d.m.Y') }}@endisset</td>
                            <td>
                                @isset($buyerDocV[$i])
                                    {{ $buyerDocV[$i]['title'] }}
                                    @if (($buyerDocV[$i]['kind'] ?? '') !== 'opening_card' && trim((string) ($buyerDocV[$i]['detail'] ?? '')) !== '')
                                        · {{ $buyerDocV[$i]['detail'] }}
                                    @endif
                                @endisset
                            </td>
                            <td class="num">
                                @isset($buyerDocV[$i])
                                    @if (($buyerDocV[$i]['kind'] ?? '') === 'return')
                                        −{{ $fmt($buyerDocV[$i]['credit']) }}
                                    @else
                                        {{ $fmt($buyerDocV[$i]['debit']) }}
                                    @endif
                                @endisset
                            </td>
                        </tr>
                    @endfor
                </tbody>
            </table>
            <div class="sum-box">
                <strong>Итог</strong><br/>
                Сальдо на {{ $from->format('d.m.Y') }}: {{ $fmtSigned($buyerOpening) }}<br/>
                Долг нам на {{ $to->format('d.m.Y') }}: <strong>{{ $fmtSigned($buyerClosing) }}</strong>
            </div>
        @else
            <div class="subhead">Оплаты и закупки</div>
            <table class="grid">
                <thead>
                    <tr>
                        <th colspan="2" class="pair-title">Оплатили</th>
                        <th colspan="3" class="pair-title">Закупки</th>
                    </tr>
                    <tr>
                        <th>Дата</th>
                        <th class="num">Сумма оплаты</th>
                        <th>Дата</th>
                        <th>Документ</th>
                        <th class="num">Сумма</th>
                    </tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < $pairRows; $i++)
                        <tr>
                            <td>@isset($supPayV[$i]){{ $supPayV[$i]['date']->format('d.m.Y') }}@endisset</td>
                            <td class="num">@isset($supPayV[$i]){{ $fmt($supPayV[$i]['debit']) }}@endisset</td>
                            <td>@isset($supDocV[$i]){{ $supDocV[$i]['date']->format('d.m.Y') }}@endisset</td>
                            <td>
                                @isset($supDocV[$i])
                                    {{ $supDocV[$i]['title'] }}
                                    @if (($supDocV[$i]['kind'] ?? '') !== 'opening_card' && trim((string) ($supDocV[$i]['detail'] ?? '')) !== '')
                                        · {{ $supDocV[$i]['detail'] }}
                                    @endif
                                @endisset
                            </td>
                            <td class="num">
                                @isset($supDocV[$i])
                                    @if (($supDocV[$i]['kind'] ?? '') === 'purchase_return')
                                        −{{ $fmt($supDocV[$i]['debit']) }}
                                    @else
                                        {{ $fmt($supDocV[$i]['credit']) }}
                                    @endif
                                @endisset
                            </td>
                        </tr>
                    @endfor
                </tbody>
            </table>
            <div class="sum-box">
                <strong>Итог</strong><br/>
                Сальдо на {{ $from->format('d.m.Y') }}: {{ $fmtSigned($supplierOpening) }}<br/>
                Мы должны на {{ $to->format('d.m.Y') }}: <strong>{{ $fmtSigned($supplierClosing) }}</strong>
            </div>
        @endif
    @endif
</body>
</html>
