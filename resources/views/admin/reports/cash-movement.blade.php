@php
    use App\Support\InvoiceNakladnayaFormatter;

    $fmtCell = static function (float $v): string {
        if (abs($v) < 0.005) {
            return '—';
        }

        return InvoiceNakladnayaFormatter::formatMoney($v);
    };
@endphp
<x-admin-layout pageTitle="Отчёт: движение денег за период" main-class="px-3 py-6 sm:px-6 lg:px-8">
    @include('admin.partials.cp-brush')
    @include('admin.bank.partials.1c-form-document-styles')

    <style>
        /* Сброс «1С»-оформления документа на этой странице */
        .bank-movement-report-page.bank-1c-scope {
            font-family: ui-sans-serif, system-ui, 'Segoe UI', Roboto, sans-serif;
            color: #0f172a;
        }
        .bank-movement-report-page .bank-1c-doc {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 4px 24px -8px rgba(30, 27, 75, 0.12);
            overflow: hidden;
            background: #fff;
        }
        .bank-movement-report-page .bank-1c-titlebar {
            border: none;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 55%, #f5f3ff 100%);
            padding: 16px 20px;
        }
        .bank-movement-report-page .bank-1c-titlebar h2 {
            font-size: 15px;
            font-weight: 600;
            letter-spacing: -0.02em;
            color: #1e1b4b;
        }
        .bank-movement-report-page .bank-1c-toolbar {
            background: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 16px;
            gap: 8px;
        }
        .bank-movement-report-page .bank-1c-tb-btn-primary {
            border-color: #6366f1;
            background: linear-gradient(180deg, #818cf8 0%, #4f46e5 100%);
            border-radius: 8px;
            padding: 6px 16px;
            min-height: 36px;
            font-size: 12px;
            font-weight: 600;
        }
        .bank-movement-report-page .bank-1c-tb-btn-primary:hover {
            background: linear-gradient(180deg, #a5b4fc 0%, #6366f1 100%);
        }

        /* Общая сетка отчёта: воздух и типографика */
        .bank-movement-report-page .bank-1c-report-body {
            padding: 14px 16px 18px;
        }
        @media (min-width: 640px) {
            .bank-movement-report-page .bank-1c-report-body {
                padding: 18px 22px 24px;
            }
        }

        .bank-movement-card {
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            background: #fff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .bank-movement-section-head {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 8px 14px;
            margin-bottom: 12px;
        }
        .bank-movement-section-title {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: -0.01em;
            color: #0f172a;
        }
        .bank-movement-section-title::before {
            content: '';
            flex-shrink: 0;
            width: 3px;
            height: 1.15em;
            border-radius: 2px;
            background: linear-gradient(180deg, #6366f1 0%, #a855f7 100%);
        }

        .bank-movement-hint {
            margin: 0 0 14px;
            padding: 10px 14px;
            font-size: 11.5px;
            line-height: 1.45;
            color: #475569;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            border-left: 3px solid #818cf8;
        }

        /* Таблица сводки */
        .bank-movement-matrix-wrap {
            border-radius: 14px;
            overflow: auto;
            border: 1px solid rgba(148, 163, 184, 0.28);
            background: #fff;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.9),
                0 8px 28px -12px rgba(15, 23, 42, 0.1);
            max-height: min(70vh, 720px);
        }

        .bank-movement-matrix {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
            font-feature-settings: 'tnum' 1;
        }

        .bank-movement-matrix thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            padding: 14px 14px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            line-height: 1.4;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: #64748b;
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid #e2e8f0;
            vertical-align: bottom;
        }

        .bank-movement-matrix thead th.bank-mm-date {
            min-width: 8rem;
            border-bottom: 3px solid #94a3b8;
        }
        .bank-movement-matrix thead th.bank-mm-col-income {
            text-align: right;
            border-bottom: 3px solid #14b8a6;
        }
        .bank-movement-matrix thead th.bank-mm-col-supplier {
            text-align: right;
            border-bottom: 3px solid #f43f5e;
        }
        .bank-movement-matrix thead th.bank-mm-col-other {
            text-align: right;
            border-bottom: 3px solid #a855f7;
        }
        .bank-movement-matrix thead th.bank-mm-col-transfer {
            text-align: right;
            border-bottom: 3px solid #3b82f6;
        }

        .bank-movement-matrix tbody td {
            padding: 11px 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            transition: background-color 0.12s ease;
        }
        .bank-movement-matrix tbody tr:last-child td {
            border-bottom: none;
        }
        .bank-movement-matrix tbody tr:nth-child(odd) td {
            background: #fff;
        }
        .bank-movement-matrix tbody tr:nth-child(even) td {
            background: #fafbfc;
        }
        .bank-movement-matrix tbody tr:hover td {
            background: #f5f3ff !important;
        }

        .bank-movement-matrix td.bank-mm-date {
            font-weight: 600;
            color: #1e293b;
            white-space: nowrap;
            font-size: 12.5px;
        }
        .bank-movement-matrix td.bank-mm-num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            font-weight: 500;
        }
        /* Акценты колонок: бирюза / роза / фиолет / синий */
        .bank-movement-matrix td.bank-mm-income {
            color: #0f766e;
            box-shadow: inset 3px 0 0 0 rgba(20, 184, 166, 0.55);
        }
        .bank-movement-matrix td.bank-mm-supplier {
            color: #be123c;
            box-shadow: inset 3px 0 0 0 rgba(244, 63, 94, 0.45);
        }
        .bank-movement-matrix td.bank-mm-other {
            color: #7e22ce;
            box-shadow: inset 3px 0 0 0 rgba(168, 85, 247, 0.45);
        }
        .bank-movement-matrix td.bank-mm-transfer {
            color: #1d4ed8;
            box-shadow: inset 3px 0 0 0 rgba(59, 130, 246, 0.5);
        }

        .bank-movement-matrix tfoot td {
            padding: 14px 14px 15px;
            font-weight: 600;
            font-size: 12.5px;
            letter-spacing: -0.01em;
            border-top: 1px solid #e2e8f0;
            background: linear-gradient(180deg, #fafafa 0%, #f4f4f5 100%);
            color: #18181b;
        }
        .bank-movement-matrix tfoot td:first-child {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748b;
        }
        .bank-movement-matrix tfoot td.bank-mm-num {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .bank-movement-matrix tfoot td.bank-mm-income {
            color: #0d9488;
            box-shadow: inset 3px 0 0 0 #14b8a6;
        }
        .bank-movement-matrix tfoot td.bank-mm-supplier {
            color: #e11d48;
            box-shadow: inset 3px 0 0 0 #f43f5e;
        }
        .bank-movement-matrix tfoot td.bank-mm-other {
            color: #9333ea;
            box-shadow: inset 3px 0 0 0 #a855f7;
        }
        .bank-movement-matrix tfoot td.bank-mm-transfer {
            color: #2563eb;
            box-shadow: inset 3px 0 0 0 #3b82f6;
        }

        .bank-movement-matrix-empty {
            padding: 36px 20px;
            text-align: center;
            color: #64748b;
            font-size: 13px;
            background: #fafafa;
        }

        /* Блок остатков */
        .bank-movement-report-page .bank-1c-table-panel {
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            background: #fff;
            box-shadow: none;
        }
        .bank-movement-report-page .bank-1c-data-table thead th {
            background: #fafafa;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #71717a;
            font-weight: 600;
            padding-top: 12px;
            padding-bottom: 10px;
        }
        .bank-movement-report-page .bank-1c-data-table tbody td {
            padding: 10px 12px;
        }
        .bank-movement-report-page .bank-1c-data-table td.bank-1c-num-pos {
            color: #0d9488;
        }
        .bank-movement-report-page .bank-1c-data-table td.bank-1c-num-neg {
            color: #e11d48;
        }

        /* Фильтры периода */
        .bank-movement-report-page .bank-1c-header--filters {
            background: #f8fafc;
            border-bottom-color: #e2e8f0;
        }
        .bank-movement-report-page .bank-1c-header input[type='date'] {
            border-radius: 8px;
            border: 1px solid #d4d4d8;
            padding: 6px 10px;
            font-size: 12px;
            color: #18181b;
            min-height: 34px;
            background: #fff;
        }
        .bank-movement-report-page .bank-1c-header input[type='date']:focus {
            outline: none;
            border-color: #818cf8;
            box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.25);
        }
    </style>

    <div class="mx-auto w-full max-w-7xl space-y-4">
        <div class="rounded-2xl bg-gradient-to-br from-indigo-100/70 via-white to-violet-50/90 p-px shadow-[0_20px_50px_-20px_rgba(79,70,229,0.35)] ring-1 ring-indigo-200/60">
            <div class="rounded-[15px] bg-white px-3 py-4 sm:px-5 sm:py-6">
                <div class="bank-1c-scope bank-movement-report-page w-full min-w-0">
                    <div class="bank-1c-doc w-full">
            <div class="bank-1c-titlebar">
                <h2>Движение денежных средств за период</h2>
            </div>

            <form method="GET" action="{{ route('admin.reports.cash-movement') }}" class="contents">
                <div class="bank-1c-toolbar">
                    <button type="submit" class="bank-1c-tb-btn bank-1c-tb-btn-primary">Сформировать</button>
                </div>
                <div class="bank-1c-header bank-1c-header--filters">
                    <div class="bank-1c-filter-field">
                        <span class="bank-1c-field-label">С даты *</span>
                        <input
                            id="m_from"
                            type="date"
                            name="from"
                            required
                            value="{{ $filterFrom }}"
                        />
                    </div>
                    <div class="bank-1c-filter-field">
                        <span class="bank-1c-field-label">По дату *</span>
                        <input
                            id="m_to"
                            type="date"
                            name="to"
                            required
                            value="{{ $filterTo }}"
                        />
                    </div>
                </div>
            </form>

            <div class="bank-1c-report-body space-y-6">
                @if ($summary->isNotEmpty())
                    <div class="bank-movement-card mb-2">
                        <div class="bank-movement-section-head px-4 pt-4 sm:px-5 sm:pt-5">
                            <p class="bank-movement-section-title">Остатки по счетам на границах периода</p>
                        </div>
                        <div class="bank-1c-table-panel overflow-x-auto px-2 pb-4 sm:px-3 sm:pb-5">
                            <table class="bank-1c-data-table">
                                <thead>
                                    <tr>
                                        <th>Счёт</th>
                                        <th>Валюта</th>
                                        <th class="bank-1c-num">На начало</th>
                                        <th class="bank-1c-num">Изменение</th>
                                        <th class="bank-1c-num">На конец</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($summary as $s)
                                        <tr>
                                            <td class="font-semibold">{{ $s['label'] }}</td>
                                            <td>{{ $s['currency'] }}</td>
                                            <td class="bank-1c-num">{{ InvoiceNakladnayaFormatter::formatMoney((float) $s['opening']) }}</td>
                                            <td class="bank-1c-num @if ($s['change'] >= 0) bank-1c-num-pos @else bank-1c-num-neg @endif">
                                                @if ($s['change'] > 0)+@endif{{ InvoiceNakladnayaFormatter::formatMoney((float) $s['change']) }}
                                            </td>
                                            <td class="bank-1c-num font-semibold">{{ InvoiceNakladnayaFormatter::formatMoney((float) $s['closing']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="bank-movement-card">
                    <div class="bank-movement-section-head px-4 pt-4 sm:px-5 sm:pt-5">
                        <p class="bank-movement-section-title">Сводка по дням (приход и расходы по видам)</p>
                    </div>
                    <p class="bank-movement-hint mx-4 sm:mx-5">
                        В приходе учтены оплаты от клиентов и розничные продажи (ФЛ). Переводы — сумма операций перевода за день (одна сумма на перевод).
                    </p>
                    @if ($dailyRows->isEmpty())
                        <div class="bank-movement-matrix-wrap mx-4 mb-5 sm:mx-5">
                            <div class="bank-movement-matrix-empty">Нет операций в выбранном периоде.</div>
                        </div>
                    @else
                        <div class="bank-movement-matrix-wrap mx-4 mb-5 sm:mx-5">
                            <table class="bank-movement-matrix">
                                <thead>
                                    <tr>
                                        <th class="bank-mm-date">Дата</th>
                                        <th class="bank-mm-col-income">Приход</th>
                                        <th class="bank-mm-col-supplier">Расход: оплата поставщику</th>
                                        <th class="bank-mm-col-other">Расходы: прочие</th>
                                        <th class="bank-mm-col-transfer">Переводы между счетами</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($dailyRows as $row)
                                        <tr>
                                            <td class="bank-mm-date">{{ $row['date'] }}</td>
                                            <td class="bank-mm-num bank-mm-income">{{ $fmtCell((float) $row['income']) }}</td>
                                            <td class="bank-mm-num bank-mm-supplier">{{ $fmtCell((float) $row['expense_supplier']) }}</td>
                                            <td class="bank-mm-num bank-mm-other">{{ $fmtCell((float) $row['expense_other']) }}</td>
                                            <td class="bank-mm-num bank-mm-transfer">{{ $fmtCell((float) $row['transfer']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td>Итого за период</td>
                                        <td class="bank-mm-num bank-mm-income">{{ InvoiceNakladnayaFormatter::formatMoney((float) $dailyTotals['income']) }}</td>
                                        <td class="bank-mm-num bank-mm-supplier">{{ InvoiceNakladnayaFormatter::formatMoney((float) $dailyTotals['expense_supplier']) }}</td>
                                        <td class="bank-mm-num bank-mm-other">{{ InvoiceNakladnayaFormatter::formatMoney((float) $dailyTotals['expense_other']) }}</td>
                                        <td class="bank-mm-num bank-mm-transfer">{{ InvoiceNakladnayaFormatter::formatMoney((float) $dailyTotals['transfer']) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
