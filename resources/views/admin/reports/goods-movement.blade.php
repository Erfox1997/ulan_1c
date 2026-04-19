@php
    $fmtQty = static fn ($v) => number_format((float) $v, 2, ',', ' ');
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-[110rem] space-y-4">
        <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
            <div
                class="border-b border-emerald-900/10 px-4 py-3 text-white sm:px-5"
                style="background: linear-gradient(120deg, #047857 0%, #0d9488 50%, #0f766e 100%);"
            >
                <h1 class="text-sm font-bold tracking-tight">{{ $pageTitle }}</h1>
                <p class="mt-0.5 text-[11px] font-medium text-emerald-100/90">
                    Количества по видам операций за период (без услуг).
                    @if ($selectedWarehouseId === 0)
                        Склад «Все склады» — сумма по филиалу; перемещения между складами учитываются как отпуск с одного и приход на другой.
                    @else
                        Фильтр по выбранному складу (как в документах).
                    @endif
                </p>
            </div>

            <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
                <form method="GET" action="{{ route('admin.reports.goods-movement') }}" class="space-y-4">
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
                        <div class="min-w-[14rem] flex-1">
                            <label class="mb-1 block text-xs font-semibold text-slate-700">Поиск по артикулу или названию</label>
                            <input
                                type="search"
                                name="q"
                                value="{{ $searchQuery }}"
                                placeholder="Например, фильтр или часть названия"
                                autocomplete="off"
                                class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                            />
                        </div>
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
                                <span class="block text-[11px] font-normal text-slate-500">
                                    Скрывает строки, где все количества за выбранные даты равны нулю (каталог без операций).
                                </span>
                            </span>
                        </label>
                    </div>
                    <input type="hidden" name="page" value="1" />
                </form>
            </div>

            @php
                $t = $totals;
                $netPurchase = (float) $t['purchase'] - (float) $t['purchase_return'];
            @endphp
            <div class="border-b border-slate-100 bg-slate-50/80 px-4 py-3 sm:px-5">
                <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Сводка по текущему отбору</p>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                    <div class="rounded-lg border border-emerald-200/80 bg-white px-3 py-2 shadow-sm">
                        <p class="text-[10px] font-medium text-slate-500">Продано всего</p>
                        <p class="mt-0.5 text-sm font-bold tabular-nums text-emerald-900">{{ $fmtQty($t['sold_total']) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200/90 bg-white px-3 py-2 shadow-sm">
                        <p class="text-[10px] font-medium text-slate-500">Розница</p>
                        <p class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900">{{ $fmtQty($t['retail_sale']) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200/90 bg-white px-3 py-2 shadow-sm">
                        <p class="text-[10px] font-medium text-slate-500">Юрлица</p>
                        <p class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900">{{ $fmtQty($t['legal_sale']) }}</p>
                    </div>
                    <div class="rounded-lg border border-sky-200/80 bg-white px-3 py-2 shadow-sm">
                        <p class="text-[10px] font-medium text-slate-500">Закупка нетто</p>
                        <p class="mt-0.5 text-sm font-semibold tabular-nums text-sky-950">{{ $fmtQty($netPurchase) }}</p>
                        <p class="mt-0.5 text-[10px] text-slate-500">закуп − возврат поставщику</p>
                    </div>
                    <div class="rounded-lg border border-violet-200/80 bg-white px-3 py-2 shadow-sm">
                        <p class="text-[10px] font-medium text-slate-500">Перемещение нетто</p>
                        <p class="mt-0.5 text-sm font-semibold tabular-nums text-violet-950">{{ $fmtQty($t['transfer_net']) }}</p>
                    </div>
                    <div class="rounded-lg border border-rose-200/80 bg-white px-3 py-2 shadow-sm">
                        <p class="text-[10px] font-medium text-slate-500">Списание</p>
                        <p class="mt-0.5 text-sm font-semibold tabular-nums text-rose-900">{{ $fmtQty($t['writeoff']) }}</p>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-slate-600">
                    В списке
                    <span class="font-semibold text-slate-800">{{ number_format($filteredGoodsCount, 0, ',', ' ') }}</span>
                    @if ($onlyWithMovement || $searchQuery !== '')
                        из {{ number_format($catalogGoodsCount, 0, ',', ' ') }} номенклатурных позиций филиала
                    @else
                        номенклатурных позиций (полный каталог)
                    @endif
                    @if ($rowsPaginator->lastPage() > 1)
                        · страница {{ $rowsPaginator->currentPage() }} из {{ $rowsPaginator->lastPage() }}
                    @endif
                </p>
            </div>

            @if ($rowsPaginator->lastPage() > 1)
                <div class="border-b border-slate-100 bg-white px-4 py-2 text-[11px] text-slate-700 sm:px-5">
                    {{ $rowsPaginator->links() }}
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                            <th class="sticky left-0 z-20 w-28 max-w-[7rem] whitespace-nowrap border-r border-slate-200 bg-slate-50 px-3 py-1.5 shadow-[2px_0_6px_-2px_rgba(15,23,42,0.12)]" rowspan="2">Артикул</th>
                            <th class="sticky left-28 z-20 min-w-[10rem] border-r border-slate-200 bg-slate-50 px-3 py-1.5 shadow-[2px_0_6px_-2px_rgba(15,23,42,0.12)]" rowspan="2">Наименование</th>
                            <th class="whitespace-nowrap px-3 py-1.5" rowspan="2">Ед.</th>
                            <th class="border-l border-slate-200 px-3 py-1.5 text-center text-emerald-900" colspan="3">Продажи</th>
                            <th class="border-l border-slate-200 px-3 py-1.5 text-center" colspan="2">Закупки</th>
                            <th class="border-l border-slate-200 px-3 py-1.5 text-center" colspan="3">Перемещения</th>
                            <th class="border-l border-slate-200 px-3 py-1.5 text-center" colspan="3">Прочее</th>
                        </tr>
                        <tr class="border-b border-slate-200 bg-slate-50/90 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                            <th class="whitespace-nowrap border-l border-slate-200 px-3 py-2 text-right text-emerald-900">Продано всего</th>
                            <th class="whitespace-nowrap px-3 py-2 text-right">Розница</th>
                            <th class="whitespace-nowrap px-3 py-2 text-right">Юрлица</th>
                            <th class="whitespace-nowrap border-l border-slate-200 px-3 py-2 text-right">Закуплено</th>
                            <th class="whitespace-nowrap px-3 py-2 text-right">Возврат поставщику</th>
                            <th class="whitespace-nowrap border-l border-slate-200 px-3 py-2 text-right">Отпуск</th>
                            <th class="whitespace-nowrap px-3 py-2 text-right">Приход</th>
                            <th class="whitespace-nowrap px-3 py-2 text-right text-slate-600">Нетто</th>
                            <th class="whitespace-nowrap border-l border-slate-200 px-3 py-2 text-right">Оприходование</th>
                            <th class="whitespace-nowrap px-3 py-2 text-right">Возврат от покупателя</th>
                            <th class="whitespace-nowrap px-3 py-2 text-right">Списание</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rowsPaginator as $r)
                            <tr class="group hover:bg-emerald-50/30">
                                <td class="sticky left-0 z-10 w-28 max-w-[7rem] whitespace-nowrap border-r border-slate-100 bg-white px-3 py-2 font-mono text-xs text-slate-600 shadow-[2px_0_6px_-2px_rgba(15,23,42,0.06)] group-hover:bg-emerald-50/40">{{ $r['article'] !== '' ? $r['article'] : '—' }}</td>
                                <td class="sticky left-28 z-10 min-w-[10rem] border-r border-slate-100 bg-white px-3 py-2 font-medium text-slate-900 shadow-[2px_0_6px_-2px_rgba(15,23,42,0.06)] group-hover:bg-emerald-50/40">{{ $r['name'] }}</td>
                                <td class="whitespace-nowrap bg-white px-3 py-2 text-slate-600 group-hover:bg-emerald-50/30">{{ $r['unit'] }}</td>
                                <td class="whitespace-nowrap border-l border-slate-100 px-3 py-2 text-right tabular-nums font-semibold text-emerald-900 group-hover:bg-emerald-50/30">{{ $fmtQty($r['sold_total']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-800 group-hover:bg-emerald-50/30">{{ $fmtQty($r['retail_sale']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-800 group-hover:bg-emerald-50/30">{{ $fmtQty($r['legal_sale']) }}</td>
                                <td class="whitespace-nowrap border-l border-slate-100 px-3 py-2 text-right tabular-nums text-slate-800 group-hover:bg-emerald-50/30">{{ $fmtQty($r['purchase']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-rose-800 group-hover:bg-emerald-50/30">{{ $fmtQty($r['purchase_return']) }}</td>
                                <td class="whitespace-nowrap border-l border-slate-100 px-3 py-2 text-right tabular-nums text-slate-800 group-hover:bg-emerald-50/30">{{ $fmtQty($r['transfer_out']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-800 group-hover:bg-emerald-50/30">{{ $fmtQty($r['transfer_in']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-600 group-hover:bg-emerald-50/30">{{ $fmtQty($r['transfer_net']) }}</td>
                                <td class="whitespace-nowrap border-l border-slate-100 px-3 py-2 text-right tabular-nums text-slate-800 group-hover:bg-emerald-50/30">{{ $fmtQty($r['surplus']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-800 group-hover:bg-emerald-50/30">{{ $fmtQty($r['customer_return']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-rose-800 group-hover:bg-emerald-50/30">{{ $fmtQty($r['writeoff']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-8 text-center text-sm text-slate-600" colspan="14">Нет строк по заданным условиям. Снимите фильтр «только с движением» или измените поиск.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($filteredGoodsCount > 0)
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50/90 text-xs font-semibold text-slate-900">
                            <tr>
                                <td class="sticky left-0 z-10 border-r border-slate-200 bg-slate-50 px-3 py-2.5 shadow-[2px_0_6px_-2px_rgba(15,23,42,0.08)]" colspan="2">Итого по отбору</td>
                                <td class="bg-slate-50 px-3 py-2.5"></td>
                                <td class="border-l border-slate-200 bg-slate-50 px-3 py-2.5 text-right tabular-nums text-emerald-900">{{ $fmtQty($totals['sold_total']) }}</td>
                                <td class="bg-slate-50 px-3 py-2.5 text-right tabular-nums">{{ $fmtQty($totals['retail_sale']) }}</td>
                                <td class="bg-slate-50 px-3 py-2.5 text-right tabular-nums">{{ $fmtQty($totals['legal_sale']) }}</td>
                                <td class="border-l border-slate-200 bg-slate-50 px-3 py-2.5 text-right tabular-nums">{{ $fmtQty($totals['purchase']) }}</td>
                                <td class="bg-slate-50 px-3 py-2.5 text-right tabular-nums text-rose-800">{{ $fmtQty($totals['purchase_return']) }}</td>
                                <td class="border-l border-slate-200 bg-slate-50 px-3 py-2.5 text-right tabular-nums">{{ $fmtQty($totals['transfer_out']) }}</td>
                                <td class="bg-slate-50 px-3 py-2.5 text-right tabular-nums">{{ $fmtQty($totals['transfer_in']) }}</td>
                                <td class="bg-slate-50 px-3 py-2.5 text-right tabular-nums text-slate-600">{{ $fmtQty($totals['transfer_net']) }}</td>
                                <td class="border-l border-slate-200 bg-slate-50 px-3 py-2.5 text-right tabular-nums">{{ $fmtQty($totals['surplus']) }}</td>
                                <td class="bg-slate-50 px-3 py-2.5 text-right tabular-nums">{{ $fmtQty($totals['customer_return']) }}</td>
                                <td class="bg-slate-50 px-3 py-2.5 text-right tabular-nums text-rose-800">{{ $fmtQty($totals['writeoff']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            @if ($rowsPaginator->lastPage() > 1)
                <div class="border-t border-slate-100 bg-slate-50/50 px-4 py-2 text-[11px] text-slate-700 sm:px-5">
                    {{ $rowsPaginator->links() }}
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
