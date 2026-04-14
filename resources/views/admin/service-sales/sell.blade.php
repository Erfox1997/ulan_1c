<x-admin-layout pageTitle="Заявка на продажу" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-6xl space-y-3">
        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->has('lines'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">
                {{ $errors->first('lines') }}
            </div>
        @endif
        @if ($errors->has('recipient_kind'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">
                {{ $errors->first('recipient_kind') }}
            </div>
        @endif

        @if ($warehouses->isEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                Сначала добавьте склад в настройках.
                <a href="{{ route('admin.warehouses.create') }}" class="ml-2 font-semibold text-emerald-800 underline">Создать склад</a>
            </div>
        @else
            <script>
                window.__retailPosInit = {
                    goodsSearchUrl: @json($goodsSearchUrl),
                    warehouseId: {{ (int) $selectedWarehouseId }},
                    editMode: false,
                    initialCart: [],
                    serviceRequestMode: true,
                };
            </script>
            <style>
                /* Видимые радио + явная подсветка выбранного (:has поддерживается в современных браузерах) */
                .recipient-kind-sell label {
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                    border-radius: 0.5rem;
                    border: 2px solid rgba(255, 255, 255, 0.4);
                    background: rgba(255, 255, 255, 0.14);
                    padding: 0.45rem 0.75rem;
                    font-size: 0.875rem;
                    font-weight: 700;
                    color: #fff;
                    cursor: pointer;
                    user-select: none;
                    transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
                }
                .recipient-kind-sell label:hover {
                    background: rgba(255, 255, 255, 0.22);
                }
                .recipient-kind-sell label:has(input:checked) {
                    border-color: #fbbf24;
                    background: #fbbf24;
                    color: #065f46;
                    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
                }
                .recipient-kind-sell input[type='radio'] {
                    width: 1.125rem;
                    height: 1.125rem;
                    flex-shrink: 0;
                    accent-color: #d97706;
                    cursor: pointer;
                }
            </style>
            <div class="grid gap-3 lg:grid-cols-12 lg:items-start" x-data="retailPosForm()">
                {{-- Поиск + склад (overflow-visible — иначе список подсказок обрезается) --}}
                <div class="relative z-[100] lg:col-span-5">
                    <div class="rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
                        <div class="space-y-2 p-3">
                            <form method="GET" action="{{ route('admin.service-sales.sell') }}" class="flex items-center gap-2">
                                <label for="svc_sell_wh" class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-slate-500">Склад</label>
                                <select
                                    id="svc_sell_wh"
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
                                    id="svc_pos_search"
                                    type="search"
                                    x-model="query"
                                    @input.debounce.300ms="search()"
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
                                            class="flex w-full flex-col items-start gap-0.5 border-b border-slate-50 px-3 py-2 text-left text-sm transition hover:bg-emerald-50/80"
                                            @click="addProduct(row)"
                                        >
                                            <span class="font-medium text-slate-900" x-text="row.name"></span>
                                            <span class="text-xs font-medium text-teal-700" x-show="row.is_service === true || row.is_service === 1">Услуга</span>
                                            <span class="text-xs text-slate-600" x-show="row.stock_quantity != null && row.stock_quantity !== ''">
                                                Остаток: <span class="font-mono" x-text="row.stock_quantity"></span>
                                                <span x-show="row.sale_price != null && row.sale_price !== ''" class="text-emerald-700">
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

                {{-- Заявка --}}
                <div class="min-w-0 lg:col-span-7">
                    <form
                        method="POST"
                        action="{{ route('admin.service-sales.request.store') }}"
                        class="relative z-10 overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]"
                        @submit="handleSubmit($event)"
                    >
                        @csrf
                        <input type="hidden" name="warehouse_id" value="{{ (int) $selectedWarehouseId }}" />

                        <div class="border-b border-emerald-900/15 px-3 py-2.5 text-white sm:px-4" style="background: linear-gradient(125deg, #047857 0%, #0d9488 55%, #115e59 100%);">
                            <div class="flex flex-col gap-2.5 sm:flex-row sm:flex-wrap sm:items-end sm:gap-x-4 sm:gap-y-2">
                                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2 sm:gap-3">
                                    <div class="recipient-kind-sell flex flex-wrap gap-2" role="group" aria-label="Тип получателя">
                                        <label>
                                            <input
                                                type="radio"
                                                name="recipient_kind"
                                                value="physical"
                                                @checked(old('recipient_kind', 'physical') === 'physical')
                                            />
                                            Физлицо
                                        </label>
                                        <label>
                                            <input
                                                type="radio"
                                                name="recipient_kind"
                                                value="legal"
                                                @checked(old('recipient_kind') === 'legal')
                                            />
                                            Юрлицо
                                        </label>
                                    </div>
                                </div>
                                <div class="w-full sm:w-auto sm:min-w-[10.5rem]">
                                    <input
                                        id="svc_req_date"
                                        type="date"
                                        name="document_date"
                                        value="{{ old('document_date', $defaultDocumentDate) }}"
                                        aria-label="Дата заявки"
                                        class="w-full rounded-lg border-0 bg-white px-2.5 py-1.5 text-sm font-semibold text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-white/80"
                                    />
                                </div>
                            </div>
                            <div class="mt-2">
                                <label for="svc_req_notes" class="block text-[10px] font-bold uppercase tracking-wide text-teal-100/95">Комментарий</label>
                                <textarea
                                    id="svc_req_notes"
                                    name="notes"
                                    rows="2"
                                    class="mt-1 w-full resize-y rounded-lg border-0 bg-white/95 px-2.5 py-1.5 text-sm leading-snug text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-white/80"
                                    placeholder="Необязательно"
                                >{{ old('notes') }}</textarea>
                            </div>
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
                                            <th class="whitespace-nowrap px-2 py-1.5 text-center">Кол-во</th>
                                            <th class="w-8 px-0 py-1.5 text-center font-normal text-slate-400"></th>
                                            <th class="w-[5.5rem] whitespace-nowrap px-1.5 py-1.5 text-right">Цена</th>
                                            <th class="whitespace-nowrap px-2 py-1.5 text-right">Сумма</th>
                                            <th class="w-9 px-1 py-1.5"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="(line, idx) in cart" :key="line.good_id">
                                            <tr class="align-middle text-slate-800">
                                                <td class="max-w-0 px-3 py-2">
                                                    <p class="truncate font-semibold text-slate-900" x-text="line.name" :title="line.name"></p>
                                                    <input type="hidden" :name="`lines[${idx}][article_code]`" :value="line.article_code" />
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
                                Отправить заявку
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if ($recentPending->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-3 py-2">
                    <h2 class="text-xs font-bold uppercase tracking-wide text-slate-600">В очереди на оформление</h2>
                    <a href="{{ route('admin.service-sales.requests') }}" class="text-[11px] font-semibold text-emerald-700 hover:underline">Все заявки</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="border-b border-slate-100 bg-white text-left font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="whitespace-nowrap px-2.5 py-1.5">№</th>
                                <th class="whitespace-nowrap px-2 py-1.5">Кому</th>
                                <th class="whitespace-nowrap px-2 py-1.5">Дата</th>
                                <th class="whitespace-nowrap px-2 py-1.5">Статус</th>
                                <th class="whitespace-nowrap px-2 py-1.5 text-right">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach ($recentPending as $row)
                                <tr class="text-slate-800">
                                    <td class="whitespace-nowrap px-2.5 py-1.5 font-mono text-slate-600">{{ $row->id }}</td>
                                    <td class="max-w-[8rem] truncate px-2 py-1.5">{{ $row->recipientKindLabel() }}</td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-slate-600">{{ $row->document_date?->format('d.m.Y') ?? '—' }}</td>
                                    <td class="px-2 py-1.5">
                                        @if ($row->status === \App\Models\ServiceOrder::STATUS_FULFILLED)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-900 ring-1 ring-emerald-200/80">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                                Оформлена
                                            </span>
                                        @elseif ($row->status === \App\Models\ServiceOrder::STATUS_CANCELLED)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700 ring-1 ring-slate-200/80">
                                                Отменена
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-950 ring-1 ring-amber-200/80">
                                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                                                Ждёт
                                            </span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-right">
                                        <a
                                            href="{{ route('admin.service-sales.requests.edit', $row) }}"
                                            class="font-semibold text-slate-700 underline decoration-slate-400 underline-offset-2 hover:text-slate-900"
                                        >Изменить</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-admin-layout>
