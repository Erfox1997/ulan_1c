@php
    $fmtSum = static fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', ' ');
    $historyQueryBase = array_filter([
        'warehouse_id' => $selectedWarehouseId > 0 ? $selectedWarehouseId : null,
        'limit' => $limit,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
    ], static fn ($v) => $v !== null && $v !== '');
@endphp
<x-admin-layout pageTitle="История розничных продаж" main-class="bg-slate-100/80 px-3 py-5 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-6xl space-y-5">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 shadow-sm">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->has('delete'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                {{ $errors->first('delete') }}
            </div>
        @endif

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ route('admin.retail-sales.index', array_filter(['warehouse_id' => $selectedWarehouseId > 0 ? $selectedWarehouseId : null], static fn ($v) => (int) $v > 0)) }}" class="text-sm font-semibold text-emerald-800 hover:underline">← К быстрой продаже</a>
                <h1 class="mt-2 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">История продаж</h1>
                <p class="mt-1 text-sm text-slate-600">Фильтры и правка проведённых чеков.</p>
            </div>
        </div>

        @if ($warehouses->isEmpty())
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-950">
                Сначала добавьте склад.
                <a href="{{ route('admin.warehouses.create') }}" class="ml-2 font-semibold text-emerald-800 underline">Создать склад</a>
            </div>
        @else
            <form
                method="GET"
                action="{{ route('admin.retail-sales.history') }}"
                class="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:p-5"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end">
                    <div class="min-w-0 lg:max-w-xs">
                        <label for="rsh_wh" class="block text-xs font-medium text-slate-600">Склад</label>
                        <select
                            id="rsh_wh"
                            name="warehouse_id"
                            class="mt-1 block w-full rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-10 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                            onchange="this.form.submit()"
                        >
                            @foreach ($warehouses as $w)
                                <option value="{{ $w->id }}" @selected((int) $w->id === (int) $selectedWarehouseId)>{{ $w->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label for="rsh_limit" class="block text-xs font-medium text-slate-600">Показать, шт.</label>
                        <select
                            id="rsh_limit"
                            name="limit"
                            class="mt-1 block w-full min-w-[8rem] rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-10 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 sm:w-auto"
                        >
                            @foreach ([50, 100, 200, 500] as $opt)
                                <option value="{{ $opt }}" @selected($limit === $opt)>{{ $opt }} (последних)</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label for="rsh_from" class="block text-xs font-medium text-slate-600">Дата документа с</label>
                        <input
                            id="rsh_from"
                            type="date"
                            name="date_from"
                            value="{{ $dateFrom }}"
                            class="mt-1 block w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 sm:w-auto"
                        />
                    </div>
                    <div class="min-w-0">
                        <label for="rsh_to" class="block text-xs font-medium text-slate-600">по</label>
                        <input
                            id="rsh_to"
                            type="date"
                            name="date_to"
                            value="{{ $dateTo }}"
                            class="mt-1 block w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 sm:w-auto"
                        />
                    </div>
                    <div class="flex flex-wrap gap-2 pb-0.5 lg:ml-auto">
                        <button type="submit" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                            Применить
                        </button>
                        <a
                            href="{{ route('admin.retail-sales.history', array_filter(['warehouse_id' => $selectedWarehouseId > 0 ? $selectedWarehouseId : null], static fn ($v) => (int) $v > 0)) }}"
                            class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                        >Сбросить даты</a>
                    </div>
                </div>
            </form>

            <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/5">
                @if ($sales->isEmpty())
                    <div class="px-5 py-12 text-center text-sm text-slate-600">
                        Нет продаж по выбранным условиям.
                    </div>
                @else
                    <div class="overflow-x-auto p-4 sm:p-5">
                        <table class="min-w-full border-collapse border border-slate-200 text-sm">
                            <thead>
                                <tr class="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                                    <th class="border border-slate-200 px-3 py-2.5">Время</th>
                                    <th class="border border-slate-200 px-3 py-2.5">Дата док.</th>
                                    <th class="border border-slate-200 px-3 py-2.5">Счёт</th>
                                    <th class="border border-slate-200 px-3 py-2.5 text-right">Сумма</th>
                                    <th class="border border-slate-200 px-3 py-2.5 text-right">Строк</th>
                                    <th class="border border-slate-200 px-3 py-2.5 text-center">Действия</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                @foreach ($sales as $sale)
                                    <tr class="transition-colors hover:bg-slate-50/90">
                                        <td class="whitespace-nowrap border border-slate-200 px-3 py-2.5 text-slate-700">
                                            {{ $sale->created_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                                        </td>
                                        <td class="whitespace-nowrap border border-slate-200 px-3 py-2.5 text-slate-700">
                                            {{ $sale->document_date->format('d.m.Y') }}
                                        </td>
                                        <td class="max-w-[14rem] truncate border border-slate-200 px-3 py-2.5 text-slate-600" title="{{ $sale->organizationBankAccount?->labelWithoutAccountNumber() }}">
                                            {{ $sale->organizationBankAccount?->labelWithoutAccountNumber() ?? '—' }}
                                        </td>
                                        <td class="whitespace-nowrap border border-slate-200 px-3 py-2.5 text-right tabular-nums font-medium text-slate-900">{{ $fmtSum($sale->total_amount) }}</td>
                                        <td class="whitespace-nowrap border border-slate-200 px-3 py-2.5 text-right text-slate-600">{{ $sale->lines->count() }}</td>
                                        <td class="border border-slate-200 px-3 py-2.5 text-center">
                                            <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1">
                                                <a
                                                    href="{{ route('admin.retail-sales.edit', $sale) }}{{ $historyQueryBase !== [] ? '?' . http_build_query($historyQueryBase) : '' }}"
                                                    class="text-sm font-semibold text-emerald-800 hover:underline"
                                                >Изменить</a>
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.retail-sales.destroy', $sale) }}"
                                                    class="inline"
                                                    onsubmit="return confirm('Удалить продажу от {{ $sale->document_date->format('d.m.Y') }}? Остатки будут восстановлены.');"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="return_warehouse_id" value="{{ $selectedWarehouseId }}">
                                                    <input type="hidden" name="return_limit" value="{{ $limit }}">
                                                    <input type="hidden" name="return_date_from" value="{{ $dateFrom }}">
                                                    <input type="hidden" name="return_date_to" value="{{ $dateTo }}">
                                                    <button type="submit" class="text-sm font-semibold text-red-700 hover:underline">Удалить</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-admin-layout>
