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
    $exportQuery = [
        'mode' => $mode,
        'date_from' => $from->format('Y-m-d'),
        'date_to' => $to->format('Y-m-d'),
    ];
    if ($counterparty !== null) {
        $exportQuery['counterparty_id'] = $counterparty->id;
    }
@endphp
<x-admin-layout pageTitle="Сверка с контрагентами" main-class="px-3 py-5 sm:px-5 lg:px-8 max-w-[1600px] mx-auto w-full">
    <div class="w-full min-w-0">
        <style>
            /* Типографика: читаемый ui-sans stack + чуть крупнее базовый кегль */
            .rec-1c-scope {
                font-family:
                    ui-sans-serif,
                    system-ui,
                    -apple-system,
                    'Segoe UI',
                    Roboto,
                    'Helvetica Neue',
                    Arial,
                    sans-serif;
                font-size: 13px;
                line-height: 1.5;
                color: #0f172a;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
                text-rendering: optimizeLegibility;
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
                font-size: 1.125rem;
                font-weight: 700;
                letter-spacing: -0.025em;
                line-height: 1.3;
                color: #020617;
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
                font-size: 12px;
                font-weight: 600;
                border-radius: 5px;
                border: 1px solid #9ca3af;
                background: linear-gradient(180deg, #ffffff 0%, #e8eaee 100%);
                color: #0f172a;
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
                font-size: 12px;
                font-weight: 600;
                color: #334155;
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
                font-size: 12px;
                font-weight: 600;
                line-height: 1.2;
                border-radius: 4px;
                border: 1px solid #9ca3af;
                background: linear-gradient(180deg, #ffffff 0%, #e8eaee 100%);
                color: #0f172a;
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
                font-size: 12px;
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
                padding: 9px 14px;
                font-size: 14px;
                font-weight: 700;
                color: #0f172a;
                letter-spacing: -0.012em;
                line-height: 1.4;
            }
            .rec-1c-subhead.rec-1c-subhead-row {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
            }
            .rec-1c-subhead a.rec-1c-btn,
            .rec-1c-toolbar a.rec-1c-btn {
                text-decoration: none;
                color: inherit;
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
                font-size: 11.5px;
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
            .rec-1c-muted {
                font-size: 13px;
                color: #64748b;
                padding: 12px 4px;
                line-height: 1.55;
            }
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
            .rec-summary-debt {
                margin-top: 16px;
                padding: 0;
                border: none;
                background: transparent;
                font-size: clamp(15px, 0.375vw + 14px, 17px);
                line-height: 1.6;
                color: #1e293b;
            }
            .rec-summary-debt .rec-summary-title {
                font-size: clamp(1rem, 0.5vw + 0.9rem, 1.1875rem);
                font-weight: 700;
                letter-spacing: -0.022em;
                line-height: 1.35;
                color: #020617;
                margin: 0 0 14px;
            }
            .rec-summary-debt .rec-summary-lines {
                margin: 0;
                padding: 0;
                list-style: none;
            }
            .rec-summary-debt .rec-summary-lines > li {
                margin: 0 0 0.52em;
                padding: 0;
                font-weight: 500;
                letter-spacing: -0.012em;
            }
            .rec-summary-debt .rec-summary-lines > li:last-child {
                margin-bottom: 0;
            }
            .rec-summary-debt .rec-summary-debt-num {
                color: #b91c1c;
                font-weight: 700;
                font-size: 1.03em;
                letter-spacing: -0.03em;
                font-variant-numeric: tabular-nums;
                font-feature-settings: 'tnum' 1, 'lnum' 1;
            }
            .rec-summary-debt .rec-summary-lines .tabular-nums {
                font-weight: 600;
                font-variant-numeric: tabular-nums;
                font-feature-settings: 'tnum' 1, 'lnum' 1;
                letter-spacing: -0.02em;
                color: #0f172a;
            }
            .rec-summary-debt .rec-summary-note {
                margin: 14px 0 0;
                font-size: max(13px, 0.875em);
                font-weight: 400;
                color: #64748b;
                line-height: 1.58;
                letter-spacing: -0.005em;
            }
            .rec-summary-debt .rec-summary-grid {
                display: flex;
                flex-wrap: wrap;
                gap: 10px 24px;
            }
            .rec-1c-search-wrap {
                display: flex;
                flex-direction: column;
                align-items: stretch;
                gap: 3px;
                min-width: min(100%, 17rem);
                max-width: min(100%, 26rem);
                flex: 1 1 14rem;
            }
            .rec-1c-search-wrap > span:first-of-type {
                display: block;
                font-size: 11px;
                font-weight: 600;
                color: #64748b;
            }
            .rec-1c-search-input {
                width: 100%;
                min-height: 30px;
                padding: 5px 10px;
                border: 1px solid #9ca3af;
                border-radius: 4px;
                font: inherit;
                font-size: 12px;
                background: #fff;
                box-sizing: border-box;
                transition: border-color 0.15s ease, box-shadow 0.15s ease;
                color: #0f172a;
            }
            .rec-1c-search-input::placeholder {
                color: #94a3b8;
            }
            .rec-1c-search-input:focus {
                outline: none;
                border-color: #3b82f6;
                box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
            }
            .rec-1c-search-hint {
                font-size: 11px;
                font-weight: 500;
                color: #64748b;
                white-space: nowrap;
            }
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
                        <div class="pb-0.5 flex flex-wrap items-center gap-2">
                            <a
                                href="{{ route('admin.reconciliation.export-excel', $exportQuery) }}"
                                class="rec-1c-btn"
                                data-no-nav-loading
                            >Скачать Excel</a>
                            <a
                                href="{{ route('admin.reconciliation.export-pdf', $exportQuery) }}"
                                class="rec-1c-btn"
                                data-no-nav-loading
                            >Скачать PDF</a>
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
                            Нет контрагентов с типом «Покупатель». Добавьте в справочнике или укажите тип «Покупатель» у карточки.
                        @else
                            Нет контрагентов с типом «Поставщик». Добавьте в справочнике или укажите тип «Поставщик» у карточки.
                        @endif
                        <a href="{{ route('admin.counterparties.index') }}" class="font-semibold text-emerald-900 underline">Справочник</a>
                    </div>
                </div>
            @elseif ($isList)
                @php
                    $recSearchHaystacks = $summaryRows->map(function ($sr) {
                        $cp = $sr['counterparty'];
                        $cpLabel = trim((string) $cp->full_name) !== '' ? $cp->full_name : $cp->name;

                        return mb_strtolower((string) $cpLabel, 'UTF-8');
                    })->values()->all();
                    $recSearchPlaceholder = $isBuyers ? 'Имя или часть имени покупателя' : 'Имя или часть имени поставщика';
                @endphp
                <div
                    class="rec-1c-panel"
                    x-data="{
                        q: '',
                        haystacks: @js($recSearchHaystacks),
                        rowVisible(i) {
                            const t = String(this.q).trim().toLowerCase();
                            if (! t) return true;

                            return (this.haystacks[i] ?? '').includes(t);
                        },
                        matchCount() {
                            const t = String(this.q).trim().toLowerCase();
                            const n = this.haystacks.length;
                            if (! t || n === 0) {
                                return n;
                            }

                            let c = 0;
                            for (let i = 0; i < n; i += 1) {
                                const h = String(this.haystacks[i] ?? '');
                                if (h.includes(t)) {
                                    c += 1;
                                }
                            }

                            return c;
                        },
                    }"
                >
                    <div class="rec-1c-subhead rec-1c-subhead-row">
                        <span class="min-w-[5rem] shrink-0">
                            @if ($isBuyers)
                                Покупатели
                            @else
                                Поставщики
                            @endif
                        </span>
                        <div class="flex w-full min-w-0 flex-1 flex-wrap items-end justify-between gap-x-4 gap-y-3">
                            <label class="rec-1c-search-wrap">
                                <span>Поиск по названию</span>
                                <input
                                    type="search"
                                    name="counterparty_search"
                                    class="rec-1c-search-input"
                                    x-model.debounce.200ms="q"
                                    maxlength="200"
                                    autocomplete="off"
                                    aria-label="{{ $recSearchPlaceholder }}"
                                    placeholder="{{ $recSearchPlaceholder }}…"
                                />
                                <span class="rec-1c-search-hint" x-show="q.trim().length > 0" x-cloak x-text="'Показано: '+matchCount()+' из '+haystacks.length"></span>
                            </label>
                            <span class="flex flex-wrap items-center gap-2 pb-px">
                                <a
                                    href="{{ route('admin.reconciliation.export-excel', $exportQuery) }}"
                                    class="rec-1c-btn"
                                    data-no-nav-loading
                                >Скачать Excel</a>
                                <a
                                    href="{{ route('admin.reconciliation.export-pdf', $exportQuery) }}"
                                    class="rec-1c-btn"
                                    data-no-nav-loading
                                >Скачать PDF</a>
                            </span>
                        </div>
                    </div>
                    <div class="rec-1c-body">
                        <p
                            class="rec-1c-muted pb-3 pt-0"
                            x-show="q.trim().length > 0 && matchCount() === 0"
                            x-cloak
                        >Никого не найдено — попробуйте другой запрос.</p>
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
                                        <tr x-show="rowVisible({{ $loop->index }})">
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
                                    <tfoot x-show="!String(q).trim()">
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
                            <div class="rec-summary-debt">
                                <div class="rec-summary-title">Итог за период ({{ $from->format('d.m.Y') }} — {{ $to->format('d.m.Y') }})</div>
                                <ul class="rec-summary-lines">
                                    <li>Сумма продаж — <span class="tabular-nums">{{ $fmtSigned($buyerSalesPeriod) }}</span></li>
                                    <li>Сумма оплат от клиента — <span class="tabular-nums">{{ $fmtSigned($paidIncomePeriod) }}</span></li>
                                    <li>
                                        Итоговый долг (нам должны) —
                                        @if (bccomp($buyerClosing, '0', 2) !== 0)
                                            <span class="rec-summary-debt-num">{{ $fmtSigned($buyerClosing) }}</span>
                                        @else
                                            <span class="tabular-nums">{{ $fmtSigned($buyerClosing) }}</span>
                                        @endif
                                    </li>
                                    <li>Документов продаж — {{ number_format((int) $buyerSaleCount, 0, '', ' ') }}</li>
                                    <li>Средний чек (по сумме) — <span class="tabular-nums">{{ $fmtSigned($buyerAvgSale) }}</span></li>
                                    <li>Документов возвратов — {{ number_format((int) $buyerReturnCount, 0, '', ' ') }}</li>
                                    <li>Сумма возвратов — <span class="tabular-nums">{{ $fmtSigned($buyerReturnsPeriod) }}</span></li>
                                    <li>Сальдо на {{ $from->format('d.m.Y') }} — <span class="tabular-nums">{{ $fmtSigned($buyerOpening) }}</span></li>
                                </ul>
                                <p class="rec-summary-note">
                                    Итоговый долг — остаток на {{ $to->format('d.m.Y') }} по данным сверки (все строки таблицы и начальное сальдо учтены).
                                </p>
                            </div>
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
                            <div class="rec-summary-debt">
                                <div class="rec-summary-title">Итог за период ({{ $from->format('d.m.Y') }} — {{ $to->format('d.m.Y') }})</div>
                                <ul class="rec-summary-lines">
                                    <li>Сумма закупок за вычетом возвратов поставщику — <span class="tabular-nums">{{ $fmtSigned($supplierPurchasesPeriod) }}</span></li>
                                    <li>Сумма оплат поставщику — <span class="tabular-nums">{{ $fmtSigned($paidExpensePeriod) }}</span></li>
                                    <li>
                                        Итоговый долг (мы должны) —
                                        @if (bccomp($supplierClosing, '0', 2) !== 0)
                                            <span class="rec-summary-debt-num">{{ $fmtSigned($supplierClosing) }}</span>
                                        @else
                                            <span class="tabular-nums">{{ $fmtSigned($supplierClosing) }}</span>
                                        @endif
                                    </li>
                                    <li>Документов закупок — {{ number_format((int) $supplierPurchaseCount, 0, '', ' ') }}</li>
                                    <li>Средняя закупка (по документам) — <span class="tabular-nums">{{ $fmtSigned($supplierAvgPurchase) }}</span></li>
                                    <li>Документов возвратов — {{ number_format((int) $supplierReturnCount, 0, '', ' ') }}</li>
                                    <li>Сумма возвратов — <span class="tabular-nums">{{ $fmtSigned($supplierReturnsPeriod) }}</span></li>
                                    <li>Сальдо на {{ $from->format('d.m.Y') }} — <span class="tabular-nums">{{ $fmtSigned($supplierOpening) }}</span></li>
                                </ul>
                                <p class="rec-summary-note">
                                    Итоговый долг — остаток «мы должны» на {{ $to->format('d.m.Y') }} по данным сверки.
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-admin-layout>
