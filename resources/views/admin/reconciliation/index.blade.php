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

    $baseQuery = [
        'mode' => $mode,
        'date_from' => $from->format('Y-m-d'),
        'date_to' => $to->format('Y-m-d'),
    ];
    $isList = $counterparty === null;
    $isBuyers = $mode === 'buyers';
    $totalPeriodPurchases = '0';
    $totalPaid = '0';
    $totalDebt = '0';
    $totalOpeningCard = '0';
    if ($isList && $summaryRows->isNotEmpty()) {
        foreach ($summaryRows as $sr) {
            $totalPeriodPurchases = bcadd($totalPeriodPurchases, (string) $sr['period_purchases'], 2);
            $totalPaid = bcadd($totalPaid, (string) $sr['paid'], 2);
            $totalDebt = bcadd($totalDebt, (string) $sr['debt'], 2);
            $totalOpeningCard = bcadd($totalOpeningCard, (string) ($sr['opening_debt_card'] ?? '0'), 2);
        }
    }

    $buyerDocs = collect();
    $buyerPaymentsList = collect();
    $supplierDocs = collect();
    $supplierPaymentsList = collect();
    if ($counterparty !== null) {
        $buyerDocs = $buyerRows->whereIn('kind', ['sale', 'return'])->values();
        $openingBuyerCard = number_format((float) ($counterparty->opening_debt_as_buyer ?? 0), 2, '.', '');
        $buyerDocs = collect([
            [
                'sort' => $from->format('Y-m-d').'-0-0',
                'date' => $from,
                'kind' => 'opening_card',
                'title' => 'Начальные долги',
                'detail' => '',
                'debit' => $openingBuyerCard,
                'credit' => null,
            ],
        ])->merge($buyerDocs);

        $buyerPaymentsList = $buyerRows->where('kind', 'payment');
        $supplierDocs = $supplierRows->whereIn('kind', ['purchase', 'purchase_return'])->values();
        $openingSupplierCard = number_format((float) ($counterparty->opening_debt_as_supplier ?? 0), 2, '.', '');
        $supplierDocs = collect([
            [
                'sort' => $from->format('Y-m-d').'-0-0',
                'date' => $from,
                'kind' => 'opening_card',
                'title' => 'Начальные долги',
                'detail' => '',
                'debit' => null,
                'credit' => $openingSupplierCard,
            ],
        ])->merge($supplierDocs);

        $supplierPaymentsList = $supplierRows->where('kind', 'payment');
    }
