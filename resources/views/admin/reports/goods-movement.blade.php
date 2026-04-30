@php
    $fmtQty = static fn ($v) => number_format((float) $v, 2, ',', ' ');
    $viewMode ??= 'categories';
    $gmBaseRoute = function (array $extra = []) use ($filterFrom, $filterTo, $selectedWarehouseId, $onlyWithMovement): string {
        $params = array_merge([
            'from' => $filterFrom,
            'to' => $filterTo,
            'warehouse_id' => $selectedWarehouseId,
            'only_with_movement' => $onlyWithMovement ? 1 : 0,
        ], $extra);

        return route('admin.reports.goods-movement', array_filter($params, static fn ($qv) => $qv !== null && $qv !== ''));
    };
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    @include('admin.partials.cp-brush')
    <style>
        .gm-page .cp-table thead th {
            background: linear-gradient(180deg, #ecfdf5 0%, #d1fae5 100%);
            color: #065f46;
            border-color: #a7f3d0;
            font-weight: 700;
        }
        .gm-page .cp-table th,
        .gm-page .cp-table td {
            border-color: #d1fae5;
        }
        .gm-page .cp-table tbody tr:hover {
            background: #ecfdf5;
        }
    </style>
    <div class="gm-page mx-auto max-w-[110rem] space-y-4">
        <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
            <div
                class="border-b border-emerald-900/10 px-4 py-3 text-white sm:px-5"
                style="background: linear-gradient(120deg, #047857 0%, #0d9488 50%, #0f766e 100%);"
            >
                <h1 class="text-sm font-bold tracking-tight">{{ $pageTitle }}</h1>
            </div>

            <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
                <form method="GET" action="{{ route('admin.reports.goods-movement') }}" class="space-y-4">
                    @if (($viewMode ?? '') === 'goods' && ! empty($selectedCategoryKey ?? null))
                        <input type="hidden" name="category" value="{{ $selectedCategoryKey }}" />
                    @endif
                    @if (($viewMode ?? '') === 'good')
                        <input type="hidden" name="category" value="{{ $detailCategoryKey }}" />
                        <input type="hidden" name="good" value="{{ $detailGoodId }}" />
                    @endif
                    <div class="flex flex-wrap items-end gap-4">
                        <div class="min-w-[12rem]">
                            <label class="mb-1 block text-xs font-semibold text-slate-700">Склад</label>
                            <select
                                name="warehouse_id"
                                class="w-full rounded-lg border border-slate-200 bg-slate-50/80 py-2 pl-2.5 pr-8 text-sm font-medium text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                            >
                                <option value="0" @selected($selectedWarehouseId === 0)>Все склады</option>
                                @foreach ($warehouses as $w)
                                    <option value="{{ $w->id }}" @selected((int) $w->id === (int) $selectedWarehouseId)>{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-700">С даты</label>
                            <input type="date" name="from" value="{{ $filterFrom }}" required class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-700">По дату</label>
                            <input type="date" name="to" value="{{ $filterTo }}" required class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" />
                        </div>
                        @if (in_array($viewMode, ['goods', 'good'], true))
                            <div class="min-w-[14rem] flex-1">
                                <label class="mb-1 block text-xs font-semibold text-slate-700">Поиск в этой категории</label>
                                <input
                                    type="search"
                                    name="q"
                                    value="{{ $searchQuery }}"
                                    placeholder="Артикул, название или категория"
                                    autocomplete="off"
                                    class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                />
                            </div>
                        @endif
                        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Сформировать</button>
                    </div>
                    <div class="flex flex-wrap items-start gap-6 border-t border-slate-100 pt-3">
                        <label class="inline-flex cursor-pointer items-start gap-2.5 text-sm text-slate-800">
                            <input type="hidden" name="only_with_movement" value="0" />
                            <input
                                type="checkbox"
                                name="only_with_movement"
                                value="1"
                                class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/30"
                                @checked($onlyWithMovement)
                                onchange="this.form.querySelector('input[name=page]').value='1'; this.form.submit();"
                            />
                            <span>
                                Только позиции с движением за период
                            </span>
                        </label>
                    </div>
                    <input type="hidden" name="page" value="1" />
                </form>
            </div>

            {{-- категории (папки) --}}
            @if (($viewMode ?? '') === 'categories')
                <div class="px-4 py-6 sm:px-5">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @forelse ($categories as $c)
                            <a
                                href="{{ $gmBaseRoute(['category' => $c['category_key']]) }}"
                                class="group flex min-h-[4.5rem] items-center gap-3 rounded-xl border border-emerald-100 bg-white px-4 py-3.5 shadow-sm transition hover:border-emerald-300 hover:shadow-md hover:shadow-emerald-100/60"
                            >
                                <span
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 transition group-hover:bg-emerald-200/90"
                                    aria-hidden="true"
                                >
                                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/>
                                    </svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="line-clamp-2 font-semibold leading-snug text-slate-900">{{ $c['label'] }}</span>
                                    <span class="mt-0.5 block text-[11px] font-medium tabular-nums text-slate-500">{{ $c['count'] }} позиций в отборе</span>
                                </span>
                                <svg class="h-5 w-5 shrink-0 text-emerald-400 transition group-hover:text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        @empty
                            <div class="col-span-full rounded-2xl border border-dashed border-emerald-200 bg-emerald-50/40 px-6 py-14 text-center text-[13px] text-slate-600">
                                Нет строк по условию. Измените период, склад или снимите галочку «только с движением».
                            </div>
                        @endforelse
                    </div>
                </div>
            @elseif (($viewMode ?? '') === 'goods')
                <div
                    x-data="goodsMovementPeriodModal(@js([
                        'from' => $filterFrom,
                        'to' => $filterTo,
                        'warehouseId' => (int) $selectedWarehouseId,
                        'warehouse' => $selectedWarehouseId === 0
                            ? 'Все склады'
                            : (string) (optional($warehouses->firstWhere('id', $selectedWarehouseId))->name ?? '—'),
                        'ledgerUrl' => route('admin.reports.goods-movement.ledger-data'),
                    ]))"
                    @keydown.escape.window="if (modalOpen) closeModal()"
                >
                <nav class="flex flex-wrap items-center gap-2 border-b border-emerald-100/80 bg-emerald-50/35 px-4 py-3 text-[13px] sm:px-5" aria-label="Навигация">
                    <a
                        href="{{ $gmBaseRoute() }}"
                        class="rounded-lg px-2.5 py-1 font-semibold text-emerald-800 transition hover:bg-white/80 hover:text-emerald-950"
                    >Все категории</a>
                    <span class="text-emerald-200" aria-hidden="true">/</span>
                    <span class="font-semibold text-slate-800">{{ $categoryTitle }}</span>
                </nav>

                @if ($rowsPaginator !== null && $rowsPaginator->lastPage() > 1)
                    <div class="border-b border-slate-100 bg-white px-4 py-2 text-[11px] text-slate-700 sm:px-5">
                        {{ $rowsPaginator->links() }}
                    </div>
                @endif

                <div class="cp-table-wrap bg-gradient-to-b from-emerald-50/25 to-white">
                    <table class="cp-table">
                        <thead>
                            <tr>
                                <th>Наименование</th>
                                <th class="whitespace-nowrap">Ед.&nbsp;изм.</th>
                                <th class="cp-num whitespace-nowrap">Остаток</th>
                                <th class="cp-num whitespace-nowrap">Продано за период</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rowsPaginator ?? [] as $r)
                                <tr
                                    class="cursor-pointer transition-colors hover:bg-emerald-50/70 focus-within:bg-emerald-50/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-emerald-500/50"
                                    role="button"
                                    tabindex="0"
                                    aria-label="Показать движение по позиции"
                                    @click.stop="openRow(@js($r))"
                                    @keydown.enter.prevent.stop="openRow(@js($r))"
                                    @keydown.space.prevent.stop="openRow(@js($r))"
                                >
                                    <td class="align-top font-semibold text-neutral-900">{{ $r['name'] }}</td>
                                    <td class="align-top whitespace-nowrap text-neutral-700">{{ $r['unit'] }}</td>
                                    <td class="cp-num align-top tabular-nums font-medium text-emerald-800">{{ $fmtQty($r['stock_qty'] ?? 0) }}</td>
                                    <td class="cp-num align-top tabular-nums text-slate-800">{{ $fmtQty($r['sold_total'] ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-14 text-center text-[13px] text-slate-600">
                                        Пусто по этой категории или поиску.
                                        <a href="{{ $gmBaseRoute(['category' => $selectedCategoryKey]) }}" class="cp-link ml-1 font-semibold text-sky-700 hover:text-sky-900">Сбросить поиск</a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($rowsPaginator !== null && $rowsPaginator->hasPages())
                    <div class="border-t border-slate-100 bg-slate-50/80 px-4 py-3 text-sm text-slate-700">
                        {{ $rowsPaginator->links() }}
                    </div>
                @endif

                <template x-teleport="body">
                    <div
                        x-show="modalOpen"
                        x-cloak
                        class="fixed inset-0 z-[20000] flex items-end justify-center sm:items-center sm:p-5"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="gm-movement-modal-title"
                    >
                        <div
                            class="absolute inset-0 bg-slate-900/55 backdrop-blur-[1px]"
                            @click="closeModal()"
                            aria-hidden="true"
                        ></div>
                        <div
                            class="relative flex max-h-[min(90vh,52rem)] w-full max-w-lg flex-col overflow-hidden rounded-t-2xl border border-slate-200/90 bg-white shadow-2xl shadow-slate-300/40 sm:max-w-2xl sm:rounded-2xl"
                            @click.stop
                        >
                            <div class="flex shrink-0 items-start gap-4 border-b border-emerald-700/20 bg-gradient-to-r from-emerald-600 to-teal-600 px-5 py-4">
                                <span class="mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/15 text-white ring-1 ring-white/25" aria-hidden="true">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                                    </svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-50/95">Движение за период (только просмотр)</p>
                                    <h2 id="gm-movement-modal-title" class="mt-1 text-base font-bold leading-snug text-white sm:text-[17px]" x-text="row ? row.name : ''"></h2>
                                    <p class="mt-2 text-[11px] leading-snug text-emerald-50/92" x-text="'Период: ' + (meta.from || '') + ' — ' + (meta.to || '') + ' · склад: ' + (meta.warehouse || '')"></p>
                                </div>
                                <button
                                    type="button"
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/35 bg-white/10 text-xl font-light leading-none text-white transition hover:bg-white/20"
                                    @click="closeModal()"
                                    aria-label="Закрыть"
                                >×</button>
                            </div>
                            <div class="flex-1 overflow-y-auto px-4 py-4 sm:px-6">
                                <div x-show="row">
                                    <div class="border-b border-slate-100 pb-4 mb-4 text-[13px] text-slate-600">
                                        <p class="flex flex-wrap gap-x-4 gap-y-1">
                                            <span>Артикул:
                                                <span class="font-mono font-semibold text-slate-900" x-text="row.article && row.article !== '' ? row.article : '—'"></span>
                                            </span>
                                            <span>Ед. изм.: <span class="font-semibold text-slate-900" x-text="row.unit || '—'"></span></span>
                                            <span>Остаток (выбранный склад):
                                                <span class="font-semibold text-emerald-800" x-text="fmtQty(row.stock_qty ?? 0)"></span>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 px-3 py-2.5">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-900/80">Продажи</p>
                                            <p class="mt-1 tabular-nums text-sm text-slate-800">
                                                <span class="text-slate-600">Всего:</span>
                                                <span class="font-bold text-emerald-950" x-text="fmtQty(row.sold_total)"></span>
                                            </p>
                                            <p class="tabular-nums text-xs text-slate-700"><span class="text-slate-600">Розница:</span> <span x-text="fmtQty(row.retail_sale)"></span></p>
                                            <p class="tabular-nums text-xs text-slate-700"><span class="text-slate-600">Юрлица:</span> <span x-text="fmtQty(row.legal_sale)"></span></p>
                                        </div>
                                        <div class="rounded-xl border border-sky-100 bg-sky-50/60 px-3 py-2.5">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-sky-900/80">Закупки</p>
                                            <p class="mt-1 tabular-nums text-sm text-slate-800">
                                                <span class="text-slate-600">Закуплено:</span>
                                                <span class="font-semibold text-sky-950" x-text="fmtQty(row.purchase)"></span>
                                            </p>
                                            <p class="tabular-nums text-xs text-rose-800"><span class="text-slate-600">Возврат поставщику:</span> <span x-text="fmtQty(row.purchase_return)"></span></p>
                                        </div>
                                        <div class="rounded-xl border border-violet-100 bg-violet-50/50 px-3 py-2.5 sm:col-span-2 lg:col-span-1">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-violet-900/80">Перемещения</p>
                                            <p class="mt-1 flex flex-wrap gap-x-4 gap-y-1 tabular-nums text-xs text-slate-800">
                                                <span><span class="text-slate-600">Отпуск:</span> <span x-text="fmtQty(row.transfer_out)"></span></span>
                                                <span><span class="text-slate-600">Приход:</span> <span x-text="fmtQty(row.transfer_in)"></span></span>
                                                <span class="font-semibold text-violet-950">Нетто: <span x-text="fmtQty(row.transfer_net)"></span></span>
                                            </p>
                                        </div>
                                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2.5">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-600">Оприходование</p>
                                            <p class="mt-1 text-sm font-semibold tabular-nums text-slate-900" x-text="fmtQty(row.surplus)"></p>
                                        </div>
                                        <div class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2.5">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-600">Возврат от покупателя</p>
                                            <p class="mt-1 text-sm font-semibold tabular-nums text-slate-900" x-text="fmtQty(row.customer_return)"></p>
                                        </div>
                                        <div class="rounded-xl border border-rose-100 bg-rose-50/60 px-3 py-2.5">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-rose-900/80">Списание</p>
                                            <p class="mt-1 text-sm font-bold tabular-nums text-rose-950" x-text="fmtQty(row.writeoff)"></p>
                                        </div>
                                    </div>

                                    <div class="mt-5 border-t border-slate-100 pt-4">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Журнал движений за период</p>
                                        <p x-show="ledgerLoading" class="mt-3 rounded-xl border border-dashed border-slate-200 bg-slate-50/70 py-8 text-center text-[13px] text-slate-600">Загрузка…</p>
                                        <p x-show="ledgerError" x-text="ledgerError" class="mt-3 text-[13px] text-red-600"></p>
                                        <p
                                            x-show="!ledgerLoading && !ledgerError && ledgerRows.length === 0"
                                            class="mt-3 rounded-xl border border-dashed border-slate-200 bg-slate-50/70 py-8 text-center text-[13px] text-slate-600"
                                        >Нет строк журнала за выбранный период и склад.</p>
                                        <div
                                            x-show="!ledgerLoading && !ledgerError && ledgerRows.length > 0"
                                            class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                                        >
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-slate-100 text-[13px]">
                                                    <thead class="bg-emerald-50/90 text-left text-[10px] font-bold uppercase tracking-wider text-emerald-900">
                                                        <tr>
                                                            <th class="whitespace-nowrap px-3 py-2.5">Дата</th>
                                                            <th class="px-3 py-2.5">Операция</th>
                                                            <th class="px-3 py-2.5">Склад</th>
                                                            <th class="whitespace-nowrap px-3 py-2.5 text-right">Кол-во</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-slate-100">
                                                        <template x-for="(m, idx) in ledgerRows" :key="idx">
                                                            <tr class="bg-white transition hover:bg-emerald-50/50">
                                                                <td class="whitespace-nowrap px-3 py-2.5 tabular-nums text-slate-600" x-text="m.date_human"></td>
                                                                <td class="px-3 py-2.5 font-medium text-slate-900" x-text="m.label"></td>
                                                                <td class="max-w-[10rem] truncate px-3 py-2.5 text-slate-600 sm:max-w-none" x-text="m.warehouse" :title="m.warehouse"></td>
                                                                <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono text-sm font-semibold tabular-nums" :class="m.direction === 'in' ? 'text-emerald-700' : (m.direction === 'out' ? 'text-red-700' : 'text-slate-800')" x-text="fmtLedgerQty(m.quantity, m.direction)"></td>
                                                            </tr>
                                                        </template>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <p class="mt-5 text-center text-[11px] text-slate-500">Количества по операциям за выбранные даты; без услуг.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
                </div>
            @elseif (($viewMode ?? '') === 'good')
                @php($r = $detailRow)
                <nav class="flex flex-wrap items-center gap-2 border-b border-emerald-100/80 bg-emerald-50/35 px-4 py-3 text-[13px] sm:px-5" aria-label="Навигация">
                    <a href="{{ $gmBaseRoute() }}" class="rounded-lg px-2.5 py-1 font-semibold text-emerald-800 transition hover:bg-white/80 hover:text-emerald-950">Все категории</a>
                    <span class="text-emerald-200" aria-hidden="true">/</span>
                    <a
                        href="{{ $gmBaseRoute(['category' => $detailCategoryKey]) }}"
                        class="rounded-lg px-2.5 py-1 font-semibold text-emerald-800 transition hover:bg-white/80 hover:text-emerald-950"
                    >{{ $detailCategoryTitle }}</a>
                    <span class="text-emerald-200" aria-hidden="true">/</span>
                    <span class="truncate font-semibold text-slate-800" title="{{ $r['name'] }}">{{ \Illuminate\Support\Str::limit($r['name'], 70) }}</span>
                </nav>

                <div class="border-b border-slate-100 bg-white px-4 py-4 sm:px-5">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Движение за выбранный период (только чтение)</p>
                    <h2 class="mt-1 text-base font-bold text-slate-900 sm:text-[17px]">{{ $r['name'] }}</h2>
                    <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[12px] text-slate-600">
                        <span>Артикул: <span class="font-mono font-semibold text-slate-900">{{ $r['article'] !== '' ? $r['article'] : '—' }}</span></span>
                        <span>Ед.: <span class="font-semibold text-slate-900">{{ $r['unit'] }}</span></span>
                        <span class="tabular-nums">Остаток на складе (выбранный отбор): <span class="font-semibold text-emerald-800">{{ $fmtQty($r['stock_qty'] ?? 0) }}</span></span>
                    </p>
                </div>

                <div class="grid gap-4 border-b border-slate-100 px-4 py-5 sm:grid-cols-2 lg:grid-cols-3 sm:px-5">
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 px-3 py-2.5">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-900/80">Продажи</p>
                        <p class="mt-1 tabular-nums text-sm"><span class="text-slate-600">Всего:</span> <span class="font-bold text-emerald-950">{{ $fmtQty($r['sold_total']) }}</span></p>
                        <p class="tabular-nums text-xs"><span class="text-slate-600">Розница:</span> {{ $fmtQty($r['retail_sale']) }}</p>
                        <p class="tabular-nums text-xs"><span class="text-slate-600">Юрлица:</span> {{ $fmtQty($r['legal_sale']) }}</p>
                    </div>
                    <div class="rounded-xl border border-sky-100 bg-sky-50/60 px-3 py-2.5">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-sky-900/80">Закупки</p>
                        <p class="mt-1 tabular-nums text-sm"><span class="text-slate-600">Закуплено:</span> <span class="font-semibold text-sky-950">{{ $fmtQty($r['purchase']) }}</span></p>
                        <p class="tabular-nums text-xs text-rose-800"><span class="text-slate-600">Возврат поставщику:</span> {{ $fmtQty($r['purchase_return']) }}</p>
                    </div>
                    <div class="rounded-xl border border-violet-100 bg-violet-50/50 px-3 py-2.5 sm:col-span-2 lg:col-span-1">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-violet-900/80">Перемещения</p>
                        <p class="mt-1 flex flex-wrap gap-x-4 tabular-nums text-xs">
                            <span><span class="text-slate-600">Отпуск:</span> {{ $fmtQty($r['transfer_out']) }}</span>
                            <span><span class="text-slate-600">Приход:</span> {{ $fmtQty($r['transfer_in']) }}</span>
                            <span class="font-semibold text-violet-950">Нетто: {{ $fmtQty($r['transfer_net']) }}</span>
                        </p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2.5">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-600">Оприходование</p>
                        <p class="mt-1 text-sm font-semibold tabular-nums">{{ $fmtQty($r['surplus']) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2.5">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-600">Возврат от покупателя</p>
                        <p class="mt-1 text-sm font-semibold tabular-nums">{{ $fmtQty($r['customer_return']) }}</p>
                    </div>
                    <div class="rounded-xl border border-rose-100 bg-rose-50/60 px-3 py-2.5">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-rose-900/80">Списание</p>
                        <p class="mt-1 text-sm font-bold tabular-nums text-rose-950">{{ $fmtQty($r['writeoff']) }}</p>
                    </div>
                </div>

                <div class="border-b border-slate-100 bg-white px-4 py-5 sm:px-5">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Журнал движений за период</p>
                    @if (count($ledgerRows ?? []) === 0)
                        <p class="mt-3 rounded-xl border border-dashed border-slate-200 bg-slate-50/70 py-8 text-center text-[13px] text-slate-600">Нет строк журнала за выбранный период и склад.</p>
                    @else
                        <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-100 text-[13px]">
                                    <thead class="bg-emerald-50/90 text-left text-[10px] font-bold uppercase tracking-wider text-emerald-900">
                                        <tr>
                                            <th class="whitespace-nowrap px-3 py-2.5">Дата</th>
                                            <th class="px-3 py-2.5">Операция</th>
                                            <th class="px-3 py-2.5">Склад</th>
                                            <th class="whitespace-nowrap px-3 py-2.5 text-right">Кол-во</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach (($ledgerRows ?? []) as $lr)
                                            <tr class="bg-white transition hover:bg-emerald-50/50">
                                                <td class="whitespace-nowrap px-3 py-2.5 tabular-nums text-slate-600">{{ $lr['date_human'] }}</td>
                                                <td class="px-3 py-2.5 font-medium text-slate-900">{{ $lr['label'] }}</td>
                                                <td class="max-w-[10rem] truncate px-3 py-2.5 text-slate-600 sm:max-w-none" title="{{ $lr['warehouse'] }}">{{ $lr['warehouse'] }}</td>
                                                <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono text-sm font-semibold tabular-nums {{ ($lr['direction'] ?? '') === 'in' ? 'text-emerald-700' : (($lr['direction'] ?? '') === 'out' ? 'text-red-700' : 'text-slate-800') }}">{{ (($lr['direction'] ?? '') === 'in' ? '+' : (($lr['direction'] ?? '') === 'out' ? '−' : '')) }}{{ $fmtQty($lr['quantity'] ?? 0) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="px-4 pb-6 pt-4 sm:px-5">
                    @php($backHref = $gmBaseRoute(['category' => $detailCategoryKey]) . (trim((string) ($searchQuery ?? '')) !== '' ? '&q=' . rawurlencode((string) $searchQuery) : ''))
                    <a
                        href="{{ $backHref }}"
                        class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >← Назад к списку категории</a>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
