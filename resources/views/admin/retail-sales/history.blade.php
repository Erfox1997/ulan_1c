@php
    $fmtSum = static fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', ' ');
    $historyQueryBase = array_filter([
        'warehouse_id' => $selectedWarehouseId > 0 ? $selectedWarehouseId : null,
        'limit' => $limit,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'good_id' => ($filterGoodId ?? 0) > 0 ? (int) $filterGoodId : null,
    ], static fn ($v) => $v !== null && $v !== '');
    // Относительный путь: тот же origin, что у страницы (иначе fetch может дать «код 0» при localhost vs 127.0.0.1).
    $rsPhysBase = rtrim(route('admin.retail-sales.index', [], false), '/');
    $hasPaymentAccounts = isset($paymentAccountsPayload) && count($paymentAccountsPayload) > 0;
@endphp
<x-admin-layout pageTitle="История розничных продаж" main-class="bg-slate-100/80 px-3 py-5 sm:px-4 lg:px-6">
    <div
        class="mx-auto max-w-6xl space-y-5"
        @if ($warehouses->isNotEmpty())
            x-data="retailSalesHistoryPage(@js([
                'physicalBase' => $rsPhysBase,
                'csrf' => csrf_token(),
                'hasAccounts' => $hasPaymentAccounts,
                'historyParams' => [
                    'warehouse_id' => $selectedWarehouseId > 0 ? $selectedWarehouseId : null,
                    'limit' => $limit,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'good_id' => ($filterGoodId ?? 0) > 0 ? (int) $filterGoodId : null,
                ],
            ]))"
            @keydown.escape.window="closeReturnModal()"
        @endif
    >
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 shadow-sm">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ route('admin.retail-sales.index', array_filter(['warehouse_id' => $selectedWarehouseId > 0 ? $selectedWarehouseId : null], static fn ($v) => (int) $v > 0)) }}" class="text-sm font-semibold text-emerald-800 hover:underline">← К быстрой продаже</a>
                <h1 class="mt-2 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">История продаж</h1>
            </div>
        </div>

        @if ($warehouses->isEmpty())
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-950">
                Сначала добавьте склад.
                <a href="{{ route('admin.warehouses.create') }}" class="ml-2 font-semibold text-emerald-800 underline">Создать склад</a>
            </div>
        @else
            @include('admin.partials.journal-good-filter', [
                'formSelector' => '#rsh-history-form',
                'goodsSearchUrl' => route('admin.goods.search'),
                'warehouseId' => $selectedWarehouseId,
                'filterGoodId' => (int) ($filterGoodId ?? 0),
                'filterGoodSummary' => $filterGoodSummary ?? '',
                'returnsUrl' => route('admin.customer-returns.index'),
            ])
            <form
                id="rsh-history-form"
                data-journal-filter-form
                method="GET"
                action="{{ route('admin.retail-sales.history') }}"
                class="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:p-5"
            >
                <input type="hidden" name="good_id" value="{{ ($filterGoodId ?? 0) > 0 ? (int) $filterGoodId : '' }}">
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
                            href="{{ route('admin.retail-sales.history', array_filter([
                                'warehouse_id' => $selectedWarehouseId > 0 ? $selectedWarehouseId : null,
                                'good_id' => ($filterGoodId ?? 0) > 0 ? (int) $filterGoodId : null,
                            ], static fn ($v) => $v !== null && $v !== '' && (int) $v > 0)) }}"
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
                                    <th class="border border-slate-200 px-3 py-2.5">Оплаты</th>
                                    <th class="border border-slate-200 px-3 py-2.5 text-right">Сумма</th>
                                    <th class="border border-slate-200 px-3 py-2.5 text-right">Долг</th>
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
                                        <td class="max-w-[18rem] border border-slate-200 px-3 py-2.5 text-xs leading-snug text-slate-700">
                                            @forelse ($sale->payments as $p)
                                                <span class="whitespace-nowrap">{{ $p->organizationBankAccount?->labelWithoutAccountNumber() ?? '—' }}</span>
                                                <span class="tabular-nums font-medium">{{ $fmtSum($p->amount) }}</span>@if (! $loop->last)<span class="text-slate-400"> · </span>@endif
                                            @empty
                                                <span class="text-slate-400">—</span>
                                            @endforelse
                                        </td>
                                        <td class="whitespace-nowrap border border-slate-200 px-3 py-2.5 text-right tabular-nums font-medium text-slate-900">{{ $fmtSum($sale->total_amount) }}</td>
                                        <td class="whitespace-nowrap border border-slate-200 px-3 py-2.5 text-right tabular-nums">
                                            @if ((float) $sale->debt_amount > 0.004)
                                                <span class="font-semibold text-rose-700">{{ $fmtSum($sale->debt_amount) }}</span>
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap border border-slate-200 px-3 py-2.5 text-right text-slate-600">{{ $sale->lines->count() }}</td>
                                        <td class="border border-slate-200 px-3 py-2.5 text-center">
                                            <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1">
                                                <a
                                                    href="{{ route('admin.retail-sales.receipt', $sale) }}"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="text-sm font-semibold text-slate-700 hover:underline"
                                                >Чек</a>
                                                @if ($hasPaymentAccounts)
                                                    <button
                                                        type="button"
                                                        class="text-sm font-semibold text-amber-800 hover:underline"
                                                        @click="openReturn({{ (int) $sale->id }})"
                                                    >Возврат</button>
                                                @else
                                                    <span class="cursor-not-allowed text-sm font-semibold text-slate-400" title="Нет счетов — заведите счета у организации">Возврат</span>
                                                @endif
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
                                                    <input type="hidden" name="return_good_id" value="{{ ($filterGoodId ?? 0) > 0 ? (int) $filterGoodId : '' }}">
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

        @if ($warehouses->isNotEmpty())
            <div
                x-show="returnOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center"
                @click.self="closeReturnModal()"
            >
                <div
                    class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-xl"
                    @click.stop
                >
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Возврат по чеку</h2>
                            <p class="mt-1 text-xs text-slate-500" x-show="activeSaleId" x-text="'Чек № ' + activeSaleId"></p>
                        </div>
                        <button type="button" class="rounded-lg p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-800" @click="closeReturnModal()" aria-label="Закрыть">✕</button>
                    </div>
                    <div class="space-y-4 px-5 py-4">
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                            Укажите количество по строкам, счёт списания и дату — товар вернётся на склад этого чека, сумма уменьшит выбранный счёт (как выдача денег клиенту).
                        </div>
                        <template x-if="returnErr">
                            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900" x-text="returnErr"></div>
                        </template>
                        <div x-show="returnLoading" class="text-sm text-slate-600">Загрузка…</div>
                        <div class="grid gap-3 sm:grid-cols-2" x-show="!returnLoading && returnLines.length > 0">
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Дата возврата</label>
                                <input type="date" x-model="returnDocDate" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Счёт (списание денег клиенту)</label>
                                <select x-model="returnAccountId" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">— выберите —</option>
                                    <template x-for="a in returnAccounts" :key="a.id">
                                        <option :value="String(a.id)" x-text="a.organization + ' — ' + a.label"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                        <div class="overflow-x-auto border border-slate-200" x-show="!returnLoading && returnLines.length > 0">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-left text-[11px] font-semibold uppercase text-slate-600">
                                    <tr>
                                        <th class="px-3 py-2">Товар</th>
                                        <th class="px-3 py-2 text-right">Продано</th>
                                        <th class="px-3 py-2 text-right">Доступно</th>
                                        <th class="px-3 py-2 text-right">Цена</th>
                                        <th class="px-3 py-2 text-right">К возврату</th>
                                        <th class="px-3 py-2 text-right">Сумма</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="row in returnLines" :key="row.id">
                                        <tr class="border-t border-slate-100" x-show="parseNum(row.quantity_available) > 0">
                                            <td class="px-3 py-2">
                                                <div class="font-medium text-slate-900" x-text="row.article_code + ' · ' + row.name"></div>
                                                <div class="text-xs text-slate-500" x-text="row.unit"></div>
                                            </td>
                                            <td class="px-3 py-2 text-right tabular-nums" x-text="formatQty(row.quantity_sold)"></td>
                                            <td class="px-3 py-2 text-right tabular-nums text-emerald-800" x-text="formatQty(row.quantity_available)"></td>
                                            <td class="px-3 py-2 text-right tabular-nums" x-text="formatMoney(row.unit_price)"></td>
                                            <td class="px-3 py-2 text-right">
                                                <input
                                                    type="text"
                                                    inputmode="decimal"
                                                    class="w-24 rounded border border-slate-300 px-2 py-1 text-right tabular-nums text-sm"
                                                    x-model="qty[row.id]"
                                                    placeholder="0"
                                                    @focus="$event.target.select()"
                                                    @mouseup.prevent
                                                />
                                            </td>
                                            <td class="px-3 py-2 text-right tabular-nums font-medium" x-text="formatMoney(lineSubtotal(row))"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4" x-show="!returnLoading && returnLines.length > 0">
                            <div class="text-sm text-slate-700">
                                К возврату всего: <span class="tabular-nums font-semibold text-slate-900" x-text="formatMoney(totalRefund()) + ' сом'"></span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="closeReturnModal()">Отмена</button>
                                <button
                                    type="button"
                                    class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-700 disabled:opacity-50"
                                    :disabled="returnSubmitting"
                                    @click="submitReturn()"
                                >Провести возврат</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-admin-layout>
