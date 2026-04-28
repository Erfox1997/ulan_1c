<x-admin-layout pageTitle="Заявка №{{ $serviceOrder->id }} — позиции" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-6xl space-y-3">
        @php
            $linesIndexRoute = $linesIndexRoute ?? 'admin.service-sales.sell.lines';
            $linesStoreRoute = $linesStoreRoute ?? 'admin.service-sales.sell.lines.store';
            $linesFromRequests = $linesFromRequests ?? false;
        @endphp
        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
            <div class="flex flex-wrap items-center gap-2">
                @if ($linesFromRequests)
                    <a href="{{ route('admin.service-sales.requests') }}" class="font-semibold text-emerald-700 hover:underline">← К заявкам</a>
                @else
                    <a href="{{ route('admin.service-sales.sell') }}" class="font-semibold text-emerald-700 hover:underline">← Заявка на продажу</a>
                @endif
                @if ($mayAccessRoute('admin.service-sales.requests.edit'))
                    <span class="text-slate-300" aria-hidden="true">·</span>
                    <a href="{{ route('admin.service-sales.requests.edit', $serviceOrder) }}" class="font-semibold text-slate-600 hover:text-emerald-700 hover:underline">Шапка заявки</a>
                @endif
            </div>
            @if ($mayAccessRoute('admin.service-sales.requests.show') && $serviceOrder->isAwaitingFulfillment())
                <a
                    href="{{ route('admin.service-sales.requests.show', $serviceOrder) }}"
                    class="text-xs font-semibold text-slate-600 hover:text-emerald-800 hover:underline"
                >К оформлению</a>
            @endif
        </div>

        @if ($serviceOrder->status === \App\Models\ServiceOrder::STATUS_FULFILLED)
            <div class="rounded-lg border border-teal-200/90 bg-teal-50/90 px-3 py-2 text-xs font-medium leading-snug text-teal-950">
                Заявка уже оформлена — изменения позиций переписывают связанный документ продажи и пересчитывают складские списания.
            </div>
        @endif

        @php
            $cp = $serviceOrder->counterparty;
            $veh = $serviceOrder->customerVehicle;
            $cpLabel = $cp ? (string) ($cp->full_name ?: $cp->name) : '—';
            $vehLabel = $veh ? $veh->label() : '—';
        @endphp
        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 shadow-sm">
            <span class="font-semibold text-slate-900">№ {{ $serviceOrder->id }}</span>
            <span class="text-slate-300" aria-hidden="true"> · </span>
            <span>{{ $cpLabel }}</span>
            @if ($serviceOrder->contact_name)
                <span class="text-slate-300" aria-hidden="true"> · </span>
                <span class="text-slate-600">{{ $serviceOrder->contact_name }}</span>
            @endif
            <span class="text-slate-300" aria-hidden="true"> · </span>
            <span class="text-slate-600">{{ $vehLabel }}</span>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <script>
            window.__retailPosInit = {
                goodsSearchUrl: @json($goodsSearchUrl),
                counterpartySearchUrl: '',
                initialCounterparty: null,
                warehouseId: {{ (int) $selectedWarehouseId }},
                defaultWarehouseId: {{ $warehouses->isNotEmpty() ? (int) $warehouses->first()->id : 0 }},
                editMode: false,
                initialCart: @json($initialCart),
                serviceRequestMode: true,
                linesPageMode: true,
            };
        </script>
        <div class="grid gap-3 lg:grid-cols-12 lg:items-start" x-data="retailPosForm()">
            <div class="relative z-[100] lg:col-span-5">
                <div class="rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
                    <div class="space-y-2 p-3">
                        <form method="GET" action="{{ route($linesIndexRoute, $serviceOrder) }}" class="flex items-center gap-2">
                            <label for="svc_lines_wh" class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-slate-500">Склад (остатки)</label>
                            <select
                                id="svc_lines_wh"
                                name="warehouse_id"
                                class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-slate-50 py-1.5 pl-2 pr-8 text-sm font-medium text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500/30"
                                onchange="this.form.submit()"
                            >
                                @foreach ($warehouses as $w)
                                    <option value="{{ $w->id }}" @selected((int) $w->id === (int) $selectedWarehouseId)>{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </form>
                        <div class="relative">
                            <input
                                id="svc_lines_search"
                                type="search"
                                x-model="query"
                                @input.debounce.300ms="search()"
                                @search="results = []; searchOpen = true"
                                @focus="searchOpen = true; if (query.trim().length >= 2) { search() }"
                                @keydown.escape="searchOpen = false"
                                autocomplete="off"
                                placeholder="Наименование, артикул…"
                                class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-3 pr-9 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                            />
                            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">⌕</span>

                            <div
                                x-cloak
                                x-show="searchOpen && (loading || results.length || (query.trim().length >= 2 && !loading))"
                                @click.outside="searchOpen = false"
                                class="absolute left-0 right-0 top-full z-[200] mt-1 max-h-64 overflow-y-auto rounded-lg border border-slate-300 bg-white py-0.5 shadow-2xl ring-1 ring-slate-900/10"
                            >
                                <div x-show="loading" class="px-3 py-2 text-xs text-slate-500">Поиск…</div>
                                <div x-show="!loading && query.trim().length >= 2 && results.length === 0" class="px-3 py-2 text-xs text-slate-500">
                                    Ничего не найдено
                                </div>
                                <template x-for="row in results" :key="row.id">
                                    <button
                                        type="button"
                                        class="flex w-full flex-col items-start gap-0.5 border-b px-3 py-2 text-left text-sm transition"
                                        :class="goodsRowOutOfStock(row) ? 'border-red-100 bg-red-50 hover:bg-red-100/90' : 'border-slate-50 hover:bg-emerald-50/80'"
                                        @click="addProduct(row)"
                                    >
                                        <span class="font-medium" :class="goodsRowOutOfStock(row) ? 'text-red-950' : 'text-slate-900'" x-text="row.name"></span>
                                        <span class="text-xs font-medium text-teal-700" x-show="row.is_service === true || row.is_service === 1">Услуга</span>
                                        <span
                                            class="text-xs"
                                            x-show="warehouseId > 0 && !(row.is_service === true || row.is_service === 1)"
                                            :class="goodsRowOutOfStock(row) ? 'font-medium text-red-700' : 'text-slate-600'"
                                        >
                                            Остаток: <span class="font-mono tabular-nums" x-text="goodsRowStockDisplay(row)"></span>
                                            <span
                                                x-show="!goodsRowOutOfStock(row) && row.sale_price != null && row.sale_price !== ''"
                                                class="text-emerald-700"
                                            >
                                                · <span x-text="row.sale_price"></span> сом
                                            </span>
                                        </span>
                                        <span
                                            class="text-xs text-emerald-700"
                                            x-show="(row.is_service === true || row.is_service === 1) && (row.stock_quantity == null || row.stock_quantity === '') && row.sale_price != null && row.sale_price !== ''"
                                        >
                                            <span x-text="row.sale_price"></span> сом
                                        </span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="min-w-0 lg:col-span-7">
                <form
                    method="POST"
                    action="{{ route($linesStoreRoute, $serviceOrder) }}"
                    class="relative z-10 overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]"
                    @submit="handleSubmit($event)"
                >
                    @csrf
                    <div class="border-b border-emerald-900/15 px-3 py-2.5 text-white sm:px-4" style="background: linear-gradient(125deg, #047857 0%, #0d9488 55%, #115e59 100%);">
                        <h2 class="text-sm font-bold tracking-tight">Запчасти и услуги</h2>
                        <p class="mt-0.5 text-[11px] font-medium text-teal-100/90">Укажите количество и цены. Сумма должна быть больше нуля.</p>
                    </div>

                    <div class="min-h-[6rem]">
                        <template x-if="cart.length === 0">
                            <div class="px-3 py-6 text-center text-xs text-slate-500">
                                Добавьте позиции через поиск слева.
                            </div>
                        </template>
                        <div x-show="cart.length > 0" x-cloak class="overflow-x-auto">
                            <table class="w-full min-w-[20rem] text-left text-xs">
                                <thead class="border-b border-slate-200 bg-slate-50/90 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-3 py-1.5">Наименование</th>
                                        <th class="min-w-[7rem] whitespace-nowrap px-2 py-1.5">Мастер (услуга)</th>
                                        <th class="whitespace-nowrap px-2 py-1.5 text-center">Кол-во</th>
                                        <th class="w-8 px-0 py-1.5 text-center font-normal text-slate-400"></th>
                                        <th class="w-[5.5rem] whitespace-nowrap px-1.5 py-1.5 text-right">Цена</th>
                                        <th class="whitespace-nowrap px-2 py-1.5 text-right">Сумма</th>
                                        <th class="w-9 px-1 py-1.5"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template
                                        x-for="(line, idx) in cart"
                                        :key="(line.line_id != null && line.line_id !== '' ? 'id-' + line.line_id : 'i-' + idx) + '-' + (line.article_code || '')"
                                    >
                                        <tr class="align-middle text-slate-800" :class="cartLineStockDanger(line) ? 'bg-red-50/80' : ''">
                                            <td class="max-w-0 px-3 py-2">
                                                <p
                                                    class="truncate font-semibold"
                                                    :class="cartLineStockDanger(line) ? 'text-red-950' : 'text-slate-900'"
                                                    x-text="line.name"
                                                    :title="line.name"
                                                ></p>
                                                <input type="hidden" :name="`lines[${idx}][article_code]`" :value="line.article_code" />
                                            </td>
                                            <td class="px-2 py-2 align-top">
                                                <template x-if="line.is_service === true || line.is_service === 1">
                                                    <select
                                                        x-model="line.performer_employee_id"
                                                        :name="`lines[${idx}][performer_employee_id]`"
                                                        class="w-full min-w-[6.5rem] max-w-[10rem] rounded-md border border-slate-200 bg-white px-1 py-1 text-[11px] text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500/30"
                                                    >
                                                        <option value="">— мастер —</option>
                                                        @foreach ($masters as $m)
                                                            <option value="{{ $m->id }}">{{ $m->full_name }}</option>
                                                        @endforeach
                                                    </select>
                                                </template>
                                                <template x-if="!(line.is_service === true || line.is_service === 1)">
                                                    <span class="text-slate-400">—</span>
                                                </template>
                                            </td>
                                            <td class="px-2 py-2">
                                                <div class="inline-flex items-center overflow-hidden rounded-md border border-slate-200 bg-white">
                                                    <button type="button" class="px-2 py-1 text-sm font-medium text-slate-500 hover:bg-slate-50" @click="decQty(idx)">−</button>
                                                    <input
                                                        type="text"
                                                        :name="`lines[${idx}][quantity]`"
                                                        x-model="line.quantity"
                                                        class="w-11 border-x border-slate-200 bg-slate-50/70 py-1 text-center text-xs font-bold tabular-nums text-slate-900 focus:bg-white focus:outline-none"
                                                        inputmode="decimal"
                                                    />
                                                    <button type="button" class="px-2 py-1 text-sm font-medium text-slate-500 hover:bg-slate-50" @click="incQty(idx)">+</button>
                                                </div>
                                            </td>
                                            <td class="px-0 py-2 text-center text-slate-400">×</td>
                                            <td class="px-1.5 py-2 text-right">
                                                <input
                                                    type="text"
                                                    :name="`lines[${idx}][unit_price]`"
                                                    x-model="line.unit_price"
                                                    class="w-[5rem] max-w-full rounded-md border border-slate-200 bg-white px-1 py-0.5 text-right text-xs font-semibold tabular-nums focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500/30"
                                                    inputmode="decimal"
                                                />
                                            </td>
                                            <td class="whitespace-nowrap px-2 py-2 text-right font-bold tabular-nums text-slate-900" x-text="lineSumFormatted(line)"></td>
                                            <td class="px-1 py-2 text-center">
                                                <button type="button" class="rounded-md p-1 text-slate-400 hover:bg-red-50 hover:text-red-600" title="Удалить" @click="removeLine(idx)">
                                                    <svg class="mx-auto h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div
                        class="flex flex-col gap-2 border-t border-emerald-200/70 bg-emerald-50/70 px-3 py-2 sm:flex-row sm:items-center sm:justify-between sm:px-4"
                        x-cloak
                        x-show="cart.length > 0"
                    >
                        <div class="flex flex-wrap items-baseline gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-emerald-800/70">Итого</span>
                            <span class="text-lg font-extrabold tabular-nums text-emerald-950" x-text="cartTotalFormatted"></span>
                            <span class="text-xs font-semibold text-emerald-800">сом</span>
                        </div>
                        <button
                            type="submit"
                            class="inline-flex w-full shrink-0 items-center justify-center rounded-lg bg-gradient-to-r from-emerald-600 to-teal-600 px-5 py-2.5 text-sm font-bold text-white shadow-md transition hover:from-emerald-500 hover:to-teal-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 sm:w-auto sm:min-w-[11rem] disabled:opacity-50"
                            :class="{ 'opacity-50': cart.length === 0 }"
                            :disabled="cart.length === 0"
                        >
                            Сохранить заявку
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
