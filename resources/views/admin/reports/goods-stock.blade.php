@php
    $fmtQty = static fn ($v) => number_format((float) $v, 2, ',', ' ');
    $fmtMoney = static fn ($v) => $v === null ? '—' : \App\Support\InvoiceNakladnayaFormatter::formatMoney((float) $v);
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-6xl space-y-4">
        <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
            <div
                class="border-b border-emerald-900/10 px-4 py-3 text-white sm:px-5"
                style="background: linear-gradient(120deg, #047857 0%, #0d9488 50%, #0f766e 100%);"
            >
                <h1 class="text-sm font-bold tracking-tight">{{ $pageTitle }}</h1>
                <p class="mt-0.5 text-[11px] font-medium text-emerald-100/90">Текущие остатки по учёту (все склады или выбранный склад).</p>
            </div>

            <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
                <form method="GET" action="{{ route('admin.reports.goods-stock') }}" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[12rem] flex-1">
                        <label for="r_gs_wh" class="mb-1 block text-xs font-semibold text-slate-700">Склад</label>
                        <select
                            id="r_gs_wh"
                            name="warehouse_id"
                            class="w-full rounded-lg border border-slate-200 bg-slate-50/80 py-2 pl-2.5 pr-8 text-sm font-medium text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                            onchange="this.form.submit()"
                        >
                            <option value="0" @selected($selectedWarehouseId === 0)>Все склады (сводно)</option>
                            @foreach ($warehouses as $w)
                                <option value="{{ $w->id }}" @selected((int) $w->id === (int) $selectedWarehouseId)>{{ $w->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>

            @if ($warehouses->isEmpty())
                <div class="px-4 py-10 text-center text-sm text-slate-600">
                    Нет складов. <a href="{{ route('admin.warehouses.create') }}" class="font-semibold text-emerald-700 underline">Создать склад</a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="whitespace-nowrap px-4 py-2.5">Склад</th>
                                <th class="whitespace-nowrap px-4 py-2.5">Артикул</th>
                                <th class="min-w-[8rem] px-4 py-2.5">Наименование</th>
                                <th class="whitespace-nowrap px-4 py-2.5">Ед.</th>
                                <th class="whitespace-nowrap px-4 py-2.5 text-right">Количество</th>
                                <th class="whitespace-nowrap px-4 py-2.5 text-right">Сумма (себест.)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($rows as $r)
                                <tr class="hover:bg-emerald-50/30">
                                    <td class="whitespace-nowrap px-4 py-2.5 text-slate-600">{{ $r['warehouse'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-slate-600">{{ $r['article'] !== '' ? $r['article'] : '—' }}</td>
                                    <td class="px-4 py-2.5 font-medium text-slate-900">{{ $r['name'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-slate-600">{{ $r['unit'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right font-semibold tabular-nums text-slate-900">{{ $fmtQty($r['quantity']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-slate-800">{{ $fmtMoney($r['amount']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500">
                                        Нет товарных остатков по данным учёта.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
