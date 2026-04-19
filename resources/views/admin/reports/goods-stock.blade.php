@php
    $fmtQty = static fn ($v) => number_format((float) $v, 2, ',', ' ');
    $fmtMoney = static fn ($v) => $v === null ? '—' : \App\Support\InvoiceNakladnayaFormatter::formatMoney((float) $v);
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="px-3 py-6 sm:px-6 lg:px-8">
    @include('admin.partials.cp-brush')
    <div class="cp-root mx-auto w-full max-w-[min(100%,112rem)] space-y-6">
        @if ($warehouses->isEmpty())
            <div
                class="rounded-2xl border border-amber-200/90 bg-gradient-to-r from-amber-50 via-white to-orange-50/40 px-5 py-4 text-sm text-amber-950 shadow-sm ring-1 ring-amber-100/80"
            >
                <p class="font-semibold text-amber-950">Нет складов.</p>
                <p class="mt-2 text-amber-900/90">
                    <a
                        href="{{ route('admin.warehouses.create') }}"
                        class="font-semibold text-emerald-700 underline decoration-emerald-300 underline-offset-2 hover:text-emerald-800"
                    >Создать склад</a>
                </p>
            </div>
        @else
            @include('admin.partials.status-flash')

            {{-- Нельзя вставлять @json внутрь x-data="..." — кавычки в JSON рвут HTML-атрибут и текст показывается на странице. --}}
            <script>
                window.goodsStockPurchaseModal = function (rows) {
                    return {
                        rows: Array.isArray(rows) ? rows : [],
                        selected: {},
                        pageSelectAll: false,
                        prOpen: false,
                        modalLines: [],
                        init() {
                            this.syncPageSelectAll();
                        },
                        fmtQty(v) {
                            const n = Number(v);
                            if (Number.isNaN(n)) {
                                return '—';
                            }
                            return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
                        },
                        toggle(bid) {
                            const k = String(bid);
                            this.selected[k] = !this.selected[k];
                            this.syncPageSelectAll();
                        },
                        isOn(bid) {
                            return !!this.selected[String(bid)];
                        },
                        syncPageSelectAll() {
                            if (!this.rows.length) {
                                this.pageSelectAll = false;
                                return;
                            }
                            this.pageSelectAll = this.rows.every((r) => this.selected[String(r.balance_id)]);
                        },
                        togglePageAll(checked) {
                            this.rows.forEach((r) => {
                                this.selected[String(r.balance_id)] = checked;
                            });
                            this.pageSelectAll = !!checked;
                        },
                        selectedCount() {
                            return Object.keys(this.selected).filter((k) => this.selected[k]).length;
                        },
                        openPr() {
                            const chosen = this.rows.filter((r) => this.selected[String(r.balance_id)]);
                            if (!chosen.length) {
                                return;
                            }
                            this.modalLines = chosen.map((r) => ({ ...r, qtyStr: '' }));
                            this.prOpen = true;
                        },
                        closePr() {
                            this.prOpen = false;
                            this.modalLines = [];
                        },
                    };
                };
            </script>

            <div class="space-y-6" x-data="goodsStockPurchaseModal(@js($purchaseModalRows ?? []))">
            <div
                class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50"
            >
                <form
                    method="GET"
                    action="{{ route('admin.reports.goods-stock') }}"
                    class="rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90 px-4 py-4 sm:px-6 sm:py-5"
                >
                    <div class="grid gap-4 sm:grid-cols-2 sm:items-end">
                        <div>
                            <x-input-label for="r_gs_wh" value="Склад" />
                            <select
                                id="r_gs_wh"
                                name="warehouse_id"
                                class="mt-2 block w-full max-w-md rounded-xl border border-slate-200/90 bg-white py-2.5 pl-3 pr-10 text-sm text-slate-900 shadow-sm ring-1 ring-slate-900/5 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/25"
                                onchange="this.form.querySelector('input[name=page]').value='1'; this.form.submit();"
                            >
                                <option value="0" @selected($selectedWarehouseId === 0)>Все склады (сводно)</option>
                                @foreach ($warehouses as $w)
                                    <option value="{{ $w->id }}" @selected((int) $w->id === (int) $selectedWarehouseId)>{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="r_gs_q" value="Поиск" />
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <input
                                    id="r_gs_q"
                                    type="search"
                                    name="q"
                                    value="{{ $searchQuery }}"
                                    placeholder="Артикул, наименование, штрихкод…"
                                    autocomplete="off"
                                    class="min-w-[12rem] flex-1 rounded-xl border border-slate-200/90 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm ring-1 ring-slate-900/5 placeholder:text-slate-400 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/25"
                                />
                                <button
                                    type="submit"
                                    class="inline-flex shrink-0 items-center justify-center rounded-xl border border-emerald-200/90 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-900 shadow-sm ring-1 ring-emerald-900/5 hover:bg-emerald-100/90"
                                >
                                    Найти
                                </button>
                                @if ($searchQuery !== '')
                                    <a
                                        href="{{ route('admin.reports.goods-stock', array_filter(['warehouse_id' => $selectedWarehouseId, 'page' => 1, 'oem_min_low' => $oemMinLowOnly ? 1 : null])) }}"
                                        class="text-sm font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-slate-900"
                                    >Сбросить</a>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 border-t border-slate-200/80 pt-4">
                        <label class="inline-flex cursor-pointer items-start gap-2.5 text-sm text-slate-800">
                            <input
                                type="checkbox"
                                name="oem_min_low"
                                value="1"
                                class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/30"
                                @checked($oemMinLowOnly)
                                onchange="this.form.querySelector('input[name=page]').value='1'; this.form.submit();"
                            />
                            <span>
                                Только остатки по ОЭМ ниже минимума
                                <span class="block text-[11px] font-normal text-slate-500">
                                    Сумма количества по всем строкам с тем же ОЭМ сравнивается с мин. остатком; без ОЭМ — строка сама с собой.
                                </span>
                            </span>
                        </label>
                    </div>
                    <input type="hidden" name="page" value="1" />
                </form>
            </div>

            <div
                class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50"
            >
                <div class="overflow-hidden rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90">
                    <div class="ob-1c-scope overflow-hidden rounded-[1.5rem] bg-white/95">
                        <style>
                            .ob-1c-scope {
                                font-family: Tahoma, 'Segoe UI', Arial, sans-serif;
                                font-size: 12px;
                                color: #0f172a;
                            }
                            .ob-1c-scope .ob-1c-table {
                                width: 100%;
                                border-collapse: collapse;
                                table-layout: auto;
                                background: #fff;
                            }
                            .ob-1c-scope .ob-1c-table th,
                            .ob-1c-scope .ob-1c-table td {
                                border: 1px solid rgb(226 232 240);
                                padding: 0;
                                vertical-align: middle;
                            }
                            .ob-1c-scope .ob-1c-table th {
                                background: linear-gradient(180deg, #ecfdf5 0%, #e0f2fe 100%);
                                font-weight: 700;
                                text-align: left;
                                padding: 6px 8px;
                                white-space: nowrap;
                                color: #0f766e;
                                font-size: 11px;
                                letter-spacing: 0.02em;
                            }
                            .ob-1c-scope .ob-1c-table th.ob-num,
                            .ob-1c-scope .ob-1c-table td.ob-num {
                                text-align: center;
                                width: 2.25rem;
                                color: #475569;
                            }
                            .ob-1c-scope .ob-1c-table td.ob-cell {
                                padding: 4px 8px;
                                font-size: 12px;
                            }
                            .ob-1c-scope .ob-1c-table td.ob-numr {
                                text-align: right;
                            }
                        </style>
                        <div
                            class="flex flex-wrap items-start justify-between gap-3 border-b border-emerald-200/55 bg-gradient-to-r from-emerald-50/95 via-white to-sky-50/50 px-4 py-3 sm:px-5"
                        >
                            <div>
                                <p class="mb-0.5 text-[10px] font-semibold uppercase tracking-wider text-teal-700/90">Отчёт</p>
                                <h2 class="text-[15px] font-bold leading-tight text-slate-800">{{ $pageTitle }}</h2>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    class="shrink-0 rounded-lg border border-emerald-200/90 bg-emerald-600 px-3 py-1.5 text-[12px] font-semibold text-white shadow-sm ring-1 ring-emerald-700/30 hover:bg-emerald-700 disabled:pointer-events-none disabled:opacity-40"
                                    @click="openPr()"
                                    :disabled="selectedCount() === 0"
                                >
                                    Создать заявку
                                </button>
                                <a
                                    href="{{ route('admin.purchase-requests.index') }}"
                                    class="shrink-0 rounded-lg border border-emerald-200/90 bg-white/90 px-3 py-1.5 text-[12px] font-semibold text-emerald-800 shadow-sm ring-1 ring-slate-900/5 hover:bg-emerald-50/90"
                                >Список заявок</a>
                            </div>
                        </div>

                        @if ($rowsPaginator->lastPage() > 1)
                            <div class="border-b border-emerald-100/80 bg-slate-50/90 px-3 py-2 text-[11px] text-slate-700">
                                {{ $rowsPaginator->links() }}
                            </div>
                        @endif

                        <div class="overflow-x-auto border-t border-slate-200/90">
                            <table class="ob-1c-table">
                                <thead>
                                    <tr>
                                        <th class="ob-num text-center">
                                            <input
                                                type="checkbox"
                                                class="h-3.5 w-3.5 rounded border-slate-400 text-emerald-600 focus:ring-emerald-500/30"
                                                title="Выделить все на странице"
                                                :checked="pageSelectAll"
                                                @change="togglePageAll($event.target.checked)"
                                            />
                                        </th>
                                        <th class="ob-num">N</th>
                                        <th>Наименование</th>
                                        <th>Ед. изм.</th>
                                        <th>Количество</th>
                                        <th>ОЭМ</th>
                                        <th>Заводской №</th>
                                        <th>Мин. остаток</th>
                                        <th class="whitespace-nowrap">Сумма (себест.)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($rowsPaginator as $i => $r)
                                        @php
                                            $oemLow = ! empty($r['oem_group_low']);
                                            $oemTip = $oemLow && isset($r['oem_group_sum'], $r['oem_group_min'])
                                                ? 'По группе ОЭМ: сумма '.$fmtQty($r['oem_group_sum']).', мин. '.$fmtQty($r['oem_group_min'])
                                                : null;
                                        @endphp
                                        <tr
                                            class="{{ $oemLow ? 'bg-rose-50/90 ring-1 ring-inset ring-rose-100 hover:bg-rose-50' : 'hover:bg-emerald-50/40' }}"
                                            @if ($oemTip) title="{{ e($oemTip) }}" @endif
                                        >
                                            <td class="ob-cell text-center align-middle">
                                                <input
                                                    type="checkbox"
                                                    class="h-3.5 w-3.5 rounded border-slate-400 text-emerald-600 focus:ring-emerald-500/30"
                                                    :checked="isOn({{ (int) ($r['opening_stock_balance_id'] ?? 0) }})"
                                                    @change="toggle({{ (int) ($r['opening_stock_balance_id'] ?? 0) }})"
                                                />
                                            </td>
                                            <td class="ob-num ob-cell tabular-nums">
                                                {{ $rowsPaginator->firstItem() + $i }}
                                            </td>
                                            <td class="ob-cell min-w-[9rem] font-medium {{ $oemLow ? 'text-rose-950' : 'text-slate-900' }}">{{ $r['name'] }}</td>
                                            <td class="ob-cell whitespace-nowrap {{ $oemLow ? 'text-rose-900/95' : 'text-slate-700' }}">{{ $r['unit'] }}</td>
                                            <td class="ob-cell ob-numr tabular-nums font-semibold {{ $oemLow ? 'text-rose-800' : 'text-slate-900' }}">{{ $fmtQty($r['quantity']) }}</td>
                                            <td class="ob-cell {{ $oemLow ? 'text-rose-900' : 'text-slate-800' }}">{{ $r['oem'] !== '' ? $r['oem'] : '—' }}</td>
                                            <td class="ob-cell {{ $oemLow ? 'text-rose-900' : 'text-slate-800' }}">{{ $r['factory_number'] !== '' ? $r['factory_number'] : '—' }}</td>
                                            <td class="ob-cell ob-numr tabular-nums {{ $oemLow ? 'text-rose-900' : 'text-slate-800' }}">
                                                {{ $r['min_stock'] !== null ? $fmtQty($r['min_stock']) : '—' }}
                                            </td>
                                            <td class="ob-cell ob-numr tabular-nums {{ $oemLow ? 'text-rose-950' : 'text-slate-900' }}">{{ $fmtMoney($r['amount']) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="px-4 py-12 text-center text-sm text-slate-500">
                                                Нет товарных остатков по данным учёта.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($rowsPaginator->lastPage() > 1)
                            <div class="border-t border-slate-200/90 bg-slate-50/90 px-3 py-2 text-[11px] text-slate-700">
                                {{ $rowsPaginator->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div
                x-show="prOpen"
                x-cloak
                class="fixed inset-0 z-[100] flex items-end justify-center p-4 sm:items-center"
                role="dialog"
                aria-modal="true"
                @keydown.escape.window="closePr()"
            >
                <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-[1px]" @click="closePr()" aria-hidden="true"></div>
                <div
                    class="relative max-h-[min(90vh,40rem)] w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-xl ring-1 ring-slate-900/5 flex flex-col"
                    @click.stop
                >
                    <div class="shrink-0 border-b border-slate-200/90 px-5 py-4">
                        <p class="text-sm font-semibold text-slate-900">Новая заявка на закупку</p>
                        <p class="mt-1 text-xs text-slate-500">Укажите количество к закупке по каждой выделенной позиции.</p>
                    </div>
                    <form
                        method="POST"
                        action="{{ route('admin.purchase-requests.store') }}"
                        class="flex min-h-0 flex-1 flex-col"
                        data-no-nav-loading
                    >
                        @csrf
                        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                            <div class="overflow-x-auto rounded-xl border border-slate-200/90">
                                <table class="min-w-full border-collapse text-left text-xs">
                                    <thead class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th class="px-3 py-2">Наименование</th>
                                            <th class="px-3 py-2">Склад</th>
                                            <th class="px-3 py-2 text-right">Остаток</th>
                                            <th class="px-3 py-2 text-right">К закупке</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="(line, idx) in modalLines" :key="line.balance_id">
                                            <tr>
                                                <td class="px-3 py-2 align-top">
                                                    <span class="font-medium text-slate-900" x-text="line.name"></span>
                                                    <template x-if="line.oem">
                                                        <span class="block text-[10px] text-slate-500">ОЭМ: <span x-text="line.oem"></span></span>
                                                    </template>
                                                    <input
                                                        type="hidden"
                                                        :name="'items[' + idx + '][opening_stock_balance_id]'"
                                                        :value="line.balance_id"
                                                    />
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-2 align-top text-slate-700" x-text="line.warehouse"></td>
                                                <td class="whitespace-nowrap px-3 py-2 text-right align-top tabular-nums text-slate-800" x-text="fmtQty(line.stock_qty)"></td>
                                                <td class="whitespace-nowrap px-3 py-2 text-right align-top">
                                                    <input
                                                        type="number"
                                                        step="any"
                                                        min="0.0000001"
                                                        required
                                                        class="w-28 rounded-lg border border-slate-200 px-2 py-1 text-right tabular-nums text-slate-900 focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-500/30"
                                                        :name="'items[' + idx + '][quantity]'"
                                                        x-model="line.qtyStr"
                                                    />
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-4">
                                <x-input-label for="pr_note" value="Комментарий к заявке (необязательно)" />
                                <textarea
                                    id="pr_note"
                                    name="note"
                                    rows="2"
                                    class="mt-1 block w-full rounded-xl border border-slate-200/90 px-3 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-slate-900/5 placeholder:text-slate-400 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/25"
                                    placeholder="Срочность, поставщик…"
                                ></textarea>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-wrap justify-end gap-2 border-t border-slate-200/90 px-5 py-4">
                            <button
                                type="button"
                                class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50"
                                @click="closePr()"
                            >
                                Отмена
                            </button>
                            <button
                                type="submit"
                                class="rounded-xl border border-emerald-200/90 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
                            >
                                Создать заявку
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            </div>{{-- x-data purchase request --}}
        @endif
    </div>
</x-admin-layout>
