<x-admin-layout pageTitle="Продажа физлицам (розница)" main-class="min-w-0 w-full bg-slate-100/80 px-3 py-5 sm:px-4 lg:px-6 xl:px-8 2xl:px-10">
    <div class="w-full min-w-0 max-w-full space-y-5">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 shadow-sm">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div
                id="retail-pos-errors"
                class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-4 text-sm text-amber-950 shadow-sm ring-1 ring-amber-900/10 sm:px-5"
                role="alert"
            >
                <p class="font-bold text-amber-950">Не удалось перейти к оплате — чек не изменился</p>
                <p class="mt-1 text-amber-900/95">Исправьте условия ниже или скорректируйте количество. Состав корзины восстановлен.</p>
                <ul class="mt-3 list-inside list-disc space-y-1.5 font-medium text-amber-950">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
            <script>
                requestAnimationFrame(function () {
                    document.getElementById('retail-pos-errors')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
            </script>
        @endif

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <span class="mt-1 h-10 w-1 shrink-0 rounded-full bg-gradient-to-b from-emerald-500 to-teal-600 shadow-sm" aria-hidden="true"></span>
                <div>
                    <p class="inline-block rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-widest text-emerald-800">Розница</p>
                    <h1 class="mt-1 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Быстрая продажа</h1>
                </div>
            </div>
            <form method="GET" action="{{ route('admin.retail-sales.index') }}" class="flex flex-wrap items-center justify-end gap-3 sm:justify-start">
                <label for="rsp_wh" class="text-sm font-medium text-slate-600">Склад</label>
                <select
                    id="rsp_wh"
                    name="warehouse_id"
                    class="min-w-[12rem] rounded-xl border border-emerald-200/80 bg-white py-2 pl-3 pr-8 text-sm font-semibold text-slate-900 shadow-sm ring-1 ring-emerald-900/5 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400/40"
                    onchange="this.form.submit()"
                >
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}" @selected((int) $w->id === (int) $selectedWarehouseId)>{{ $w->name }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        @if ($warehouses->isEmpty())
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-950">
                Сначала добавьте склад в настройках.
                <a href="{{ route('admin.warehouses.create') }}" class="ml-2 font-semibold text-emerald-800 underline">Создать склад</a>
            </div>
        @elseif ($paymentAccountsPayload === [])
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-950">
                Нет счетов и касс. Добавьте организацию и счета в разделе «Данные организации».
                <a href="{{ route('admin.organizations.index') }}" class="ml-2 font-semibold text-emerald-800 underline">Организации</a>
            </div>
        @else
            <script>
                window.__retailPosInit = {
                    goodsSearchUrl: @json($goodsSearchUrl),
                    warehouseId: {{ (int) $selectedWarehouseId }},
                    defaultWarehouseId: {{ $warehouses->isNotEmpty() ? (int) $warehouses->first()->id : 0 }},
                    editMode: false,
                    initialCart: @json($initialCart ?? []),
                    defaultDocumentDate: @json($defaultDocumentDate),
                    checkoutDraftUrl: @json($checkoutDraftUrl ?? ''),
                };
            </script>
            {{--
                Две колонки через sm:grid-cols-2 (а не lg:col-span в 12-кол. сетке):
                иначе ниже breakpoint колонки могут сжиматься странно в flex-контексте админки.
            --}}
            <div class="grid w-full min-w-0 max-w-full grid-cols-1 gap-5 sm:grid-cols-2 sm:items-start sm:gap-6" x-data="retailPosForm()">
                {{-- Поиск --}}
                {{-- overflow-visible: иначе absolute-выпадающий список обрезается overflow-hidden карточки --}}
                <div class="relative z-20 min-w-0">
                    <div class="min-w-0 rounded-2xl border border-emerald-900/10 bg-white shadow-lg shadow-emerald-900/10 ring-1 ring-emerald-900/[0.06]">
                        <div class="rounded-t-2xl border-b border-white/10 px-4 py-3 text-white shadow-md" style="background: linear-gradient(120deg, #059669 0%, #0d9488 50%, #0f766e 100%);">
                            <label for="pos_search" class="text-sm font-bold tracking-tight">Товар или услуга</label>
                        </div>
                        <div class="relative min-w-0 p-4">
                            <input
                                id="pos_search"
                                type="search"
                                x-model="query"
                                @input.debounce.300ms="search()"
                                @search="results = []; searchOpen = true"
                                @focus="searchOpen = true; if (query.trim().length >= 2) { search() }"
                                @keydown.escape="searchOpen = false"
                                autocomplete="off"
                                placeholder="Название, артикул или штрихкод…"
                                class="box-border w-full min-w-0 max-w-full rounded-xl border border-slate-200 bg-slate-50/80 py-3.5 pl-4 pr-10 text-base text-slate-900 placeholder:text-slate-400 shadow-inner focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/25 min-h-[3.25rem]"
                            />
                            <span class="pointer-events-none absolute right-7 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">⌕</span>

                            <div
                                x-cloak
                                x-show="searchOpen && (loading || results.length || (query.trim().length >= 2 && !loading))"
                                @click.outside="searchOpen = false"
                                class="absolute left-4 right-4 top-full z-50 mt-1 max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-white py-0.5 text-[13px] leading-snug shadow-xl"
                            >
                                <div x-show="loading" class="px-3 py-2 text-xs text-slate-500">Поиск…</div>
                                <div x-show="!loading && query.trim().length >= 2 && results.length === 0" class="px-3 py-2 text-xs text-slate-500">
                                    Ничего не найдено
                                </div>
                                <template x-for="row in results" :key="row.id">
                                    <button
                                        type="button"
                                        class="flex w-full flex-col items-start gap-0 border-b px-3 py-2 text-left transition"
                                        :class="goodsRowOutOfStock(row) ? 'border-red-100 bg-red-50 hover:bg-red-100/90' : 'border-slate-50 hover:bg-emerald-50/80'"
                                        @click="addProduct(row)"
                                    >
                                        <span class="font-medium leading-snug" :class="goodsRowOutOfStock(row) ? 'text-red-950' : 'text-slate-900'" x-text="row.name"></span>
                                        <span class="text-[11px] font-medium text-teal-700" x-show="row.is_service === true || row.is_service === 1">Услуга</span>
                                        <span
                                            class="text-[11px] leading-tight"
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
                                            class="text-[11px] leading-tight text-emerald-700"
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

                {{-- Чек --}}
                <div class="min-w-0">
                    <form
                        method="POST"
                        action="{{ route('admin.retail-sales.checkout-draft') }}"
                        class="min-w-0 overflow-hidden rounded-2xl border border-emerald-900/10 bg-white shadow-xl shadow-emerald-900/15 ring-1 ring-emerald-900/[0.05]"
                        @submit="handleCheckoutDraft($event)"
                    >
                        @csrf
                        <input type="hidden" name="warehouse_id" :value="warehouseId" />
                        {{-- Дата из сервера: без Alpine поле часто уходит пустым (прод, медленный JS, сбой инициализации). --}}
                        <input type="hidden" name="document_date" value="{{ $defaultDocumentDate }}" />

                        <div class="border-b border-emerald-900/20 px-4 py-4 text-white sm:px-6" style="background: linear-gradient(135deg, #047857 0%, #0d9488 45%, #0f766e 100%);">
                            <p class="text-sm font-bold tracking-tight">Чек</p>
                        </div>

                        <div class="min-h-[11rem] divide-y divide-slate-100/90">
                            <template x-if="cart.length === 0">
                                <div class="px-5 py-12 text-center text-sm text-slate-500">
                                    Чек пуст. Выберите товар слева.
                                </div>
                            </template>
                            <template x-for="(line, idx) in cart" :key="line.good_id">
                                <div
                                    class="flex flex-col gap-3 px-4 py-3.5 transition-colors sm:flex-row sm:items-center sm:gap-4 sm:px-5"
                                    :class="cartLineStockDanger(line) ? 'bg-red-50/70 hover:bg-red-50/85' : 'hover:bg-slate-50/90'"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="text-[13px] font-semibold leading-snug"
                                            :class="cartLineStockDanger(line) ? 'text-red-950' : 'text-slate-900'"
                                            x-text="line.name"
                                        ></p>
                                        <input type="hidden" :name="`lines[${idx}][article_code]`" :value="line.article_code" />
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                        <div class="flex items-center overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
                                            <button type="button" class="px-3.5 py-2.5 text-lg font-medium text-slate-500 transition hover:bg-slate-50 hover:text-slate-800" @click="decQty(idx)">−</button>
                                            <input
                                                type="text"
                                                :name="`lines[${idx}][quantity]`"
                                                x-model="line.quantity"
                                                class="w-14 border-x border-slate-200/90 bg-slate-50/50 py-2.5 text-center text-sm font-bold tabular-nums text-slate-900 focus:bg-white focus:outline-none"
                                                inputmode="decimal"
                                            />
                                            <button type="button" class="px-3.5 py-2.5 text-lg font-medium text-slate-500 transition hover:bg-slate-50 hover:text-slate-800" @click="incQty(idx)">+</button>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-sm text-slate-400">×</span>
                                            <input
                                                type="text"
                                                :name="`lines[${idx}][unit_price]`"
                                                x-model="line.unit_price"
                                                class="w-24 rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-right text-sm font-semibold tabular-nums shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20"
                                                inputmode="decimal"
                                            />
                                        </div>
                                        <span class="min-w-[4.75rem] text-right text-sm font-bold tabular-nums text-slate-900" x-text="lineSumFormatted(line)"></span>
                                        <button type="button" class="rounded-xl p-2 text-slate-400 transition hover:bg-red-50 hover:text-red-600" title="Удалить" @click="removeLine(idx)">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div
                            class="flex flex-wrap items-center justify-between gap-3 border-t-2 border-emerald-500/80 bg-emerald-50/90 px-4 py-4 sm:px-6"
                            x-cloak
                            x-show="cart.length > 0"
                        >
                            <div class="flex min-w-0 flex-1 flex-wrap items-center" style="gap: 10px 24px;">
                                <span class="inline-flex flex-wrap items-baseline" style="gap: 8px;">
                                    <span class="text-xs font-semibold lowercase tracking-wide text-emerald-800/80">итого</span>
                                    <span class="text-xl font-extrabold tabular-nums tracking-tight text-emerald-950" x-text="cartTotalFormatted"></span>
                                    <span class="text-sm font-semibold tabular-nums text-emerald-800">сом</span>
                                </span>
                            </div>
                            <p class="shrink-0 rounded-md bg-white/80 px-2.5 py-1 text-xs font-medium text-slate-600 shadow-sm ring-1 ring-emerald-900/10">
                                Позиций:&nbsp;<span class="font-extrabold tabular-nums text-emerald-900" x-text="cart.length"></span>
                            </p>
                        </div>

                        <div
                            class="border-t border-dashed border-slate-200 bg-slate-50/80 px-4 py-4 sm:px-6"
                            x-cloak
                            x-show="clientQueue.length > 0"
                        >
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-600">Ожидают</p>
                            <ul class="mt-2 space-y-2">
                                <template x-for="(slot, qIdx) in clientQueue" :key="slot.id">
                                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-slate-200/80">
                                        <span class="font-medium text-slate-800" x-text="slot.label"></span>
                                        <span class="text-xs text-slate-500"><span x-text="slot.lines"></span> поз.</span>
                                        <button type="button" class="font-semibold text-emerald-700 hover:underline" @click="resumeQueue(qIdx)">Продолжить</button>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-emerald-200/60 bg-gradient-to-b from-white to-emerald-50/30 px-4 py-5 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:px-6">
                            <button
                                type="button"
                                class="inline-flex w-full items-center justify-center rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-950 shadow-sm transition hover:bg-amber-100 sm:w-auto"
                                :class="{ 'opacity-50': cart.length === 0 }"
                                :disabled="cart.length === 0"
                                @click="holdCurrentClient()"
                            >
                                В очередь (первый ждёт)
                            </button>
                            <button
                                type="submit"
                                class="retail-pos-submit inline-flex w-full items-center justify-center rounded-xl px-8 py-3.5 text-[15px] font-bold tracking-wide shadow-lg transition focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 sm:ml-auto sm:w-auto sm:min-w-[15rem] disabled:opacity-50"
                                :class="{ 'opacity-50': cart.length === 0 }"
                                :disabled="cart.length === 0"
                            >
                                К оплате
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-admin-layout>
