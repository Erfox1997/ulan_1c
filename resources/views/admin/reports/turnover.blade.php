@php
    use App\Support\InvoiceNakladnayaFormatter;

    /** @var callable(float,float): array{dt:string,ct:string,dtNeg:bool,ctNeg:bool} $saldoCells */
    $saldoCells = static function (float $dt, float $ct): array {
        $net = round((float) $dt - (float) $ct, 2);
        if (abs($net) < 0.005) {
            return ['dt' => '—', 'ct' => '—', 'dtNeg' => false, 'ctNeg' => false];
        }
        if ($net > 0) {
            return [
                'dt' => InvoiceNakladnayaFormatter::formatMoney($net),
                'ct' => '—',
                'dtNeg' => false,
                'ctNeg' => false,
            ];
        }

        return [
            'dt' => InvoiceNakladnayaFormatter::formatMoney($net),
            'ct' => '—',
            'dtNeg' => true,
            'ctNeg' => false,
        ];
    };

    /** Сальдо пассива и доходных счётов (норма — «кредит»). Избыток дебета — красным в колонке дебет. */
    $saldoPassive = static function (float $dt, float $ct): array {
        $netCred = round((float) $ct - (float) $dt, 2);
        if (abs($netCred) < 0.005) {
            return ['dt' => '—', 'ct' => '—', 'dtNeg' => false, 'ctNeg' => false];
        }
        if ($netCred > 0) {
            return [
                'dt' => '—',
                'ct' => InvoiceNakladnayaFormatter::formatMoney($netCred),
                'dtNeg' => false,
                'ctNeg' => false,
            ];
        }

        return [
            'dt' => InvoiceNakladnayaFormatter::formatMoney($netCred),
            'ct' => '—',
            'dtNeg' => true,
            'ctNeg' => false,
        ];
    };

    $turnCells = static function (float $dt, float $ct): array {
        $f = static fn (float $v): string => abs($v) < 0.005 ? '—' : InvoiceNakladnayaFormatter::formatMoney($v);

        return ['dt' => $f($dt), 'ct' => $f($ct)];
    };

    $sumCells = static function (float $dt, float $ct): array {
        $f = static fn (float $v): string => abs($v) < 0.005 ? '—' : InvoiceNakladnayaFormatter::formatMoney($v);

        return ['dt' => $f($dt), 'ct' => $f($ct)];
    };

    $pickSaldoCells = static function (array $row) use ($saldoCells, $saldoPassive): callable {
        if (($row['balance_kind'] ?? '') === 'passive') {
            return $saldoPassive;
        }

        return $saldoCells;
    };

    $sections = $osv['sections'] ?? [];
    $currencyCodes = $osv['currency_codes'] ?? [];
    $multiCurrency = count($currencyCodes) > 1;
    $dash = $osv['dashboard'] ?? [];
    $dClose = $dash['closing'] ?? [];
    $dTurn = $dash['turnover'] ?? [];

    $payNet = round((float) ($dClose['payables'] ?? 0), 2);
    $recNet = round((float) ($dClose['receivables'] ?? 0), 2);

    $currencySuffix = ($currencyCodes[0] ?? '') !== '' ? ' '.($currencyCodes[0]) : '';
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="c1c-osv-page">
    <style>
        .c1c-osv-page {
            --c1c-head: #ddebf7;
            --c1c-border: #8eaad8;
            --c1c-border-soft: #b4c6e7;
            --c1c-text: #000;
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            background: #e8ecf0;
            padding: 12px 16px 24px;
        }
        .c1c-doc {
            max-width: 100%;
            font-variant-numeric: tabular-nums;
            background: #fff;
            border: 1px solid var(--c1c-border-soft);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }
        .c1c-doc-header {
            padding: 14px 18px 12px;
            border-bottom: 1px solid var(--c1c-border-soft);
        }
        .c1c-doc-header h1 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: var(--c1c-text);
        }
        .c1c-toolbar {
            padding: 10px 16px;
            border-bottom: 1px solid var(--c1c-border-soft);
            background: #f3f6fa;
        }
        .c1c-hint {
            margin: 0 16px 12px;
            padding: 8px 0 0;
            font-size: 11px;
            color: #555;
            line-height: 1.45;
        }
        .c1c-wrap {
            padding: 0 12px 16px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .c1c-wrap-inner {
            border: 1px solid var(--c1c-border);
            border-radius: 4px;
            overflow: hidden;
            background: #fff;
            min-width: min(100%, 1020px);
        }
        .c1c-table {
            width: 100%;
            min-width: 1020px;
            border-collapse: collapse;
            border-spacing: 0;
            font-size: 13px;
            line-height: 1.4;
            color: var(--c1c-text);
        }
        .c1c-table thead th {
            background: var(--c1c-head);
            border: 1px solid var(--c1c-border);
            font-weight: 600;
            padding: 10px 9px;
            text-align: center;
            vertical-align: middle;
            position: sticky;
            top: 0;
            z-index: 2;
            box-shadow: 0 1px 0 var(--c1c-border-soft);
        }
        .c1c-table tbody td {
            border: 1px solid #b8c5df;
            padding: 8px 10px;
            vertical-align: middle;
        }
        .c1c-num {
            text-align: right;
            white-space: nowrap;
            font-weight: 500;
            min-width: 8rem;
            background: #fafbfd;
        }
        .c1c-table tbody tr.c1c-main td.c1c-code-cell {
            background: #fff;
        }
        .c1c-table tbody tr.c1c-main td.c1c-ind {
            background: #f3f6fa;
        }
        .c1c-main td.c1c-code-cell {
            font-weight: 700;
        }
        .c1c-code {
            display: block;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .c1c-acct-name {
            display: block;
            margin-top: 4px;
            font-weight: 400;
            font-size: 12px;
            line-height: 1.35;
            color: #374151;
        }
        .c1c-ind {
            text-align: center;
        }
        .c1c-neg {
            color: #c00000;
            font-weight: 700;
        }
        .c1c-org td {
            background: #d8e6f7;
            font-weight: 700;
            font-size: 12px;
            padding: 8px 10px;
            border-color: #9db7dc;
        }
        .c1c-section td {
            background: #c5d9f0;
            font-weight: 700;
            font-size: 13px;
            padding: 10px 11px;
            color: #0f172a;
            border-color: #7fa3d4;
        }
        .c1c-subtotal td {
            background: #e1eaf7;
            font-weight: 600;
            font-size: 12px;
            padding: 8px 10px;
        }
        .c1c-grand td {
            background: var(--c1c-head);
            border: 1px solid var(--c1c-border);
            font-weight: 700;
        }
        .c1c-grand td.c1c-num {
            background: var(--c1c-head);
        }
        .c1c-subtotal td.c1c-num {
            background: #e1eaf7;
        }
        .c1c-drill {
            display: block;
            width: 100%;
            margin: 0;
            padding: 4px 6px;
            border: none;
            border-radius: 2px;
            background: transparent;
            font: inherit;
            font-weight: 600;
            font-variant-numeric: inherit;
            text-align: right;
            cursor: pointer;
            color: inherit;
            text-decoration: underline dotted rgba(37, 99, 235, 0.45);
            text-underline-offset: 2px;
        }
        .c1c-drill:hover {
            background: rgba(58, 121, 226, 0.12);
        }
        .c1c-drill.c1c-neg {
            color: #c00000;
        }
        .c1c-drill-overlay [x-cloak] {
            display: none !important;
        }
        .c1c-about {
            margin: 0 12px 10px;
            border: 1px solid var(--c1c-border-soft);
            border-radius: 4px;
            background: #fff;
            overflow: hidden;
        }
        .c1c-about-title {
            margin: 0;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 700;
            color: var(--c1c-text);
            background: var(--c1c-head);
            border-bottom: 1px solid var(--c1c-border-soft);
        }
        .c1c-about-grid {
            display: grid;
            gap: 12px;
            padding: 12px 12px 14px;
            font-size: 11px;
            line-height: 1.45;
            color: #333;
        }
        @media (min-width: 768px) {
            .c1c-about-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        .c1c-about-item strong {
            display: block;
            margin-bottom: 4px;
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
        }
        .c1c-about-scope {
            margin: 0 12px 12px;
            padding: 9px 11px;
            font-size: 11px;
            line-height: 1.5;
            color: #334155;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            border-left: 3px solid #8eaad8;
            background: #f8fafc;
        }
        .c1c-about-scope strong {
            color: #0f172a;
        }
        .c1c-dashboard {
            margin: 0 12px 16px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .c1c-dash-section {
            border: 1px solid var(--c1c-border-soft);
            border-radius: 4px;
            background: #fff;
            overflow: hidden;
        }
        .c1c-dash-head {
            padding: 10px 14px;
            background: var(--c1c-head);
            border-bottom: 1px solid var(--c1c-border-soft);
        }
        .c1c-dash-head h2 {
            margin: 0;
            font-size: 13px;
            font-weight: 700;
            color: var(--c1c-text);
        }
        .c1c-dash-cards {
            display: grid;
            gap: 10px;
            padding: 12px 14px 14px;
        }
        @media (min-width: 640px) {
            .c1c-dash-cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (min-width: 1100px) {
            .c1c-dash-cards.cols-5 {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
        }
        .c1c-dash-card {
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 10px 11px;
            background: #fafbfd;
        }
        .c1c-dash-card dt {
            margin: 0;
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
        }
        .c1c-dash-card dd {
            margin: 6px 0 0;
            font-size: 14px;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            color: #111;
        }
        .c1c-dash-card .c1c-dash-note {
            margin-top: 4px;
            font-size: 10px;
            font-weight: 500;
            color: #64748b;
            line-height: 1.35;
        }
        .c1c-dash-turn {
            display: grid;
            gap: 12px;
            padding: 12px 14px 14px;
        }
        @media (min-width: 768px) {
            .c1c-dash-turn {
                grid-template-columns: 1fr 1fr;
            }
        }
        .c1c-dash-turnbox {
            border: 1px solid var(--c1c-border-soft);
            border-radius: 4px;
            padding: 12px 14px;
            background: #f8fafc;
        }
        .c1c-dash-turnbox .label {
            font-size: 11px;
            font-weight: 700;
            color: #334155;
        }
        .c1c-dash-turnbox .value {
            margin-top: 6px;
            font-size: 18px;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }
        .c1c-detail-title {
            margin: 8px 12px 0;
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
        }
        @media print {
            .c1c-drill {
                text-decoration: none !important;
                pointer-events: none;
            }
            .c1c-no-print { display: none !important; }
            .c1c-osv-page {
                background: #fff;
                padding: 0;
            }
            .c1c-doc {
                border: none;
                box-shadow: none;
            }
            .c1c-table { font-size: 10pt; }
            .c1c-table thead th { position: static; }
        }
    </style>

    <div
        class="c1c-doc mx-auto max-w-[120rem]"
        x-data="{
            drillOpen: false,
            drillLoading: false,
            drillErr: null,
            drillData: null,
            drillUrl: @js(route('admin.reports.turnover.detail')),
            filterFrom: @js($filterFrom),
            filterTo: @js($filterTo),
            async drill(kind, accountId) {
                this.drillOpen = true;
                this.drillLoading = true;
                this.drillErr = null;
                this.drillData = null;
                try {
                    const u = new URL(this.drillUrl, window.location.origin);
                    u.searchParams.set('account_id', String(accountId));
                    u.searchParams.set('from', this.filterFrom);
                    u.searchParams.set('to', this.filterTo);
                    u.searchParams.set('kind', kind);
                    const res = await fetch(u.toString(), {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) {
                        const j = await res.json().catch(() => ({}));
                        throw new Error(j.message || 'Не удалось загрузить расшифровку');
                    }
                    this.drillData = await res.json();
                } catch (e) {
                    this.drillErr = e.message || 'Ошибка';
                } finally {
                    this.drillLoading = false;
                }
            },
            drillClose() {
                this.drillOpen = false;
            },
        }"
        @keydown.escape.window="drillOpen && drillClose()"
    >
        <header class="c1c-doc-header">
            <h1>Оборотно-сальдовая ведомость</h1>
            <div class="mt-2 text-[11px] text-[#444]">
                <div><span class="text-[#666]">Период:</span> {{ $periodLabel }}</div>
                @if (! empty($branchName))
                    <div><span class="text-[#666]">Филиал:</span> {{ $branchName }}</div>
                @endif
            </div>
        </header>

        <div class="c1c-toolbar c1c-no-print">
            <div class="flex flex-wrap items-end justify-between gap-3">
                @include('admin.reports.partials.period-filter', [
                    'action' => route('admin.reports.turnover'),
                    'filterFrom' => $filterFrom,
                    'filterTo' => $filterTo,
                ])
                <div class="flex flex-wrap gap-2">
                    <a
                        href="{{ route('admin.reports.turnover.pdf', request()->query()) }}"
                        class="rounded border border-[#8eaad8] bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50"
                    >Скачать PDF</a>
                    <button
                        type="button"
                        class="rounded border border-[#8eaad8] bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50"
                        onclick="window.print()"
                    >
                        Печать
                    </button>
                </div>
            </div>
        </div>

        <div class="c1c-dashboard" aria-label="Сводка ОСВ">
            <section class="c1c-dash-section">
                <div class="c1c-dash-head">
                    <h2>1. Сальдо на конец периода</h2>
                </div>
                <dl class="c1c-dash-cards cols-5">
                    <div class="c1c-dash-card">
                        <dt>Касса</dt>
                        <dd class="{{ ($dClose['cash'] ?? 0) < -0.005 ? 'c1c-neg' : '' }}">
                            {{ InvoiceNakladnayaFormatter::formatMoney((float) ($dClose['cash'] ?? 0)) }}{{ $currencySuffix }}
                        </dd>
                        <p class="c1c-dash-note">Остаток денег в кассе по учёту «Банк и касса»</p>
                    </div>
                    <div class="c1c-dash-card">
                        <dt>Расчётные счета</dt>
                        <dd class="{{ ($dClose['bank'] ?? 0) < -0.005 ? 'c1c-neg' : '' }}">
                            {{ InvoiceNakladnayaFormatter::formatMoney((float) ($dClose['bank'] ?? 0)) }}{{ $currencySuffix }}
                        </dd>
                        <p class="c1c-dash-note">Банк (все счёта организаций, кроме кассы)</p>
                    </div>
                    <div class="c1c-dash-card">
                        <dt>Дебиторская задолженность</dt>
                        <dd class="{{ $recNet < -0.005 ? 'c1c-neg' : '' }}">
                            {{ InvoiceNakladnayaFormatter::formatMoney($recNet) }}{{ $currencySuffix }}
                        </dd>
                        <p class="c1c-dash-note">Сколько должны <strong>вам</strong> (контрагенты из справочника + долги в чеках)</p>
                    </div>
                    <div class="c1c-dash-card">
                        <dt>Кредиторская задолженность</dt>
                        <dd>
                            @if ($payNet >= -0.005)
                                {{ InvoiceNakladnayaFormatter::formatMoney(max(0.0, $payNet)) }}{{ $currencySuffix }}
                            @else
                                <span class="c1c-neg">{{ InvoiceNakladnayaFormatter::formatMoney($payNet) }}{{ $currencySuffix }}</span>
                            @endif
                        </dd>
                        <p class="c1c-dash-note">
                            Сколько должны <strong>вы</strong> поставщикам. Отрицательное значение — аванс / переплата поставщику.
                        </p>
                    </div>
                    <div class="c1c-dash-card">
                        <dt>Товар на складе</dt>
                        <dd>{{ InvoiceNakladnayaFormatter::formatMoney((float) ($dClose['inventory'] ?? 0)) }}{{ $currencySuffix }}</dd>
                        <p class="c1c-dash-note">Оценка запасов по учётной себестоимости из остатков</p>
                    </div>
                </dl>
                @if ($multiCurrency)
                    <p class="c1c-hint !mt-0 !pt-0 !px-4 !pb-3">
                        Несколько валют в счетах ({{ implode(', ', $currencyCodes) }}): суммы в блоке «Деньги» и в сводке выше <strong>не пересчитываются</strong> по курсу.
                    </p>
                @endif
            </section>

            <section class="c1c-dash-section">
                <div class="c1c-dash-head">
                    <h2>2. Обороты за период</h2>
                </div>
                <div class="c1c-dash-turn">
                    <div class="c1c-dash-turnbox">
                        <div class="label">Дебет — поступления (приход по строкам отчёта)</div>
                        <div class="value tabular-nums">{{ InvoiceNakladnayaFormatter::formatMoney((float) ($dTurn['debit'] ?? 0)) }}{{ $currencySuffix }}</div>
                    </div>
                    <div class="c1c-dash-turnbox">
                        <div class="label">Кредит — списания (расход по строкам отчёта)</div>
                        <div class="value tabular-nums">{{ InvoiceNakladnayaFormatter::formatMoney((float) ($dTurn['credit'] ?? 0)) }}{{ $currencySuffix }}</div>
                    </div>
                </div>
            </section>
        </div>

        <h2 class="c1c-detail-title">Детализация по счетам</h2>
        <div class="c1c-wrap">
            @if (empty($sections))
                <p class="rounded border border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-600">
                    Нет данных для отчёта.
                </p>
            @else
                <div class="c1c-wrap-inner">
                    <table class="c1c-table" cellspacing="0" cellpadding="0">
                    <thead>
                        <tr>
                            <th rowspan="2" style="min-width: 13rem">Счёт</th>
                            <th rowspan="2" style="min-width: 4.75rem">Показатели</th>
                            <th colspan="2">Сальдо на начало периода</th>
                            <th colspan="2">Обороты за период</th>
                            <th colspan="2">Сальдо на конец периода</th>
                        </tr>
                        <tr>
                            <th style="width: 7rem">Дебет</th>
                            <th style="width: 7rem">Кредит</th>
                            <th style="width: 7rem">Дебет</th>
                            <th style="width: 7rem">Кредит</th>
                            <th style="width: 7rem">Дебет</th>
                            <th style="width: 7rem">Кредит</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sections as $sec)
                            <tr class="c1c-section">
                                <td colspan="8">{{ $sec['title'] }}</td>
                            </tr>
                            @if (($sec['mode'] ?? '') === 'money')
                                @foreach (($sec['groups'] ?? []) as $g)
                                    <tr class="c1c-org">
                                        <td colspan="8">{{ $g['organization_name'] }}</td>
                                    </tr>
                                    @foreach ($g['accounts'] as $row)
                                        @php
                                            $code = $row['register_code'] ?? str_pad((string) (($row['id'] ?? 0) % 10000), 4, '0', STR_PAD_LEFT);
                                            $scf = $pickSaldoCells($row);
                                            $sn = $scf((float) $row['sn_debit'], (float) $row['sn_credit']);
                                            $tn = $turnCells((float) $row['to_debit'], (float) $row['to_credit']);
                                            $sk = $scf((float) $row['sk_debit'], (float) $row['sk_credit']);
                                            $canDrill = (($row['drill'] ?? true) !== false) && (int) $row['id'] !== 0;
                                        @endphp
                                        @include('admin.reports.partials.turnover-osv-row', [
                                            'code' => $code,
                                            'accountLabel' => $row['account_label'],
                                            'sn' => $sn,
                                            'tn' => $tn,
                                            'sk' => $sk,
                                            'canDrill' => $canDrill,
                                            'rowId' => (int) $row['id'],
                                        ])
                                    @endforeach
                                    @php
                                        $sub = $g['subtotal'];
                                        $sSn = $sumCells((float) $sub['sn_debit'], (float) $sub['sn_credit']);
                                        $sTn = $turnCells((float) $sub['to_debit'], (float) $sub['to_credit']);
                                        $sSk = $sumCells((float) $sub['sk_debit'], (float) $sub['sk_credit']);
                                    @endphp
                                    <tr class="c1c-subtotal">
                                        <td colspan="2" class="!text-right">Итого по организации</td>
                                        <td class="c1c-num">{{ $sSn['dt'] }}</td>
                                        <td class="c1c-num">{{ $sSn['ct'] }}</td>
                                        <td class="c1c-num">{{ $sTn['dt'] }}</td>
                                        <td class="c1c-num">{{ $sTn['ct'] }}</td>
                                        <td class="c1c-num">{{ $sSk['dt'] }}</td>
                                        <td class="c1c-num">{{ $sSk['ct'] }}</td>
                                    </tr>
                                @endforeach
                                @if (empty($sec['groups'] ?? []) || count($sec['groups'] ?? []) === 0)
                                    <tr class="c1c-main">
                                        <td class="py-6 text-[#475569]" colspan="8">
                                            Денежных счетов пока нет — добавьте кассу и банковские счета в «Настройки» → «Данные организации».
                                        </td>
                                    </tr>
                                @else
                                    @php
                                        $grand = $sec['grand'];
                                        $gSn = $sumCells((float) $grand['sn_debit'], (float) $grand['sn_credit']);
                                        $gTn = $turnCells((float) $grand['to_debit'], (float) $grand['to_credit']);
                                        $gSk = $sumCells((float) $grand['sk_debit'], (float) $grand['sk_credit']);
                                    @endphp
                                    <tr class="c1c-grand">
                                        <td colspan="2" class="!text-right !uppercase !tracking-wide">
                                            Итого по разделу «деньги»
                                        </td>
                                        <td class="c1c-num">{{ $gSn['dt'] }}</td>
                                        <td class="c1c-num">{{ $gSn['ct'] }}</td>
                                        <td class="c1c-num">{{ $gTn['dt'] }}</td>
                                        <td class="c1c-num">{{ $gTn['ct'] }}</td>
                                        <td class="c1c-num">{{ $gSk['dt'] }}</td>
                                        <td class="c1c-num">{{ $gSk['ct'] }}</td>
                                    </tr>
                                @endif
                            @else
                                @foreach (($sec['accounts'] ?? []) as $row)
                                    @php
                                        $code = $row['register_code'] ?? '—';
                                        $scf = $pickSaldoCells($row);
                                        $sn = $scf((float) $row['sn_debit'], (float) $row['sn_credit']);
                                        $tn = $turnCells((float) $row['to_debit'], (float) $row['to_credit']);
                                        $sk = $scf((float) $row['sk_debit'], (float) $row['sk_credit']);
                                        $canDrill = (($row['drill'] ?? true) !== false) && (int) $row['id'] !== 0;
                                    @endphp
                                    @include('admin.reports.partials.turnover-osv-row', [
                                        'code' => $code,
                                        'accountLabel' => $row['account_label'],
                                        'sn' => $sn,
                                        'tn' => $tn,
                                        'sk' => $sk,
                                        'canDrill' => $canDrill,
                                        'rowId' => (int) $row['id'],
                                    ])
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>

        <div
            x-cloak
            x-show="drillOpen"
            class="c1c-drill-overlay c1c-no-print fixed inset-0 z-[90] flex items-center justify-center bg-slate-900/45 p-4"
            @click.self="drillClose()"
        >
            <div
                class="flex max-h-[88vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-slate-300 bg-white shadow-2xl ring-1 ring-slate-900/5"
                @click.stop
                role="dialog"
                aria-modal="true"
                aria-labelledby="c1c-drill-title"
            >
                <div class="flex shrink-0 items-start justify-between gap-3 border-b border-[#8eaad8] bg-[#ddebf7] px-4 py-3">
                    <div id="c1c-drill-title" class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-700" x-text="drillData?.kind_label || 'Расшифровка'"></p>
                        <p class="mt-1 text-sm font-bold text-slate-900 tabular-nums">
                            <span class="mr-1" x-text="drillData?.account_code || ''"></span>
                            <span class="font-normal text-slate-700" x-text="drillData?.account_label || ''"></span>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded border border-slate-400 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50"
                        @click="drillClose()"
                    >
                        Закрыть
                    </button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto p-4">
                    <p x-show="drillLoading" class="text-sm text-slate-600">Загрузка…</p>
                    <p x-show="!drillLoading && drillErr" class="text-sm text-red-700" x-text="drillErr"></p>
                    <div x-show="!drillLoading && drillData && !drillErr">
                        <p x-show="drillData.is_synthetic" class="mb-3 rounded border border-amber-200 bg-amber-50/90 px-3 py-2 text-[11px] leading-snug text-amber-950">
                            Управленческая расшифровка: ниже показано, из каких данных сложилась ячейка (документы и формулы в примечании).
                        </p>
                        <div class="overflow-x-auto rounded border border-slate-200">
                            <table class="w-full border-collapse text-left text-xs">
                                <thead>
                                    <tr class="bg-slate-50 text-[10px] uppercase tracking-wide text-slate-600">
                                        <th class="border border-slate-200 px-2 py-1.5 font-semibold">Дата</th>
                                        <th class="border border-slate-200 px-2 py-1.5 font-semibold">Операция</th>
                                        <th class="border border-slate-200 px-2 py-1.5 font-semibold">Примечание</th>
                                        <th class="border border-slate-200 px-2 py-1.5 text-right font-semibold">Сумма</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(line, idx) in drillData.lines || []" :key="idx">
                                        <tr class="text-slate-800">
                                            <td class="whitespace-nowrap border border-slate-200 px-2 py-1.5 tabular-nums text-slate-700" x-text="line.date || '—'"></td>
                                            <td class="border border-slate-200 px-2 py-1.5" x-text="line.title"></td>
                                            <td class="border border-slate-200 px-2 py-1.5 text-slate-600" x-text="line.detail"></td>
                                            <td class="whitespace-nowrap border border-slate-200 px-2 py-1.5 text-right font-medium tabular-nums" x-text="line.amount_fmt"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <p x-show="drillData && drillData.lines && drillData.lines.length === 0" class="mt-3 text-sm text-slate-600">Нет строк для выбранной ячейки.</p>
                        <p class="mt-3 text-xs leading-relaxed text-slate-600" x-show="drillData?.footer_note" x-text="drillData.footer_note"></p>
                        <p class="mt-2 text-sm font-semibold text-slate-900" x-show="drillData?.total_fmt">
                            Итого: <span class="tabular-nums" x-text="drillData.total_fmt"></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
