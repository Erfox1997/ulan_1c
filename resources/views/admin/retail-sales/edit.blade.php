@php
    $returnQuery = array_filter([
        'warehouse_id' => $retailSale->warehouse_id > 0 ? $retailSale->warehouse_id : null,
        'limit' => request()->integer('limit') ?: null,
        'date_from' => request()->input('date_from'),
        'date_to' => request()->input('date_to'),
    ], static fn ($v) => $v !== null && $v !== '');
    $historyUrl = route('admin.retail-sales.history', $returnQuery);
@endphp
<x-admin-layout pageTitle="Правка розничной продажи" main-class="bg-slate-100/80 px-3 py-5 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-6xl space-y-5">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 shadow-sm">
                {{ session('status') }}
            </div>
        @endif

        <div>
            <a href="{{ $historyUrl }}" class="text-sm font-semibold text-emerald-800 hover:underline">← К истории продаж</a>
            <h1 class="mt-2 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Изменить продажу</h1>
            <p class="mt-1 text-sm text-slate-600">
                Склад: <span class="font-semibold text-slate-800">{{ $retailSale->warehouse->name ?? '—' }}</span>
                · документ № <span class="font-mono">{{ $retailSale->id }}</span>
            </p>
        </div>

        @if ($errors->has('lines'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                {{ $errors->first('lines') }}
            </div>
        @endif

        <script>
            window.__retailPosInit = {
                goodsSearchUrl: @json($goodsSearchUrl),
                warehouseId: {{ (int) $retailSale->warehouse_id }},
                editMode: true,
                initialCart: @json($linesForJs),
            };
        </script>

        <div class="grid gap-5 lg:grid-cols-12 lg:items-start" x-data="retailPosForm()">
            <div class="relative z-20 lg:col-span-5">
                <div class="rounded-2xl border border-emerald-900/10 bg-white shadow-lg shadow-emerald-900/10 ring-1 ring-emerald-900/[0.06]">
                    <div class="rounded-t-2xl border-b border-white/10 px-4 py-3 text-white shadow-md" style="background: linear-gradient(120deg, #059669 0%, #0d9488 50%, #0f766e 100%);">
                        <label for="pos_edit_search" class="text-sm font-bold tracking-tight">Товар или услуга</label>
                        <p class="mt-0.5 text-xs font-medium text-emerald-50/90">Один поиск: товары и услуги. Услуги со склада не списываются.</p>
                    </div>
                    <div class="relative p-4">
                        <input
                            id="pos_edit_search"
                            type="search"
                            x-model="query"
                            @input.debounce.300ms="search()"
                            @focus="searchOpen = true; if (query.trim().length >= 2) { search() }"
                            @keydown.escape="searchOpen = false"
                            autocomplete="off"
                            placeholder="Начните вводить…"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/80 py-3 pl-4 pr-10 text-base text-slate-900 placeholder:text-slate-400 shadow-inner focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/25"
                        />
                        <span class="pointer-events-none absolute right-7 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">⌕</span>

                        <div
                            x-cloak
                            x-show="searchOpen && (loading || results.length || (query.trim().length >= 2 && !loading))"
                            @click.outside="searchOpen = false"
                            class="absolute left-4 right-4 top-full z-50 mt-1 max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-xl"
                        >
                            <div x-show="loading" class="px-4 py-3 text-sm text-slate-500">Поиск…</div>
                            <div x-show="!loading && query.trim().length >= 2 && results.length === 0" class="px-4 py-3 text-sm text-slate-500">
                                Ничего не найдено
                            </div>
                            <template x-for="row in results" :key="row.id">
                                <button
                                    type="button"
                                    class="flex w-full flex-col items-start gap-0.5 border-b border-slate-50 px-4 py-2.5 text-left transition hover:bg-emerald-50/80"
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

            <div class="lg:col-span-7">
                <form
                    method="POST"
                    action="{{ route('admin.retail-sales.update', $retailSale) }}"
                    class="overflow-hidden rounded-2xl border border-emerald-900/10 bg-white shadow-xl shadow-emerald-900/15 ring-1 ring-emerald-900/[0.05]"
                    @submit="handleSubmit($event)"
                >
                    @csrf
                    @method('PUT')

                    <div class="border-b border-emerald-900/20 px-4 py-5 text-white sm:px-6" style="background: linear-gradient(135deg, #047857 0%, #0d9488 45%, #0f766e 100%);">
                        <div class="grid gap-4 sm:grid-cols-2 sm:items-end sm:gap-6">
                            <div>
                                <label for="pos_edit_account" class="block text-[10px] font-bold uppercase tracking-[0.12em] text-teal-100/95">Счёт / касса *</label>
                                <select
                                    id="pos_edit_account"
                                    name="organization_bank_account_id"
                                    class="mt-2 w-full rounded-xl border-0 bg-white py-2.5 pl-3 pr-8 text-sm font-semibold text-slate-900 shadow-md shadow-teal-950/10 focus:outline-none focus:ring-2 focus:ring-white/90"
                                    required
                                >
                                    @foreach ($paymentAccountsPayload as $acc)
                                        <option value="{{ $acc['id'] }}" @selected((int) old('organization_bank_account_id', $retailSale->organization_bank_account_id) === (int) $acc['id'])>
                                            {{ $acc['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="pos_edit_date" class="block text-[10px] font-bold uppercase tracking-[0.12em] text-teal-100/95">Дата документа *</label>
                                <input
                                    id="pos_edit_date"
                                    type="date"
                                    name="document_date"
                                    value="{{ old('document_date', $retailSale->document_date->format('Y-m-d')) }}"
                                    required
                                    class="mt-2 w-full rounded-xl border-0 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 shadow-md shadow-teal-950/10 focus:outline-none focus:ring-2 focus:ring-white/90"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="min-h-[11rem] divide-y divide-slate-100/90">
                        <template x-if="cart.length === 0">
                            <div class="px-5 py-12 text-center text-sm text-slate-500">
                                Чек пуст. Добавьте позиции слева.
                            </div>
                        </template>
                        <template x-for="(line, idx) in cart" :key="`${idx}-${line.good_id}-${line.article_code}`">
                            <div class="flex flex-col gap-3 px-4 py-3.5 transition-colors hover:bg-slate-50/90 sm:flex-row sm:items-center sm:gap-4 sm:px-5">
                                <div class="min-w-0 flex-1">
                                    <p class="text-[15px] font-semibold leading-snug text-slate-900" x-text="line.name"></p>
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

                    <div class="flex flex-col gap-4 border-t border-emerald-200/60 bg-gradient-to-b from-white to-emerald-50/30 px-4 py-5 sm:flex-row sm:items-center sm:justify-end sm:px-6">
                        <button
                            type="submit"
                            class="retail-pos-submit inline-flex w-full items-center justify-center rounded-xl px-8 py-3.5 text-[15px] font-bold tracking-wide shadow-lg transition focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 sm:w-auto sm:min-w-[15rem] disabled:opacity-50"
                            :class="{ 'opacity-50': cart.length === 0 }"
                            :disabled="cart.length === 0"
                        >
                            Сохранить изменения
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
