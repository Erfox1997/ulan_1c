@php
    use App\Support\InvoiceNakladnayaFormatter;

    $kpis = [
        ['key' => 'retail_payments', 'label' => 'Продажа физлицам', 'money' => true, 'tone' => 'retail'],
        ['key' => 'refunds', 'label' => 'Возврат от покупателя', 'money' => true, 'tone' => 'refund'],
        ['key' => 'income_client', 'label' => 'Оплата от покупателя (юр)', 'money' => true, 'tone' => 'in'],
        ['key' => 'income_other', 'label' => 'Приход: прочие и займы', 'money' => true, 'tone' => 'in'],
        ['key' => 'expense_supplier', 'label' => 'Расход: оплата поставщику', 'money' => true, 'tone' => 'out'],
        ['key' => 'expense_other', 'label' => 'Расход: прочие', 'money' => true, 'tone' => 'out'],
    ];

    $kpiShell = [
        'retail' => 'border-l-[3px] border-l-emerald-500 bg-gradient-to-br from-emerald-50/50 via-white to-white shadow-[0_1px_0_0_rgba(255,255,255,0.8)_inset]',
        'refund' => 'border-l-[3px] border-l-amber-500 bg-gradient-to-br from-amber-50/70 via-white to-white shadow-[0_1px_0_0_rgba(255,255,255,0.8)_inset]',
        'in' => 'border-l-[3px] border-l-sky-500 bg-gradient-to-br from-sky-50/45 via-white to-white shadow-[0_1px_0_0_rgba(255,255,255,0.8)_inset]',
        'out' => 'border-l-[3px] border-l-rose-500 bg-gradient-to-br from-rose-50/80 via-white to-white shadow-[0_1px_0_0_rgba(255,255,255,0.8)_inset]',
    ];
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="min-h-0 bg-gradient-to-b from-slate-100 via-slate-50 to-slate-100 px-3 py-6 sm:px-6 lg:px-10">
    <div class="mx-auto max-w-5xl space-y-6">
        <a
            href="{{ route('admin.reports.shift-report') }}"
            class="group inline-flex items-center gap-2 rounded-full border border-slate-200/90 bg-white/90 px-4 py-2 text-sm font-medium text-slate-800 shadow-sm shadow-slate-200/50 ring-1 ring-white/60 backdrop-blur-sm transition hover:border-emerald-200 hover:text-emerald-800 hover:shadow-md hover:shadow-emerald-500/10"
        >
            <span
                class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-xs text-slate-700 transition group-hover:bg-emerald-100 group-hover:text-emerald-800"
                aria-hidden="true"
            >←</span>
            К списку смен
        </a>

        <div
            class="overflow-hidden rounded-[1.25rem] border border-slate-200/80 bg-white shadow-[0_22px_60px_-12px_rgba(15,23,42,0.18)] ring-1 ring-slate-900/[0.04]"
        >
            <header class="border-b border-slate-200 bg-white">
                <div
                    class="h-1 bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700"
                    aria-hidden="true"
                ></div>
                <div class="px-5 py-6 sm:px-7">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 space-y-4">
                            <p class="inline-flex items-center rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-emerald-900 ring-1 ring-emerald-200/90">
                                Сменный отчёт
                            </p>
                            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-[1.85rem] sm:leading-tight">
                                Смена № {{ $shift->id }}
                            </h1>
                            <p class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-base text-slate-900">
                                <span class="whitespace-nowrap">
                                    <span class="text-xs font-bold uppercase tracking-wide text-slate-600">Кассир</span>
                                    <span class="ml-1.5 font-semibold">{{ $shift->user?->name ?? '—' }}</span>
                                </span>
                                <span class="text-slate-300" aria-hidden="true">·</span>
                                <span class="whitespace-nowrap">
                                    <span class="text-xs font-bold uppercase tracking-wide text-slate-600">Операционный день</span>
                                    <span class="ml-1.5 font-semibold tabular-nums">
                                        {{ $shift->business_date?->format('d.m.Y') ?? '—' }}
                                    </span>
                                </span>
                            </p>
                        </div>
                        <div class="shrink-0 sm:pt-1">
                            @if ($shift->closed_at)
                                <span
                                    class="inline-flex items-center gap-2 rounded-lg border border-emerald-700/25 bg-emerald-600 px-3.5 py-2 text-sm font-bold uppercase tracking-wide text-white shadow-sm"
                                >
                                    <span class="h-2 w-2 rounded-full bg-emerald-200" aria-hidden="true"></span>
                                    Закрыта
                                </span>
                            @else
                                <span
                                    class="inline-flex items-center gap-2 rounded-lg border border-amber-600/40 bg-amber-500 px-3.5 py-2 text-sm font-bold uppercase tracking-wide text-white shadow-sm"
                                >
                                    <span class="h-2 w-2 animate-pulse rounded-full bg-amber-100" aria-hidden="true"></span>
                                    Смена открыта
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </header>

            <section class="border-b border-slate-100 bg-gradient-to-b from-slate-50/90 to-slate-50/40 px-5 py-5 sm:px-7">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div
                        class="rounded-xl border border-slate-200/70 bg-white/80 px-4 py-3 shadow-sm shadow-slate-200/30"
                    >
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-700">Открыта</dt>
                        <dd class="mt-1 font-semibold tabular-nums text-slate-900">{{ $shift->opened_at?->format('d.m.Y H:i:s') }}</dd>
                    </div>
                    <div
                        class="rounded-xl border border-slate-200/70 bg-white/80 px-4 py-3 shadow-sm shadow-slate-200/30"
                    >
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-700">Закрыта</dt>
                        <dd class="mt-1 font-semibold tabular-nums text-slate-900">
                            {{ $shift->closed_at ? $shift->closed_at->format('d.m.Y H:i:s') : '—' }}
                        </dd>
                    </div>
                    @if ($shift->open_note)
                        <div class="sm:col-span-2">
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-700">Комментарий при открытии</dt>
                            <dd
                                class="mt-1.5 rounded-xl border border-slate-200/80 bg-white px-4 py-3 text-slate-900 shadow-sm"
                            >
                                {{ $shift->open_note }}
                            </dd>
                        </div>
                    @endif
                    @if ($shift->close_note)
                        <div class="sm:col-span-2">
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-700">Комментарий при закрытии</dt>
                            <dd
                                class="mt-1.5 rounded-xl border border-slate-200/80 bg-white px-4 py-3 text-slate-900 shadow-sm"
                            >
                                {{ $shift->close_note }}
                            </dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section
                class="border-b border-slate-100 px-5 py-6 sm:px-7"
                x-data="{
                    active: null,
                    detail: @js($kindDetailLists),
                    labels: @js(collect($kpis)->pluck('label', 'key')->all()),
                    isMovement() {
                        return ['income_client','income_other','expense_supplier','expense_other'].includes(this.active);
                    },
                    open(k) { this.active = k; },
                    close() { this.active = null; },
                }"
                @keydown.escape.window="close()"
            >
                <div class="flex items-center gap-3">
                    <span class="h-8 w-1 rounded-full bg-gradient-to-b from-emerald-500 to-teal-600 shadow-sm" aria-hidden="true"></span>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Сводка по видам операций</h2>
                        <p class="text-xs font-medium text-slate-700">Показатели за интервал смены — нажмите блок, чтобы открыть список операций</p>
                    </div>
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($kpis as $item)
                        @php
                            $val = $kindBreakdown[$item['key']];
                            $shell = $kpiShell[$item['tone']] ?? $kpiShell['retail'];
                        @endphp
                        <button
                            type="button"
                            @click="open('{{ $item['key'] }}')"
                            class="{{ $shell }} flex min-h-[5.5rem] w-full cursor-pointer flex-col justify-between rounded-2xl border border-slate-200/60 p-4 text-left transition duration-200 hover:-translate-y-0.5 hover:border-slate-300/80 hover:shadow-lg hover:shadow-slate-200/50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/60 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-50"
                        >
                            <p class="text-xs font-semibold leading-snug text-slate-800">{{ $item['label'] }}</p>
                            <p class="mt-3 text-lg font-bold tabular-nums tracking-tight text-slate-950 sm:text-xl">
                                @if ($item['money'])
                                    {{ InvoiceNakladnayaFormatter::formatMoney($val) }}
                                @else
                                    {{ $val }}
                                @endif
                            </p>
                        </button>
                    @endforeach
                </div>

                <template x-teleport="body">
                    <div
                        x-show="active !== null"
                        x-cloak
                        class="fixed inset-0 z-[20000] flex items-end justify-center sm:items-center sm:p-5"
                        role="dialog"
                        aria-modal="true"
                    >
                        <div
                            class="absolute inset-0 bg-slate-900/55 backdrop-blur-[1px]"
                            @click="close()"
                            aria-hidden="true"
                        ></div>
                        <div
                            class="relative flex max-h-[min(90vh,40rem)] w-full max-w-3xl flex-col overflow-hidden rounded-t-2xl border border-slate-200/90 bg-white shadow-2xl shadow-slate-400/25 sm:rounded-2xl"
                            @click.stop
                        >
                            <div class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-200 bg-gradient-to-r from-slate-700 to-slate-800 px-4 py-4 sm:px-5">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-white/80">Детализация</p>
                                    <h3 class="mt-0.5 truncate text-base font-bold text-white sm:text-lg" x-text="active ? labels[active] : ''"></h3>
                                </div>
                                <button
                                    type="button"
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/30 bg-white/10 text-xl font-light leading-none text-white transition hover:bg-white/20"
                                    @click="close()"
                                    aria-label="Закрыть"
                                >×</button>
                            </div>
                            <div class="min-h-0 flex-1 overflow-y-auto bg-slate-50/80 p-4 sm:p-5">
                                <p
                                    x-show="active && ((detail[active] || []).length === 0)"
                                    class="rounded-xl border border-dashed border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-600"
                                >
                                    За эту смену нет операций этого вида.
                                </p>

                                <div x-show="active === 'retail_payments' && (detail.retail_payments || []).length > 0" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                                    <div class="overflow-x-auto">
                                        <table class="shift-report-table w-full min-w-[28rem] border-collapse text-left text-sm">
                                            <thead>
                                                <tr>
                                                    <th class="whitespace-nowrap px-3 py-2.5 text-left">Время</th>
                                                    <th class="whitespace-nowrap px-3 py-2.5 text-left">Чек №</th>
                                                    <th class="whitespace-nowrap px-3 py-2.5 text-left">Дата док. чека</th>
                                                    <th class="whitespace-nowrap px-3 py-2.5 text-left">Счёт</th>
                                                    <th class="whitespace-nowrap px-3 py-2.5 text-right">Сумма</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="(row, idx) in (detail.retail_payments || [])" :key="'rp'+idx">
                                                    <tr :class="idx % 2 === 1 ? 'bg-slate-50/90' : 'bg-white'">
                                                        <td class="border border-slate-200 px-3 py-2 tabular-nums text-slate-800" x-text="row.at"></td>
                                                        <td class="border border-slate-200 px-3 py-2 tabular-nums font-medium text-slate-900" x-text="row.sale_id"></td>
                                                        <td class="border border-slate-200 px-3 py-2 tabular-nums text-slate-800" x-text="row.sale_doc"></td>
                                                        <td class="border border-slate-200 px-3 py-2 text-slate-800" x-text="row.account"></td>
                                                        <td class="border border-slate-200 px-3 py-2 text-right tabular-nums font-semibold text-slate-900" x-text="row.amount_fmt"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div x-show="active === 'refunds' && (detail.refunds || []).length > 0" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                                    <div class="overflow-x-auto">
                                        <table class="shift-report-table w-full min-w-[28rem] border-collapse text-left text-sm">
                                            <thead>
                                                <tr>
                                                    <th class="whitespace-nowrap px-3 py-2.5 text-left">Время</th>
                                                    <th class="whitespace-nowrap px-3 py-2.5 text-left">Чек №</th>
                                                    <th class="whitespace-nowrap px-3 py-2.5 text-left">Дата возврата</th>
                                                    <th class="whitespace-nowrap px-3 py-2.5 text-left">Счёт</th>
                                                    <th class="whitespace-nowrap px-3 py-2.5 text-right">Сумма</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="(row, idx) in (detail.refunds || [])" :key="'rf'+idx">
                                                    <tr :class="idx % 2 === 1 ? 'bg-slate-50/90' : 'bg-white'">
                                                        <td class="border border-slate-200 px-3 py-2 tabular-nums text-slate-800" x-text="row.at"></td>
                                                        <td class="border border-slate-200 px-3 py-2 tabular-nums font-medium text-slate-900" x-text="row.sale_id"></td>
                                                        <td class="border border-slate-200 px-3 py-2 tabular-nums text-slate-800" x-text="row.return_doc"></td>
                                                        <td class="border border-slate-200 px-3 py-2 text-slate-800" x-text="row.account"></td>
                                                        <td class="border border-slate-200 px-3 py-2 text-right tabular-nums font-semibold text-slate-900" x-text="row.amount_fmt"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div
                                    x-show="isMovement() && active && (detail[active] || []).length > 0"
                                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                                >
                                    <div class="overflow-x-auto">
                                        <table class="shift-report-table w-full min-w-[32rem] border-collapse text-left text-sm">
                                            <thead>
                                                <tr>
                                                    <th class="whitespace-nowrap px-3 py-2.5 text-left">Время записи</th>
                                                    <th class="whitespace-nowrap px-3 py-2.5 text-left">Дата операции</th>
                                                    <th class="whitespace-nowrap px-3 py-2.5 text-left">Счёт</th>
                                                    <th class="whitespace-nowrap px-3 py-2.5 text-right">Сумма</th>
                                                    <th class="whitespace-nowrap px-3 py-2.5 text-left">Пояснение</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="(row, idx) in (active ? (detail[active] || []) : [])" :key="'mv'+idx">
                                                    <tr :class="idx % 2 === 1 ? 'bg-slate-50/90' : 'bg-white'">
                                                        <td class="border border-slate-200 px-3 py-2 tabular-nums text-slate-800" x-text="row.at"></td>
                                                        <td class="border border-slate-200 px-3 py-2 tabular-nums text-slate-800" x-text="row.op_date"></td>
                                                        <td class="border border-slate-200 px-3 py-2 text-slate-800" x-text="row.account"></td>
                                                        <td class="border border-slate-200 px-3 py-2 text-right tabular-nums font-semibold text-slate-900" x-text="row.amount_fmt"></td>
                                                        <td class="border border-slate-200 px-3 py-2 text-[13px] leading-snug text-slate-700" x-text="row.note || '—'"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </section>

            <section class="px-5 py-6 sm:px-7">
                <div class="flex items-center gap-3">
                    <span class="h-8 w-1 rounded-full bg-gradient-to-b from-slate-600 to-slate-800 shadow-sm" aria-hidden="true"></span>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">По счетам</h2>
                        <p class="text-xs font-medium text-slate-700">Начало, движение, ожидаемый остаток</p>
                    </div>
                </div>
                <div
                    class="mt-5 overflow-hidden rounded-xl border border-slate-200/90 shadow-[inset_0_1px_0_0_rgba(255,255,255,0.06)] ring-1 ring-slate-900/[0.03]"
                >
                    <div class="overflow-x-auto">
                        <table class="shift-report-table w-full border-collapse text-left text-sm">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3.5 text-left whitespace-nowrap">Счёт</th>
                                    <th class="px-4 py-3.5 text-right whitespace-nowrap">На начало</th>
                                    <th class="px-4 py-3.5 text-right whitespace-nowrap">Движение</th>
                                    <th class="px-4 py-3.5 text-right whitespace-nowrap">Ожидается</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                @foreach ($closingTable['rows'] as $loopIndex => $row)
                                    <tr
                                        class="{{ $loopIndex % 2 === 1 ? 'bg-slate-100/90' : 'bg-white' }} transition-colors hover:bg-emerald-50/50"
                                    >
                                        <td class="border border-slate-200 px-4 py-2.5 font-medium text-slate-900">{{ $row['label'] }}</td>
                                        <td class="border border-slate-200 px-4 py-2.5 text-right tabular-nums text-slate-900">
                                            @if ($row['opening'] !== null)
                                                {{ InvoiceNakladnayaFormatter::formatMoney($row['opening']) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="border border-slate-200 px-4 py-2.5 text-right tabular-nums text-slate-900">
                                            {{ InvoiceNakladnayaFormatter::formatMoney($row['movement']) }}
                                        </td>
                                        <td class="border border-slate-200 px-4 py-2.5 text-right font-semibold tabular-nums text-slate-900">
                                            @if ($row['expected'] !== null)
                                                {{ InvoiceNakladnayaFormatter::formatMoney($row['expected']) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-emerald-100 text-sm font-bold text-slate-950">
                                <tr>
                                    <td class="border border-slate-200 px-4 py-3.5">Итого</td>
                                    <td class="border border-slate-200 px-4 py-3.5 text-right tabular-nums">
                                        {{ InvoiceNakladnayaFormatter::formatMoney($closingTable['totals']['opening']) }}
                                    </td>
                                    <td class="border border-slate-200 px-4 py-3.5 text-right tabular-nums">
                                        {{ InvoiceNakladnayaFormatter::formatMoney($closingTable['totals']['movement']) }}
                                    </td>
                                    <td class="border border-slate-200 px-4 py-3.5 text-right tabular-nums">
                                        {{ InvoiceNakladnayaFormatter::formatMoney($closingTable['totals']['expected']) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </section>

            @if ($shift->closed_at !== null && $closingFactRows !== [])
                <section class="border-t border-slate-100 px-5 py-6 sm:px-7">
                    <div class="flex items-center gap-3">
                        <span class="h-8 w-1 rounded-full bg-gradient-to-b from-teal-600 to-cyan-600 shadow-sm" aria-hidden="true"></span>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Сдано при закрытии</h2>
                            <p class="text-xs font-medium text-slate-700">Фактически переданная сумма по счетам</p>
                        </div>
                    </div>
                    <div
                        class="mt-5 overflow-hidden rounded-xl border border-slate-200/90 shadow-[inset_0_1px_0_0_rgba(255,255,255,0.06)] ring-1 ring-slate-900/[0.03]"
                    >
                        <div class="overflow-x-auto">
                            <table class="shift-report-table w-full border-collapse text-left text-sm">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3.5 text-left">Счёт</th>
                                        <th class="px-4 py-3.5 text-right">Сумма</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white">
                                    @foreach ($closingFactRows as $loopIndex => $row)
                                        <tr
                                            class="{{ $loopIndex % 2 === 1 ? 'bg-slate-100/90' : 'bg-white' }} hover:bg-emerald-50/45"
                                        >
                                            <td class="border border-slate-200 px-4 py-2.5 text-slate-900">{{ $row['label'] }}</td>
                                            <td class="border border-slate-200 px-4 py-2.5 text-right text-base font-semibold tabular-nums text-slate-900">
                                                {{ InvoiceNakladnayaFormatter::formatMoney($row['amount']) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-admin-layout>
