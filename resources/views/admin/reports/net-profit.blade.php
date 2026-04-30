@php
    use App\Support\InvoiceNakladnayaFormatter;
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('netProfitBreakdownModal', () => ({
                meta: { detailUrl: '', from: '', to: '' },
                modalOpen: false,
                loading: false,
                error: '',
                title: '',
                periodLabel: '',
                columns: [],
                rows: [],
                totalFormatted: '',
                emptyMessage: 'Нет операций за выбранный период.',
                _abort: null,

                init() {
                    const el = this.$el;
                    this.meta = {
                        detailUrl: (el.dataset.npDetailUrl || '').trim(),
                        from: (el.dataset.npFrom || '').trim(),
                        to: (el.dataset.npTo || '').trim(),
                    };
                },

                closeModal() {
                    this.modalOpen = false;
                    this.loading = false;
                    this.error = '';
                    this.title = '';
                    this.periodLabel = '';
                    this.columns = [];
                    this.rows = [];
                    this.totalFormatted = '';
                    if (this._abort) {
                        try {
                            this._abort.abort();
                        } catch (e) {}
                        this._abort = null;
                    }
                    try {
                        document.body.style.overflow = '';
                    } catch (e) {}
                },

                async openKind(kind) {
                    const k = typeof kind === 'string' ? kind.trim() : '';
                    const baseUrl = (this.meta.detailUrl || '').trim();
                    if (k === '' || baseUrl === '') {
                        return;
                    }

                    this.modalOpen = true;
                    this.loading = true;
                    this.error = '';
                    this.title = '';
                    this.periodLabel = '';
                    this.columns = [];
                    this.rows = [];
                    this.totalFormatted = '';
                    this.emptyMessage = 'Нет операций за выбранный период.';
                    try {
                        document.body.style.overflow = 'hidden';
                    } catch (e) {}

                    if (this._abort) {
                        try {
                            this._abort.abort();
                        } catch (e) {}
                    }
                    const ac = new AbortController();
                    this._abort = ac;

                    try {
                        const u = new URL(baseUrl, window.location.origin);
                        u.searchParams.set('kind', k);
                        if (this.meta.from) {
                            u.searchParams.set('from', this.meta.from);
                        }
                        if (this.meta.to) {
                            u.searchParams.set('to', this.meta.to);
                        }

                        const tokenMeta =
                            typeof document !== 'undefined'
                                ? document.querySelector('meta[name="csrf-token"]')
                                : null;
                        const token = tokenMeta ? tokenMeta.getAttribute('content') : null;

                        const res = await fetch(u.toString(), {
                            signal: ac.signal,
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                            },
                        });

                        const data = res.ok ? await res.json().catch(() => ({})) : {};
                        if (!res.ok) {
                            this.error =
                                typeof data.message === 'string'
                                    ? data.message
                                    : 'Не удалось загрузить детализацию.';
                            return;
                        }

                        this.title = typeof data.title === 'string' ? data.title : '';
                        this.periodLabel = typeof data.period_label === 'string' ? data.period_label : '';
                        this.columns = Array.isArray(data.columns) ? data.columns : [];
                        this.rows = Array.isArray(data.rows) ? data.rows : [];
                        this.totalFormatted = typeof data.total_formatted === 'string' ? data.total_formatted : '';
                        if (typeof data.empty_message === 'string' && data.empty_message.trim() !== '') {
                            this.emptyMessage = data.empty_message;
                        }
                    } catch (e) {
                        if (e != null && e.name === 'AbortError') {
                            return;
                        }
                        this.error = 'Ошибка сети при загрузке.';
                    } finally {
                        if (this._abort === ac) {
                            this._abort = null;
                            this.loading = false;
                        }
                    }
                },
            }));
        });
    </script>
    <div
        id="np-profit-root"
        class="mx-auto max-w-3xl space-y-4"
        data-np-detail-url="{{ route('admin.reports.net-profit.detail') }}"
        data-np-from="{{ $filterFrom }}"
        data-np-to="{{ $filterTo }}"
        x-data="netProfitBreakdownModal()"
        @keydown.escape.window="if (modalOpen) closeModal()"
    >
        <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
            <div
                class="border-b border-emerald-900/10 px-4 py-3 text-white sm:px-5"
                style="background: linear-gradient(120deg, #047857 0%, #0d9488 50%, #0f766e 100%);"
            >
                <h1 class="text-sm font-bold tracking-tight">{{ $pageTitle }}</h1>
                <p class="mt-0.5 text-[11px] font-medium text-emerald-100/90">
                    Свод по статьям движения денег за период: поступления минус выплаты (переводы между счетами не учитываются).
                    Нажмите на сумму, чтобы открыть список операций.
                </p>
            </div>

            <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
                <div class="flex flex-wrap items-end gap-3">
                    @include('admin.reports.partials.period-filter', [
                        'action' => route('admin.reports.net-profit'),
                        'filterFrom' => $filterFrom,
                        'filterTo' => $filterTo,
                    ])
                    <a
                        href="{{ route('admin.reports.net-profit.pdf', request()->query()) }}"
                        class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                    >Скачать PDF</a>
                </div>
            </div>

            <div class="border-b border-slate-100 px-4 py-3 text-[11px] leading-relaxed text-slate-600 sm:px-5">
                <p>
                    «Прочие расходы» — без выплат зарплаты (они отражены отдельно, как в журнале при оформлении выплаты из модуля «Зарплата»).
                    «Авансы» — суммы по дате записи в разделе «Авансы»; если выплата аванса не дублируется проводкой в кассе, итог может потребовать ручной интерпретации.
                </p>
            </div>

            <div class="overflow-x-auto px-4 py-4 sm:px-5">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="py-2.5 pr-4">Статья</th>
                            <th class="py-2.5 text-right tabular-nums">Сумма</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr class="hover:bg-emerald-50/30">
                            <td class="py-2.5 pr-4 text-slate-800">Приход от покупателя</td>
                            <td class="py-2.5 text-right">
                                <button
                                    type="button"
                                    class="inline font-medium tabular-nums text-emerald-800 underline decoration-dotted decoration-emerald-600/40 underline-offset-2 hover:decoration-solid focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
                                    @click="openKind('income_client')"
                                >
                                    + {{ InvoiceNakladnayaFormatter::formatMoney($summary['income_client']) }}
                                </button>
                            </td>
                        </tr>
                        <tr class="hover:bg-emerald-50/30">
                            <td class="py-2.5 pr-4 text-slate-800">Приход прочие</td>
                            <td class="py-2.5 text-right">
                                <button
                                    type="button"
                                    class="inline font-medium tabular-nums text-emerald-800 underline decoration-dotted decoration-emerald-600/40 underline-offset-2 hover:decoration-solid focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
                                    @click="openKind('income_other')"
                                >
                                    + {{ InvoiceNakladnayaFormatter::formatMoney($summary['income_other']) }}
                                </button>
                            </td>
                        </tr>
                        <tr class="hover:bg-emerald-50/30">
                            <td class="py-2.5 pr-4 text-slate-800">Расход поставщику</td>
                            <td class="py-2.5 text-right">
                                <button
                                    type="button"
                                    class="inline font-medium tabular-nums text-rose-700 underline decoration-dotted decoration-rose-400/50 underline-offset-2 hover:decoration-solid focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-400/40"
                                    @click="openKind('expense_supplier')"
                                >
                                    − {{ InvoiceNakladnayaFormatter::formatMoney($summary['expense_supplier']) }}
                                </button>
                            </td>
                        </tr>
                        <tr class="hover:bg-emerald-50/30">
                            <td class="py-2.5 pr-4 text-slate-800">Прочие расходы <span class="text-[10px] font-normal text-slate-500">(без зарплаты)</span></td>
                            <td class="py-2.5 text-right">
                                <button
                                    type="button"
                                    class="inline font-medium tabular-nums text-rose-700 underline decoration-dotted decoration-rose-400/50 underline-offset-2 hover:decoration-solid focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-400/40"
                                    @click="openKind('expense_other')"
                                >
                                    − {{ InvoiceNakladnayaFormatter::formatMoney($summary['expense_other']) }}
                                </button>
                            </td>
                        </tr>
                        <tr class="hover:bg-emerald-50/30">
                            <td class="py-2.5 pr-4 text-slate-800">
                                Продажа физлицам <span class="text-[10px] font-normal text-slate-500">(оплаты по чекам)</span>
                            </td>
                            <td class="py-2.5 text-right">
                                <button
                                    type="button"
                                    class="inline font-medium tabular-nums text-emerald-800 underline decoration-dotted decoration-emerald-600/40 underline-offset-2 hover:decoration-solid focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
                                    @click="openKind('retail_payments')"
                                >
                                    + {{ InvoiceNakladnayaFormatter::formatMoney($summary['retail_payments']) }}
                                </button>
                            </td>
                        </tr>
                        <tr class="hover:bg-emerald-50/30">
                            <td class="py-2.5 pr-4 text-slate-800">
                                Возврат покупателю <span class="text-[10px] font-normal text-slate-500">(розница, выплата по документу возврата)</span>
                            </td>
                            <td class="py-2.5 text-right">
                                <button
                                    type="button"
                                    class="inline font-medium tabular-nums text-rose-700 underline decoration-dotted decoration-rose-400/50 underline-offset-2 hover:decoration-solid focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-400/40"
                                    @click="openKind('retail_refunds')"
                                >
                                    − {{ InvoiceNakladnayaFormatter::formatMoney($summary['retail_refunds']) }}
                                </button>
                            </td>
                        </tr>
                        <tr class="hover:bg-emerald-50/30">
                            <td class="py-2.5 pr-4 align-top text-slate-800">
                                Выплаты зарплаты и авансы <span class="text-[10px] font-normal text-slate-500">(зарплата из кассы + записи «Авансы»)</span>
                            </td>
                            <td class="py-2.5 text-right align-top">
                                @if ($summary['employee_advances'] > 0.00001 || $summary['salary_payouts'] > 0.00001)
                                    <div class="mb-0.5 text-[10px] font-normal tabular-nums text-slate-500">
                                        @if ($summary['salary_payouts'] > 0.00001)
                                            <button
                                                type="button"
                                                class="inline underline decoration-dotted decoration-slate-400 underline-offset-2 hover:text-slate-800 hover:decoration-solid"
                                                @click="openKind('salary_payouts')"
                                            >
                                                зарплата {{ InvoiceNakladnayaFormatter::formatMoney($summary['salary_payouts']) }}
                                            </button>
                                        @endif
                                        @if ($summary['salary_payouts'] > 0.00001 && $summary['employee_advances'] > 0.00001)
                                            <br />
                                        @endif
                                        @if ($summary['employee_advances'] > 0.00001)
                                            <button
                                                type="button"
                                                class="inline underline decoration-dotted decoration-slate-400 underline-offset-2 hover:text-slate-800 hover:decoration-solid"
                                                @click="openKind('employee_advances')"
                                            >
                                                авансы {{ InvoiceNakladnayaFormatter::formatMoney($summary['employee_advances']) }}
                                            </button>
                                        @endif
                                    </div>
                                @endif
                                <button
                                    type="button"
                                    class="inline font-medium tabular-nums text-rose-700 underline decoration-dotted decoration-rose-400/50 underline-offset-2 hover:decoration-solid focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-400/40"
                                    @click="openKind('payroll_advances')"
                                >
                                    − {{ InvoiceNakladnayaFormatter::formatMoney($summary['payroll_advances_total']) }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-emerald-200/80 bg-emerald-50/80">
                            <th class="py-3 pr-4 text-left text-xs font-bold uppercase tracking-wide text-emerald-900">Чистая прибыль</th>
                            <th class="py-3 text-right">
                                <button
                                    type="button"
                                    class="inline text-base font-bold tabular-nums text-emerald-950 underline decoration-dotted decoration-emerald-700/40 underline-offset-2 hover:decoration-solid focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600/40"
                                    @click="openKind('net_profit')"
                                >
                                    {{ InvoiceNakladnayaFormatter::formatMoney($summary['net_profit']) }}
                                </button>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <template x-teleport="body">
            <div
                x-show="modalOpen"
                x-cloak
                class="fixed inset-0 z-[20000] flex items-end justify-center sm:items-center sm:p-5"
                role="dialog"
                aria-modal="true"
                aria-labelledby="np-detail-modal-title"
            >
                <div class="absolute inset-0 bg-slate-900/55 backdrop-blur-[1px]" @click="closeModal()" aria-hidden="true"></div>
                <div
                    class="relative flex max-h-[min(90vh,52rem)] w-full max-w-lg flex-col overflow-hidden rounded-t-2xl border border-slate-200/90 bg-white shadow-2xl shadow-slate-300/40 sm:max-w-4xl sm:rounded-2xl"
                    @click.stop
                >
                    <div class="flex shrink-0 items-start gap-4 border-b border-emerald-700/20 bg-gradient-to-r from-emerald-600 to-teal-600 px-5 py-4">
                        <span class="mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/15 text-white ring-1 ring-white/25" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-50/95">Детализация</p>
                            <h2 id="np-detail-modal-title" class="mt-1 text-base font-bold leading-snug text-white sm:text-[17px]" x-text="title || '…'"></h2>
                            <p class="mt-2 text-[11px] leading-snug text-emerald-50/92" x-show="periodLabel" x-text="'Период: ' + periodLabel"></p>
                        </div>
                        <button
                            type="button"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/35 bg-white/10 text-xl font-light leading-none text-white transition hover:bg-white/20"
                            @click="closeModal()"
                            aria-label="Закрыть"
                        >×</button>
                    </div>

                    <div class="flex min-h-0 flex-1 flex-col overflow-hidden bg-white">
                        <div x-show="loading" class="flex items-center gap-2 border-b border-slate-100 px-5 py-4 text-sm text-slate-600">
                            <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-emerald-600 border-t-transparent"></span>
                            Загрузка…
                        </div>

                        <div x-show="!loading && error" class="border-b border-rose-100 bg-rose-50 px-5 py-4 text-sm text-rose-800" x-text="error"></div>

                        <div class="min-h-0 flex-1 overflow-auto px-3 py-3 sm:px-5 sm:py-4">
                            <template x-if="!loading && !error && rows.length === 0">
                                <p class="py-10 text-center text-sm text-slate-500" x-text="emptyMessage"></p>
                            </template>

                            <template x-if="!loading && !error && rows.length > 0">
                                <div class="overflow-x-auto rounded-lg border border-slate-200/90">
                                    <table class="min-w-full text-left text-xs sm:text-sm">
                                        <thead class="sticky top-0 z-[1] border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                            <tr>
                                                <template x-for="col in columns" :key="col.key">
                                                    <th class="whitespace-nowrap px-3 py-2.5 sm:px-4" x-text="col.label"></th>
                                                </template>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <template x-for="(row, ri) in rows" :key="'np-row-' + ri">
                                                <tr class="hover:bg-emerald-50/40">
                                                    <template x-for="col in columns" :key="col.key + '-' + ri">
                                                        <td
                                                            class="max-w-[14rem] whitespace-normal break-words px-3 py-2 tabular-nums text-slate-800 sm:max-w-none sm:px-4 sm:py-2.5"
                                                            x-text="row[col.key] != null && row[col.key] !== '' ? String(row[col.key]) : '—'"
                                                        ></td>
                                                    </template>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </template>
                        </div>

                        <div
                            x-show="!loading && !error && totalFormatted !== ''"
                            class="shrink-0 border-t border-emerald-100 bg-emerald-50/60 px-5 py-3 text-right text-sm font-semibold tabular-nums text-emerald-950"
                        >
                            <span class="text-slate-600">Итого:</span>
                            <span class="ml-2" x-text="totalFormatted"></span>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-admin-layout>