@endphp
<x-admin-layout pageTitle="Сверка с контрагентами" main-class="px-3 py-5 sm:px-5 lg:px-8 max-w-[1600px] mx-auto w-full">
    <div class="w-full min-w-0">
        <style>
            /* 1С-подобный интерфейс: плотная сетка, но с современной полировкой */
            .rec-1c-scope {
                font-family: system-ui, 'Segoe UI', Tahoma, Arial, sans-serif;
                font-size: 12.5px;
                line-height: 1.45;
                color: #1a1d21;
                -webkit-font-smoothing: antialiased;
            }
            .rec-1c-panel {
                border: 1px solid #c5cad3;
                border-radius: 6px;
                background: #fff;
                box-shadow:
                    0 1px 2px rgba(15, 23, 42, 0.04),
                    0 4px 12px rgba(15, 23, 42, 0.06);
                overflow: hidden;
            }
            .rec-1c-titlebar {
                border-bottom: 1px solid #c5cad3;
                background: linear-gradient(180deg, #fafbfc 0%, #eef0f3 100%);
                padding: 10px 14px;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
            }
            .rec-1c-titlebar h2 {
                margin: 0;
                font-size: 14px;
                font-weight: 700;
                letter-spacing: -0.01em;
                color: #111827;
            }
            .rec-mode-row {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 8px;
                padding: 10px 14px;
                background: linear-gradient(180deg, #f1f3f6 0%, #e8ebf0 100%);
                border-bottom: 1px solid #c5cad3;
            }
            .rec-mode-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 6px 18px;
                min-height: 28px;
                font-size: 11.5px;
                font-weight: 600;
                border-radius: 5px;
                border: 1px solid #9ca3af;
                background: linear-gradient(180deg, #ffffff 0%, #e8eaee 100%);
                color: #111827;
                text-decoration: none;
                cursor: pointer;
                transition: background 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
                box-shadow: 0 1px 0 rgba(255, 255, 255, 0.7) inset;
            }
            .rec-mode-btn:hover {
                background: linear-gradient(180deg, #ffffff 0%, #dfe3e9 100%);
                border-color: #6b7280;
            }
            .rec-mode-btn.rec-mode-active {
                background: linear-gradient(180deg, #dbeafe 0%, #bfdbfe 100%);
                border-color: #3b82f6;
                color: #0f172a;
                box-shadow:
                    inset 0 1px 0 rgba(255, 255, 255, 0.65),
                    0 1px 2px rgba(37, 99, 235, 0.15);
            }
            .rec-1c-toolbar {
                display: flex;
                flex-wrap: wrap;
                align-items: flex-end;
                gap: 12px 18px;
                padding: 10px 14px;
                background: #f4f6f9;
                border-bottom: 1px solid #c5cad3;
            }
            .rec-1c-toolbar label {
                display: block;
                font-size: 11px;
                font-weight: 600;
                color: #374151;
                margin-bottom: 3px;
            }
            .rec-1c-toolbar input[type="date"] {
                min-height: 30px;
                padding: 4px 8px;
                border: 1px solid #9ca3af;
                border-radius: 4px;
                font: inherit;
                background: #fff;
                box-sizing: border-box;
                transition: border-color 0.15s ease, box-shadow 0.15s ease;
            }
            .rec-1c-toolbar input[type="date"]:focus {
                outline: none;
                border-color: #3b82f6;
                box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
            }
            .rec-1c-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 6px 16px;
                min-height: 30px;
                font-size: 11.5px;
                font-weight: 600;
                line-height: 1.2;
                border-radius: 4px;
                border: 1px solid #9ca3af;
                background: linear-gradient(180deg, #ffffff 0%, #e8eaee 100%);
                color: #111827;
                cursor: pointer;
                white-space: nowrap;
                transition: background 0.15s ease, border-color 0.15s ease;
                box-shadow: 0 1px 0 rgba(255, 255, 255, 0.75) inset;
            }
            .rec-1c-btn:hover {
                background: linear-gradient(180deg, #ffffff 0%, #dfe3e9 100%);
            }
            .rec-1c-btn:active {
                background: #d1d5db;
                box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.08);
            }
            .rec-back-link {
                font-size: 11.5px;
                font-weight: 600;
                color: #1d4ed8;
                text-decoration: none;
                border-bottom: 1px solid transparent;
                margin-right: 12px;
                transition: color 0.15s ease, border-color 0.15s ease;
            }
            .rec-back-link:hover {
                color: #1e3a8a;
                border-bottom-color: #93c5fd;
            }
            .rec-1c-subhead {
                border-bottom: 1px solid #c5cad3;
                background: linear-gradient(180deg, #f9fafb 0%, #eef1f5 100%);
                padding: 8px 14px;
                font-size: 12.5px;
                font-weight: 700;
                color: #1f2937;
                letter-spacing: 0.01em;
            }
            .rec-1c-body { padding: 12px 14px; }
            .rec-1c-table-wrap {
                border-radius: 6px;
                overflow: hidden;
                background: #fff;
                box-shadow: inset 0 0 0 1px #d1d5db;
            }
            .rec-1c-table {
                width: 100%;
                min-width: 720px;
                border-collapse: collapse;
                background: #fff;
            }
            .rec-1c-table th,
            .rec-1c-table td {
                border: 1px solid #d1d5db;
                padding: 7px 10px;
                vertical-align: middle;
            }
            .rec-1c-table th {
                background: linear-gradient(180deg, #f3f4f6 0%, #e5e7eb 100%);
                font-weight: 600;
                text-align: left;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                color: #4b5563;
                white-space: nowrap;
            }
            .rec-1c-table td.rec-num {
                text-align: right;
                font-variant-numeric: tabular-nums;
                font-feature-settings: 'tnum' 1;
            }
            .rec-1c-table tbody tr {
                transition: background 0.12s ease;
            }
            .rec-1c-table tbody tr:hover {
                background: rgba(59, 130, 246, 0.06);
            }
            .rec-1c-table tfoot tr:hover {
                background: transparent;
            }
            .rec-1c-table a.row-link {
                color: #1d4ed8;
                text-decoration: none;
                font-weight: 600;
                border-bottom: 1px solid rgba(29, 78, 216, 0.25);
                transition: color 0.15s ease, border-color 0.15s ease;
            }
            .rec-1c-table a.row-link:hover {
                color: #1e3a8a;
                border-bottom-color: #1e3a8a;
            }
            .rec-1c-muted { font-size: 12px; color: #6b7280; padding: 12px 4px; line-height: 1.5; }
            .rec-debt-amt {
                color: #b91c1c;
                font-weight: 700;
                font-size: 13.5px;
                font-variant-numeric: tabular-nums;
                letter-spacing: -0.02em;
            }
            .rec-debt-amt-zero { color: #6b7280; font-weight: 600; font-size: 12px; font-variant-numeric: tabular-nums; }
            .rec-1c-total-row td {
                font-weight: 700;
                background: linear-gradient(180deg, #f9fafb 0%, #f3f4f6 100%);
                border-top: 2px solid #9ca3af;
                color: #111827;
            }
            .rec-split-table > thead > tr:first-child th {
                text-align: center;
                font-weight: 700;
                font-size: 12px;
                padding: 10px 12px;
                letter-spacing: 0.04em;
                text-transform: uppercase;
                border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            }
            .rec-split-table thead th.rec-split-group-paid {
                background: linear-gradient(180deg, #e0f2fe 0%, #bae6fd 55%, #a5d8f7 100%);
                color: #0c4a6e;
                box-shadow: inset 0 -1px 0 rgba(14, 116, 144, 0.2);
            }
            .rec-split-table thead th.rec-split-group-docs {
                background: linear-gradient(180deg, #fef9c3 0%, #fde68a 55%, #fcd34d 100%);
                color: #78350f;
                box-shadow: inset 0 -1px 0 rgba(146, 64, 14, 0.18);
            }
            .rec-split-table thead th.rec-split-sub-paid {
                background: linear-gradient(180deg, #f0f9ff 0%, #e0f2fe 100%);
                color: #1e3a5f;
                font-weight: 700;
                font-size: 11px;
                padding: 8px 10px;
                border-top: none;
                text-transform: none;
                letter-spacing: 0.02em;
            }
            .rec-split-table thead th.rec-split-sub-docs {
                background: linear-gradient(180deg, #fffbeb 0%, #fef3c7 100%);
                color: #713f12;
                font-weight: 700;
                font-size: 11px;
                padding: 8px 10px;
                border-top: none;
                text-transform: none;
                letter-spacing: 0.02em;
            }
            .rec-split-table .rec-split-divider {
                border-left: 3px solid #64748b !important;
                box-shadow: -2px 0 0 rgba(255, 255, 255, 0.65) inset;
            }
            .rec-1c-scope .text-neutral-600 { color: #6b7280 !important; }
            .rec-1c-scope .font-medium { font-weight: 600; }
        </style>

        <div class="rec-1c-scope space-y-3">
            <div class="rec-1c-panel">
                <div class="rec-1c-titlebar">
                    <h2>Сверка с контрагентами</h2>
                </div>
                <div class="rec-mode-row">
                    @if ($isList)
                        <a
                            href="{{ route('admin.reconciliation.index', ['mode' => 'buyers']) }}"
                            class="rec-mode-btn {{ $isBuyers ? 'rec-mode-active' : '' }}"
                        >Покупатели</a>
                        <a
                            href="{{ route('admin.reconciliation.index', ['mode' => 'sellers']) }}"
                            class="rec-mode-btn {{ ! $isBuyers ? 'rec-mode-active' : '' }}"
                        >Поставщики</a>
                    @else
                        <a
                            href="{{ route('admin.reconciliation.index', array_merge($baseQuery, ['mode' => 'buyers'])) }}"
                            class="rec-mode-btn {{ $isBuyers ? 'rec-mode-active' : '' }}"
                        >Покупатели</a>
                        <a
                            href="{{ route('admin.reconciliation.index', array_merge($baseQuery, ['mode' => 'sellers'])) }}"
                            class="rec-mode-btn {{ ! $isBuyers ? 'rec-mode-active' : '' }}"
                        >Поставщики</a>
                    @endif
                </div>
                @if ($counterparty !== null)
                    <form method="GET" action="{{ route('admin.reconciliation.index') }}" class="rec-1c-toolbar">
                        <input type="hidden" name="mode" value="{{ $mode }}" />
                        <input type="hidden" name="counterparty_id" value="{{ $counterparty->id }}" />
                        <div class="pb-0.5">
                            <a href="{{ route('admin.reconciliation.index', ['mode' => $mode]) }}" class="rec-back-link">← К списку</a>
                        </div>
                        <div>
                            <label for="rec_from">Период с</label>
                            <input id="rec_from" type="date" name="date_from" value="{{ $from->format('Y-m-d') }}" />
                        </div>
                        <div>
                            <label for="rec_to">по</label>
                            <input id="rec_to" type="date" name="date_to" value="{{ $to->format('Y-m-d') }}" />
                        </div>
                        <div class="pb-0.5">
                            <button type="submit" class="rec-1c-btn">Показать</button>
                        </div>
                    </form>
                @endif
            </div>

            @if (! $branchHasAnyCounterparty)
                <div class="rec-1c-panel">
                    <div class="rec-1c-body rec-1c-muted">
                        Сначала добавьте контрагента в справочнике.
                        <a href="{{ route('admin.counterparties.create') }}" class="font-semibold text-emerald-900 underline">Создать</a>
                    </div>
                </div>
            @elseif ($counterparties->isEmpty())
                <div class="rec-1c-panel">
                    <div class="rec-1c-body rec-1c-muted">
                        @if ($isBuyers)
                            Нет контрагентов с типом «Покупатель» или «Прочее». Добавьте в справочнике или смените тип карточки.
                        @else
                            Нет контрагентов с типом «Поставщик» или «Прочее». Добавьте в справочнике или смените тип карточки.
                        @endif
                        <a href="{{ route('admin.counterparties.index') }}" class="font-semibold text-emerald-900 underline">Справочник</a>
                    </div>
                </div>
            @elseif ($isList)
                <div class="rec-1c-panel">
                    <div class="rec-1c-subhead">
                        @if ($isBuyers)
                            Покупатели
                        @else
                            Поставщики
                        @endif
                    </div>
                    <div class="rec-1c-body">
                        <div class="overflow-x-auto -mx-0.5">
                            <div class="rec-1c-table-wrap">
                            <table class="rec-1c-table">
                                <thead>
                                    <tr>
                                        <th>Контрагент</th>
                                        <th class="rec-num">Начальные долги</th>
                                        @if ($isBuyers)
                                            <th class="rec-num">Всего купил у нас</th>
                                            <th class="rec-num">Всего перевёл</th>
                                            <th class="rec-num">Долг нам (сейчас)</th>
                                        @else
                                            <th class="rec-num">Всего закупили у него</th>
                                            <th class="rec-num">Всего оплатили</th>
                                            <th class="rec-num">Мы должны (сейчас)</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($summaryRows as $sr)
                                        @php
                                            $cp = $sr['counterparty'];
                                            $cpLabel = trim((string) $cp->full_name) !== '' ? $cp->full_name : $cp->name;
                                            $debtNonZero = bccomp((string) $sr['debt'], '0', 2) !== 0;
                                        @endphp
                                        <tr>
                                            <td>
                                                <a
                                                    class="row-link"
                                                    href="{{ route('admin.reconciliation.index', ['mode' => $mode, 'counterparty_id' => $cp->id]) }}"
                                                >{{ $cpLabel }}</a>
                                            </td>
                                            <td class="rec-num">{{ $fmtSigned($sr['opening_debt_card'] ?? '0') }}</td>
                                            <td class="rec-num">{{ $fmtSigned($sr['period_purchases']) }}</td>
                                            <td class="rec-num">{{ $fmtSigned($sr['paid']) }}</td>
                                            <td class="rec-num">
                                                @if ($debtNonZero)
                                                    <span class="rec-debt-amt">{{ $fmtSigned($sr['debt']) }}</span>
                                                @else
                                                    {{ $fmtSigned($sr['debt']) }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                @if ($summaryRows->isNotEmpty())
                                    <tfoot>
                                        <tr class="rec-1c-total-row">
                                            <td>Итого по списку</td>
                                            <td class="rec-num">{{ $fmtSigned($totalOpeningCard) }}</td>
                                            <td class="rec-num">{{ $fmtSigned($totalPeriodPurchases) }}</td>
                                            <td class="rec-num">{{ $fmtSigned($totalPaid) }}</td>
                                            <td class="rec-num">
                                                @if (bccomp($totalDebt, '0', 2) !== 0)
                                                    <span class="rec-debt-amt">{{ $fmtSigned($totalDebt) }}</span>
                                                @else
                                                    {{ $fmtSigned($totalDebt) }}
                                                @endif
                                            </td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                @if ($isBuyers)
                    @php
                        $buyerPayV = $buyerPaymentsList->values();
                        $buyerDocV = $buyerDocs->values();
                        $buyerPairRows = max($buyerPayV->count(), $buyerDocV->count());
                    @endphp
                    <div class="rec-1c-panel">
                        <div class="rec-1c-subhead">Детально: оплаты и продажи (рядом для наглядности)</div>
                        <div class="rec-1c-body">
                            @if ($buyerPairRows === 0)
                                <p class="rec-1c-muted">Нет оплат и документов за период.</p>
                            @else
                                <div class="overflow-x-auto -mx-0.5">
                                    <div class="rec-1c-table-wrap">
                                    <table class="rec-1c-table rec-split-table">
                                        <thead>
                                            <tr>
                                                <th colspan="2" class="rec-split-group-paid">Оплатил</th>
                                                <th colspan="3" class="rec-split-group-docs rec-split-divider">Продажи и возвраты</th>
                                            </tr>
                                            <tr>
                                                <th class="rec-split-sub-paid">Дата</th>
                                                <th class="rec-num rec-split-sub-paid">Сумма оплаты</th>
                                                <th class="rec-split-divider rec-split-sub-docs">Дата</th>
                                                <th class="rec-split-sub-docs">Документ</th>
                                                <th class="rec-num rec-split-sub-docs">Сумма</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @for ($i = 0; $i < $buyerPairRows; $i++)
                                                <tr>
                                                    <td class="whitespace-nowrap">
                                                        @isset($buyerPayV[$i])
                                                            {{ $buyerPayV[$i]['date']->format('d.m.Y') }}
                                                        @endisset
                                                    </td>
                                                    <td class="rec-num">
                                                        @isset($buyerPayV[$i])
                                                            {{ $fmt($buyerPayV[$i]['credit']) }}
                                                        @endisset
                                                    </td>
                                                    <td class="whitespace-nowrap rec-split-divider">
                                                        @isset($buyerDocV[$i])
                                                            {{ $buyerDocV[$i]['date']->format('d.m.Y') }}
                                                        @endisset
                                                    </td>
                                                    <td class="max-w-xs sm:max-w-md">
                                                        @isset($buyerDocV[$i])
                                                            <span class="font-medium">{{ $buyerDocV[$i]['title'] }}</span>
                                                            @if (($buyerDocV[$i]['kind'] ?? '') !== 'opening_card' && trim((string) ($buyerDocV[$i]['detail'] ?? '')) !== '')
                                                                <span class="text-neutral-600"> · {{ $buyerDocV[$i]['detail'] }}</span>
                                                            @endif
                                                        @endisset
                                                    </td>
                                                    <td class="rec-num">
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
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    @php
                        $supPayV = $supplierPaymentsList->values();
                        $supDocV = $supplierDocs->values();
                        $supPairRows = max($supPayV->count(), $supDocV->count());
                    @endphp
                    <div class="rec-1c-panel">
                        <div class="rec-1c-subhead">Детально: оплаты и закупки (рядом для наглядности)</div>
                        <div class="rec-1c-body">
                            @if ($supPairRows === 0)
                                <p class="rec-1c-muted">Нет оплат и закупок за период.</p>
                            @else
                                <div class="overflow-x-auto -mx-0.5">
                                    <div class="rec-1c-table-wrap">
                                    <table class="rec-1c-table rec-split-table">
                                        <thead>
                                            <tr>
                                                <th colspan="2" class="rec-split-group-paid">Оплатили</th>
                                                <th colspan="3" class="rec-split-group-docs rec-split-divider">Закупки</th>
                                            </tr>
                                            <tr>
                                                <th class="rec-split-sub-paid">Дата</th>
                                                <th class="rec-num rec-split-sub-paid">Сумма оплаты</th>
                                                <th class="rec-split-divider rec-split-sub-docs">Дата</th>
                                                <th class="rec-split-sub-docs">Документ</th>
                                                <th class="rec-num rec-split-sub-docs">Сумма</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @for ($i = 0; $i < $supPairRows; $i++)
                                                <tr>
                                                    <td class="whitespace-nowrap">
                                                        @isset($supPayV[$i])
                                                            {{ $supPayV[$i]['date']->format('d.m.Y') }}
                                                        @endisset
                                                    </td>
                                                    <td class="rec-num">
                                                        @isset($supPayV[$i])
                                                            {{ $fmt($supPayV[$i]['debit']) }}
                                                        @endisset
                                                    </td>
                                                    <td class="whitespace-nowrap rec-split-divider">
                                                        @isset($supDocV[$i])
                                                            {{ $supDocV[$i]['date']->format('d.m.Y') }}
                                                        @endisset
                                                    </td>
                                                    <td class="max-w-xs sm:max-w-md">
                                                        @isset($supDocV[$i])
                                                            <span class="font-medium">{{ $supDocV[$i]['title'] }}</span>
                                                            @if (($supDocV[$i]['kind'] ?? '') !== 'opening_card' && trim((string) ($supDocV[$i]['detail'] ?? '')) !== '')
                                                                <span class="text-neutral-600"> · {{ $supDocV[$i]['detail'] }}</span>
                                                            @endif
                                                        @endisset
                                                    </td>
                                                    <td class="rec-num">
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
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-admin-layout>
