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
                    Все товары филиала (без услуг): количества по видам операций за период.
                    @if ($selectedWarehouseId === 0)
                        Склад «Все склады» — сумма по филиалу; перемещения между складами учитываются как отпуск с одного и приход на другой.
                    @else
                        Фильтр по выбранному складу (как в документах).
                    @endif
                </p>
            </div>

            <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
                <form method="GET" action="{{ route('admin.reports.goods-movement') }}" class="flex flex-wrap items-end gap-4">
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
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Сформировать</button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="whitespace-nowrap px-3 py-2.5">Артикул</th>
                            <th class="min-w-[10rem] px-3 py-2.5">Наименование</th>
                            <th class="whitespace-nowrap px-3 py-2.5">Ед.</th>
                            <th class="whitespace-nowrap px-3 py-2.5 text-right text-emerald-900">Продано всего</th>
                            <th class="whitespace-nowrap px-3 py-2.5 text-right">Розница</th>
                            <th class="whitespace-nowrap px-3 py-2.5 text-right">Юрлица</th>
                            <th class="whitespace-nowrap px-3 py-2.5 text-right">Закуплено</th>
                            <th class="whitespace-nowrap px-3 py-2.5 text-right">Возврат поставщику</th>
                            <th class="whitespace-nowrap px-3 py-2.5 text-right">Перемещение (отпуск)</th>
                            <th class="whitespace-nowrap px-3 py-2.5 text-right">Перемещение (приход)</th>
                            <th class="whitespace-nowrap px-3 py-2.5 text-right text-slate-600">Перемещение (нетто)</th>
                            <th class="whitespace-nowrap px-3 py-2.5 text-right">Оприходование</th>
                            <th class="whitespace-nowrap px-3 py-2.5 text-right">Возврат от покупателя</th>
                            <th class="whitespace-nowrap px-3 py-2.5 text-right">Списание</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $r)
                            <tr class="hover:bg-emerald-50/30">
                                <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-slate-600">{{ $r['article'] !== '' ? $r['article'] : '—' }}</td>
                                <td class="px-3 py-2 font-medium text-slate-900">{{ $r['name'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $r['unit'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums font-semibold text-emerald-900">{{ $fmtQty($r['sold_total']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-800">{{ $fmtQty($r['retail_sale']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-800">{{ $fmtQty($r['legal_sale']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-800">{{ $fmtQty($r['purchase']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-rose-800">{{ $fmtQty($r['purchase_return']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-800">{{ $fmtQty($r['transfer_out']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-800">{{ $fmtQty($r['transfer_in']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-600">{{ $fmtQty($r['transfer_net']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-800">{{ $fmtQty($r['surplus']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-800">{{ $fmtQty($r['customer_return']) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-rose-800">{{ $fmtQty($r['writeoff']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-slate-200 bg-slate-50/90 text-xs font-semibold text-slate-900">
                        <tr>
                            <td class="bg-slate-50 px-3 py-2.5" colspan="2">Итого</td>
                            <td class="px-3 py-2.5"></td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-emerald-900">{{ $fmtQty($totals['sold_total']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmtQty($totals['retail_sale']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmtQty($totals['legal_sale']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmtQty($totals['purchase']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-rose-800">{{ $fmtQty($totals['purchase_return']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmtQty($totals['transfer_out']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmtQty($totals['transfer_in']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-slate-600">{{ $fmtQty($totals['transfer_net']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmtQty($totals['surplus']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmtQty($totals['customer_return']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-rose-800">{{ $fmtQty($totals['writeoff']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-admin-layout>
