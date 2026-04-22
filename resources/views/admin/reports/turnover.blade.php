@php
    use App\Support\InvoiceNakladnayaFormatter;

    $fmt = static function (float $v): string {
        if (abs($v) < 0.005) {
            return '—';
        }

        return InvoiceNakladnayaFormatter::formatMoney($v);
    };

    $kindBadge = static function (string $kind): string {
        return match ($kind) {
            'cash' => 'Касса',
            'bank' => 'Счёт',
            default => $kind,
        };
    };

    $groups = $osv['groups'] ?? [];
    $grand = $osv['grand'] ?? [
        'sn_debit' => 0.0, 'sn_credit' => 0.0, 'to_debit' => 0.0, 'to_credit' => 0.0, 'sk_debit' => 0.0, 'sk_credit' => 0.0,
    ];
    $currencyCodes = $osv['currency_codes'] ?? [];
    $multiCurrency = count($currencyCodes) > 1;
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="osv-page bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <style>
        .osv-page {
            --osv-border: #cbd5e1;
            --osv-head: #0f172a;
        }
        .osv-doc {
            max-width: 100%;
            font-variant-numeric: tabular-nums;
        }
        .osv-doc-header h1 {
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--osv-head);
        }
        .osv-table-wrap {
            border: 1px solid var(--osv-border);
            border-radius: 0.5rem;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
        }
        .osv-table {
            width: 100%;
            min-width: 64rem;
            font-size: 0.8rem;
            line-height: 1.25;
        }
        .osv-table thead th {
            background: #f1f5f9;
            border: 1px solid var(--osv-border);
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #475569;
            padding: 0.4rem 0.35rem;
        }
        .osv-table tbody td {
            border: 1px solid #e2e8f0;
            padding: 0.4rem 0.35rem;
        }
        .osv-table tbody tr:hover td {
            background: #f8fafc;
        }
        .osv-num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
        .osv-num--dt { color: #0f172a; }
        .osv-num--ct { color: #374151; }
        .osv-org {
            background: #e2e8f0 !important;
            font-weight: 700;
            color: #0f172a;
        }
        .osv-subtotal {
            background: #f1f5f9 !important;
            font-weight: 600;
            color: #0f172a;
        }
        .osv-grand {
            background: #ecfdf5 !important;
            font-weight: 800;
            color: #064e3b;
            border-top: 2px solid #6ee7b7;
        }
        .osv-kind {
            display: inline-block;
            min-width: 2.5rem;
            text-align: center;
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            padding: 0.1rem 0.3rem;
            border-radius: 0.2rem;
        }
        .osv-kind--cash { background: #ecfeff; color: #0e7490; border: 1px solid #a5f3fc; }
        .osv-kind--bank { background: #f5f3ff; color: #4c1d95; border: 1px solid #c4b5fd; }
        @media print {
            .osv-no-print { display: none !important; }
            .osv-page { background: #fff; padding: 0; }
            .osv-table { font-size: 7.5pt; }
            .osv-table-wrap { border: 1px solid #000; box-shadow: none; }
        }
    </style>

    <div class="osv-doc mx-auto max-w-7xl space-y-3">
        <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]">
            <div
                class="osv-doc-header border-b border-slate-200 px-4 py-3 sm:px-5"
                style="background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);"
            >
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Отчёт</p>
                <h1>Оборотно-сальдовая ведомость по денежным средствам</h1>
                <div class="mt-2 space-y-0.5 text-xs text-slate-600">
                    <p>
                        <span class="text-slate-500">Период:</span>
                        <span class="font-medium text-slate-900">{{ $periodLabel }}</span>
                    </p>
                    @if (! empty($branchName))
                        <p>
                            <span class="text-slate-500">Филиал:</span>
                            <span class="font-medium text-slate-900">{{ $branchName }}</span>
                        </p>
                    @endif
                </div>
            </div>

            <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
                <div class="osv-no-print flex flex-wrap items-end justify-between gap-3">
                    @include('admin.reports.partials.period-filter', [
                        'action' => route('admin.reports.turnover'),
                        'filterFrom' => $filterFrom,
                        'filterTo' => $filterTo,
                    ])
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                        onclick="window.print()"
                    >
                        Печать
                    </button>
                </div>
            </div>

            <div class="px-3 py-3 sm:px-4 sm:py-4">
                <p class="mb-3 text-[11px] leading-relaxed text-slate-600">
                    Сальдо: положительный остаток — колонка <strong>Дт</strong>, отрицательный (перерасход) — <strong>Кт</strong>.
                    Обороты: <strong>Дт</strong> — поступления на счёт (включая розницу, приходы и входящий полуборот переводов);
                    <strong>Кт</strong> — списания, возвраты, исходящие переводы. Итоги по строкам в одной валюте; при нескольких
                    валютах итоговая строка — суммарно по цифрам (для сверки в одной валюте пользуйтесь сальдо по строкам).
                </p>
                @if ($multiCurrency)
                    <p class="mb-3 rounded border border-amber-200/80 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                        В ведомости встречаются разные валюты: {{ implode(', ', $currencyCodes) }}. Сводная итоговая строка не заменяет
                        валютный баланс — соотносите остатки по каждой валюте отдельно.
                    </p>
                @endif

                @if (count($groups) === 0)
                    <p class="rounded border border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600">
                        Нет денежных счетов. Укажите банк и кассу в «Настройки» → «Данные организации».
                    </p>
                @else
                    <div class="osv-table-wrap overflow-x-auto">
                        <table class="osv-table" cellspacing="0" cellpadding="0">
                            <thead>
                                <tr>
                                    <th scope="col" rowspan="2" class="!text-center">№</th>
                                    <th scope="col" rowspan="2" class="!text-left" style="min-width: 14rem">Счёт (касса / банк)</th>
                                    <th scope="col" rowspan="2" class="!text-center">Вид</th>
                                    <th scope="col" rowspan="2" class="!text-center">Вал.</th>
                                    <th scope="col" colspan="2" class="!text-center">Сальдо на начало</th>
                                    <th scope="col" colspan="2" class="!text-center">Обороты за период</th>
                                    <th scope="col" colspan="2" class="!text-center">Сальдо на конец</th>
                                </tr>
                                <tr>
                                    <th scope="col" class="!text-right">Дт</th>
                                    <th scope="col" class="!text-right">Кт</th>
                                    <th scope="col" class="!text-right">Дт</th>
                                    <th scope="col" class="!text-right">Кт</th>
                                    <th scope="col" class="!text-right">Дт</th>
                                    <th scope="col" class="!text-right">Кт</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $n = 0; @endphp
                                @foreach ($groups as $g)
                                    <tr class="osv-org">
                                        <td colspan="10" class="osv-org !py-1.5 !pl-2 text-[11px] sm:!pl-3">
                                            Организация: <span class="ml-0.5">{{ $g['organization_name'] }}</span>
                                        </td>
                                    </tr>
                                    @foreach ($g['accounts'] as $row)
                                        @php $n++; @endphp
                                        <tr>
                                            <td class="osv-num !text-slate-500">{{ $n }}</td>
                                            <td class="!text-slate-900 max-w-xs">{{ $row['account_label'] }}</td>
                                            <td class="!text-center">
                                                <span
                                                    class="osv-kind {{ $row['kind'] === 'cash' ? 'osv-kind--cash' : 'osv-kind--bank' }}"
                                                >{{ $kindBadge($row['kind']) }}</span>
                                            </td>
                                            <td class="!text-center text-slate-600">{{ $row['currency'] }}</td>
                                            <td class="osv-num osv-num--dt">{{ $fmt((float) $row['sn_debit']) }}</td>
                                            <td class="osv-num osv-num--ct">{{ $fmt((float) $row['sn_credit']) }}</td>
                                            <td class="osv-num text-emerald-900">{{ $fmt((float) $row['to_debit']) }}</td>
                                            <td class="osv-num text-rose-900">{{ $fmt((float) $row['to_credit']) }}</td>
                                            <td class="osv-num osv-num--dt font-medium">{{ $fmt((float) $row['sk_debit']) }}</td>
                                            <td class="osv-num osv-num--ct font-medium">{{ $fmt((float) $row['sk_credit']) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="osv-subtotal text-[11px] sm:text-xs">
                                        <td colspan="4" class="!pr-2 !text-right !text-slate-600 sm:!pr-3">Итого по организации</td>
                                        <td class="osv-num !font-semibold">{{ $fmt((float) $g['subtotal']['sn_debit']) }}</td>
                                        <td class="osv-num !font-semibold">{{ $fmt((float) $g['subtotal']['sn_credit']) }}</td>
                                        <td class="osv-num !font-semibold">{{ $fmt((float) $g['subtotal']['to_debit']) }}</td>
                                        <td class="osv-num !font-semibold">{{ $fmt((float) $g['subtotal']['to_credit']) }}</td>
                                        <td class="osv-num !font-semibold">{{ $fmt((float) $g['subtotal']['sk_debit']) }}</td>
                                        <td class="osv-num !font-semibold">{{ $fmt((float) $g['subtotal']['sk_credit']) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="osv-grand text-xs">
                                    <td colspan="4" class="!pr-2 !text-right !uppercase !tracking-wide sm:!pr-3">Всего</td>
                                    <td class="osv-num">{{ $fmt((float) $grand['sn_debit']) }}</td>
                                    <td class="osv-num">{{ $fmt((float) $grand['sn_credit']) }}</td>
                                    <td class="osv-num">{{ $fmt((float) $grand['to_debit']) }}</td>
                                    <td class="osv-num">{{ $fmt((float) $grand['to_credit']) }}</td>
                                    <td class="osv-num">{{ $fmt((float) $grand['sk_debit']) }}</td>
                                    <td class="osv-num">{{ $fmt((float) $grand['sk_credit']) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-admin-layout>
