@php
    $fmt = static function ($v): string {
        if ($v === null) {
            return '—';
        }

        return number_format((float) $v, 2, ',', ' ');
    };
@endphp
<x-admin-layout pageTitle="Товары" main-class="px-3 py-6 sm:px-6 lg:px-8">
    @include('admin.partials.cp-brush')
    <style>
        /* Товары: мягкий акцент (изумруд), без «радуги» */
        .sg-goods-page .cp-table thead th {
            background: linear-gradient(180deg, #ecfdf5 0%, #d1fae5 100%);
            color: #065f46;
            border-color: #a7f3d0;
            font-weight: 700;
        }
        .sg-goods-page .cp-table th,
        .sg-goods-page .cp-table td {
            border-color: #d1fae5;
        }
        .sg-goods-page .cp-table tbody tr:hover {
            background: #ecfdf5;
        }
    </style>
    <div class="cp-root sg-goods-page mx-auto w-full max-w-7xl space-y-5">
        @if (session('status'))
            <div
                class="flex items-start gap-3 rounded-2xl border border-emerald-200/90 bg-gradient-to-r from-emerald-50 to-teal-50/90 px-4 py-3 text-[13px] text-emerald-950 shadow-sm"
                role="status"
            >
                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-700" aria-hidden="true">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($errors->has('delete'))
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 shadow-sm">
                {{ $errors->first('delete') }}
            </div>
        @endif

        @if (session('import_errors'))
            <div
                class="rounded-2xl border border-amber-200/90 bg-gradient-to-r from-amber-50 to-white px-4 py-3 text-sm text-amber-950 shadow-sm ring-1 ring-amber-100/60"
            >
                <p class="font-semibold">Замечания при импорте:</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach (session('import_errors') as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <script>
            window.__saleGoodsInit = @json($goodsSearchConfig ?? []);
            @if (($viewMode ?? '') === 'goods' && isset($goodModalConfig) && is_array($goodModalConfig))
            window.__saleGoodModalCfg = @json($goodModalConfig);
            @endif
        </script>

        <div class="overflow-hidden rounded-2xl border border-emerald-200/80 bg-white shadow-[0_10px_40px_-12px_rgba(5,150,105,0.12)] ring-1 ring-emerald-100/50">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-emerald-100/90 bg-gradient-to-r from-emerald-50/90 via-white to-slate-50/80 px-4 py-4 sm:px-5">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-md shadow-emerald-500/25" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="mb-0.5 text-[10px] font-semibold uppercase tracking-wider text-emerald-800/85">Справочник</p>
                        <h2 class="truncate text-[15px] font-bold leading-tight text-slate-900">
                            @if (($viewMode ?? '') === 'categories')
                                Категории товаров
                            @else
                                Товары
                            @endif
                        </h2>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form
                        method="POST"
                        action="{{ route('admin.sale-goods.import') }}"
                        enctype="multipart/form-data"
                        class="flex flex-wrap items-center gap-2"
                    >
                        @csrf
                        <input
                            type="file"
                            name="file"
                            id="sale_goods_import_file"
                            class="hidden"
                            accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
                            onchange="if (this.files.length) this.form.requestSubmit()"
                        />
                        <button
                            type="button"
                            class="cp-btn border-emerald-200/90 bg-gradient-to-b from-white to-emerald-50/90 text-[11px] font-semibold text-slate-800 shadow-sm hover:to-emerald-50"
                            onclick="document.getElementById('sale_goods_import_file').click()"
                        >
                            Excel…
                        </button>
                    </form>
                    <a
                        href="{{ route('admin.sale-goods.sample-import') }}"
                        class="cp-btn border-sky-200 bg-gradient-to-b from-sky-50 to-white text-[11px] font-semibold text-sky-900 hover:from-sky-100"
                    >
                        Образец
                    </a>
                    <a href="{{ route('admin.sale-goods.create') }}" class="cp-btn cp-btn-primary shadow-md shadow-amber-400/20 ring-1 ring-amber-300/40">
                        <span class="text-[14px] leading-none">+</span>
                        Добавить товар
                    </a>
                </div>
            </div>
            <x-input-error class="px-4 pt-3 sm:px-5" :messages="$errors->get('file')" />
            @if (($viewMode ?? 'goods') === 'categories')
                <div class="px-4 py-6 sm:px-5">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @forelse ($categories as $c)
                            <a
                                href="{{ route('admin.sale-goods.index', ['category' => $c['category_key']]) }}"
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
                                    <span class="mt-0.5 block text-[11px] font-medium tabular-nums text-slate-500">{{ $c['count'] }} наименований</span>
                                </span>
                                <svg class="h-5 w-5 shrink-0 text-emerald-400 transition group-hover:text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        @empty
                            <div class="col-span-full rounded-2xl border border-dashed border-emerald-200 bg-emerald-50/40 px-6 py-14 text-center text-[13px] text-slate-600">
                                Нет товаров —
                                <a href="{{ route('admin.sale-goods.create') }}" class="cp-link font-semibold text-sky-700 hover:text-sky-900">добавить</a>
                            </div>
                        @endforelse
                    </div>
                </div>
            @else
                <nav
                    class="flex flex-wrap items-center gap-2 border-b border-emerald-100/80 bg-emerald-50/35 px-4 py-3 text-[13px] sm:px-5"
                    aria-label="Навигация по категориям"
                >
                    <a
                        href="{{ route('admin.sale-goods.index') }}"
                        class="rounded-lg px-2.5 py-1 font-semibold text-emerald-800 transition hover:bg-white/80 hover:text-emerald-950"
                    >Все категории</a>
                    <span class="text-emerald-200" aria-hidden="true">/</span>
                    <span class="font-semibold text-slate-800">{{ $categoryTitle }}</span>
                </nav>
            <div
                class="relative z-[1] border-b border-slate-100 bg-gradient-to-b from-slate-50/70 to-white px-4 py-4 sm:px-5"
                x-data="saleGoodsSearch()"
                @click.outside="open = false"
                role="search"
            >
                <form
                    x-ref="saleGoodsTableForm"
                    method="GET"
                    action="{{ route('admin.sale-goods.index') }}"
                    class="space-y-2"
                >
                    @if (! empty($selectedCategoryKey))
                        <input type="hidden" name="category" value="{{ $selectedCategoryKey }}" />
                    @endif
                    <label for="sale_goods_q" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                        Поиск по номенклатуре
                    </label>
                    <div class="relative min-w-0 max-w-2xl">
                        <input
                            type="search"
                            name="q"
                            id="sale_goods_q"
                            x-model="query"
                            @input.debounce.300ms="open = true; fetchResults()"
                            @search="results = []; open = false"
                            @focus="onFocus()"
                            @keydown="onInputEnter($event)"
                            @keydown.escape="open = false"
                            autocomplete="off"
                            placeholder="Название, штрихкод, ОЭМ… (как на быстрой продаже)"
                            class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-3 pr-10 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        />
                        <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">⌕</span>
                        <div
                            x-cloak
                            x-show="open && (loading || query.trim() !== '')"
                            class="absolute left-0 right-0 top-full z-50 mt-1 max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-white py-0.5 text-[13px] leading-snug shadow-lg shadow-slate-200/50"
                        >
                            <div x-show="loading" class="px-3 py-2 text-xs text-slate-500">Поиск…</div>
                            <div
                                x-show="!loading && query.trim().length < 2 && query.trim() !== ''"
                                class="px-3 py-2 text-xs text-amber-800/90"
                            >Введите не менее 2 символов (так же, как в «Быстрой продаже»)</div>
                            <div
                                x-show="!loading && query.trim().length >= 2 && results.length === 0"
                                class="px-3 py-2 text-xs text-slate-500"
                            >Ничего не найдено</div>
                            <template x-for="row in results" :key="row.id">
                                <button
                                    type="button"
                                    class="flex w-full flex-col items-start gap-0.5 border-b border-slate-50 px-3 py-2 text-left transition hover:bg-emerald-50/80"
                                    @click="goEdit(row)"
                                >
                                    <span class="font-medium leading-snug text-slate-900" x-text="row.name"></span>
                                    <span
                                        x-show="row.barcode"
                                        class="text-[11px] font-mono text-slate-600"
                                        x-text="row.barcode"
                                    ></span>
                                    <span
                                        x-show="formatSalePrice(row.sale_price) !== ''"
                                        class="text-[11px] font-medium text-emerald-800"
                                        x-text="formatSalePrice(row.sale_price)"
                                    ></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 pt-0.5">
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-xl border border-emerald-600 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                        >
                            В список
                        </button>
                        @if (trim($searchQuery ?? '') !== '')
                            <a
                                href="{{ route('admin.sale-goods.index', ['category' => $selectedCategoryKey ?? '']) }}"
                                class="text-sm font-semibold text-slate-600 underline-offset-2 hover:text-slate-900 hover:underline"
                            >Сбросить</a>
                        @endif
                    </div>
                </form>
                @if (trim($searchQuery ?? '') !== '')
                    <p class="pt-1 text-[12px] text-slate-600">
                        В таблице: <span class="font-semibold tabular-nums text-slate-800">{{ $goods->total() }}</span>
                        @if ($goods->total() > 0)
                            <span class="text-slate-400">·</span>
                            <span class="text-slate-500">страница {{ $goods->currentPage() }} из {{ $goods->lastPage() }}</span>
                        @endif
                    </p>
                @endif
            </div>
            <div
                class="relative"
                x-data="saleGoodDetailModal(typeof window.__saleGoodModalCfg !== 'undefined' && window.__saleGoodModalCfg != null ? window.__saleGoodModalCfg : {})"
                @sale-good-open-modal.window="if ($event.detail && $event.detail.id != null) { openModal($event.detail.id); }"
            >
                <div class="cp-table-wrap border-t border-emerald-100/60 bg-gradient-to-b from-emerald-50/25 to-white">
                    <table class="cp-table">
                        <thead>
                            <tr>
                                <th>Наименование</th>
                                <th class="whitespace-nowrap">Ед.&nbsp;изм.</th>
                                <th class="cp-num whitespace-nowrap">Оптовая цена, сом</th>
                                <th class="cp-num whitespace-nowrap">Закуп. цена, сом</th>
                                <th class="cp-num whitespace-nowrap">Цена продажи, сом</th>
                                <th class="cp-num whitespace-nowrap">Мин. остаток</th>
                                <th class="cp-num whitespace-nowrap">Остатки</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($goods as $g)
                                <tr
                                    class="cursor-pointer transition-colors hover:bg-emerald-50/70 focus-within:bg-emerald-50/40"
                                    role="button"
                                    tabindex="0"
                                    @click.stop="openModal({{ $g->id }})"
                                    @keydown.enter.prevent="openModal({{ $g->id }})"
                                >
                                    <td class="align-top font-semibold text-neutral-900">{{ $g->display_name ?? $g->name }}</td>
                                    <td class="align-top whitespace-nowrap text-neutral-700">{{ $g->unit ?? '—' }}</td>
                                    <td class="cp-num align-top tabular-nums">{{ $fmt($g->wholesale_price) }}</td>
                                    <td class="cp-num align-top tabular-nums">{{ $fmt($g->aggregated_purchase_price ?? null) }}</td>
                                    <td class="cp-num align-top tabular-nums font-medium text-emerald-700">{{ $fmt($g->sale_price) }}</td>
                                    <td class="cp-num align-top tabular-nums">{{ $fmt($g->min_stock) }}</td>
                                    <td class="cp-num align-top tabular-nums">{{ $fmt($g->aggregated_stock ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-14 text-center text-[13px] text-slate-600">
                                        @if (trim($searchQuery ?? '') !== '')
                                            По запросу ничего не найдено.
                                            <a href="{{ route('admin.sale-goods.index', ['category' => $selectedCategoryKey ?? '']) }}" class="cp-link font-semibold text-sky-700 hover:text-sky-900">Сбросить поиск</a>
                                        @else
                                            Нет товаров —
                                            <a href="{{ route('admin.sale-goods.create') }}" class="cp-link font-semibold text-sky-700 hover:text-sky-900">добавить</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($goods->hasPages())
                    <div class="border-t border-slate-100 bg-slate-50/80 px-4 py-3 text-sm text-slate-700">
                        {{ $goods->links() }}
                    </div>
                @endif

                {{-- Teleport + z-[20000]: иначе модалка оказывается ПОД блоком поиска (z-20) из-за stacking context. --}}
                <template x-teleport="body">
                    <div
                        x-show="modalOpen"
                        x-cloak
                        class="fixed inset-0 z-[20000] flex items-end justify-center sm:items-center sm:p-5"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="sale-good-modal-title"
                        @keydown.escape.window="if (modalOpen) closeModal()"
                    >
                        <div
                            class="absolute inset-0 bg-slate-900/55 backdrop-blur-[1px]"
                            @click="closeModal()"
                            aria-hidden="true"
                        ></div>
                        <div
                            class="relative flex max-h-[min(90vh,52rem)] w-full max-w-lg flex-col overflow-hidden rounded-t-2xl border border-slate-200/90 bg-white shadow-2xl shadow-slate-300/40 sm:max-w-xl sm:rounded-2xl"
                            @click.stop
                        >
                            <div class="flex shrink-0 items-start gap-4 border-b border-emerald-700/20 bg-gradient-to-r from-emerald-600 to-teal-600 px-5 py-4 sm:px-6">
                            <span class="mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/15 text-white ring-1 ring-white/25" aria-hidden="true">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                                </svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-50/95">Карточка товара</p>
                                <h2 class="mt-1 text-base font-bold leading-snug text-white sm:text-[17px]" x-text="meta.display_name || 'Товар'"></h2>
                                <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[12px] text-emerald-50">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-white/15 px-2.5 py-0.5 font-medium tabular-nums text-white ring-1 ring-white/20">
                                        Остаток:
                                        <span x-text="Number(meta.aggregated_stock || 0).toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full bg-white/15 px-2.5 py-0.5 font-medium tabular-nums text-white ring-1 ring-white/20"
                                        x-show="meta.aggregated_purchase_price != null"
                                    >
                                        Закуп.:
                                        <span x-text="meta.aggregated_purchase_price != null ? Number(meta.aggregated_purchase_price).toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '\u00A0сом' : ''"></span>
                                    </span>
                                </p>
                            </div>
                            <button
                                type="button"
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/35 bg-white/10 text-xl font-light leading-none text-white transition hover:bg-white/20"
                                @click="closeModal()"
                                aria-label="Закрыть"
                            >×</button>
                        </div>

                        <div class="flex shrink-0 gap-0 border-b border-slate-200 bg-slate-100/90 px-2 sm:px-3">
                            <button
                                type="button"
                                class="relative flex-1 rounded-t-lg px-3 py-3 text-[13px] font-semibold transition sm:flex-none sm:px-5"
                                :class="activeTab === 'info'
                                    ? 'bg-white text-emerald-900 shadow-[0_-2px_10px_rgba(0,0,0,0.04)] ring-1 ring-slate-200/90 ring-b-0 sm:rounded-t-xl'
                                    : 'text-slate-600 hover:bg-white/70 hover:text-slate-900'"
                                @click="activeTab = 'info'"
                            >Информация о товаре</button>
                            <button
                                type="button"
                                class="relative flex-1 rounded-t-lg px-3 py-3 text-[13px] font-semibold transition sm:flex-none sm:px-5"
                                :class="activeTab === 'movements'
                                    ? 'bg-white text-emerald-900 shadow-[0_-2px_10px_rgba(0,0,0,0.04)] ring-1 ring-slate-200/90 ring-b-0 sm:rounded-t-xl'
                                    : 'text-slate-600 hover:bg-white/70 hover:text-slate-900'"
                                @click="activeTab = 'movements'"
                            >Движения товара</button>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto bg-gradient-to-b from-white to-emerald-50/25 px-4 py-5 sm:px-6">
                            <div x-show="loading" class="flex flex-col items-center justify-center gap-2 py-16 text-sm text-slate-500">
                                <svg class="h-8 w-8 animate-spin text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Загрузка…
                            </div>
                            <div x-show="!loading && loadError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" x-text="loadError"></div>

                            <div x-show="!loading && !loadError && activeTab === 'info'" x-cloak class="space-y-4">
                                <p x-show="saveError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" x-text="saveError"></p>
                                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="saveGood()">
                                    <div class="sm:col-span-2">
                                        <label class="block text-sm font-medium text-slate-700">Артикул *</label>
                                        <input x-model="form.article_code" type="text" required maxlength="100" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm shadow-sm transition placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" autocomplete="off" />
                                        <p x-show="fieldErr('article_code')" class="mt-1.5 text-xs text-red-700" x-text="fieldErr('article_code')"></p>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-sm font-medium text-slate-700">Наименование *</label>
                                        <input x-model="form.name" type="text" required maxlength="500" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm shadow-sm transition focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" />
                                        <p x-show="fieldErr('name')" class="mt-1.5 text-xs text-red-700" x-text="fieldErr('name')"></p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Ед. изм.</label>
                                        <input x-model="form.unit" type="text" maxlength="32" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm shadow-sm transition focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Категория</label>
                                        <div class="mt-1.5 flex gap-2">
                                            <select
                                                x-model="form.category"
                                                class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                            >
                                                <option value="">Без категории</option>
                                                <template x-for="cat in sortedCategories()" :key="cat">
                                                    <option :value="cat" x-text="cat"></option>
                                                </template>
                                            </select>
                                            <button
                                                type="button"
                                                class="inline-flex h-[42px] w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-lg font-semibold leading-none text-emerald-700 shadow-sm transition hover:bg-emerald-50"
                                                :class="showCategoryAdd ? 'border-emerald-400 bg-emerald-50' : ''"
                                                title="Новая категория"
                                                :aria-expanded="showCategoryAdd"
                                                @click="showCategoryAdd = !showCategoryAdd"
                                            >+</button>
                                        </div>
                                        <div
                                            x-show="showCategoryAdd"
                                            x-cloak
                                            class="mt-2 flex flex-wrap items-stretch gap-2 rounded-xl border border-slate-200 bg-slate-50/90 p-2.5 sm:items-center"
                                        >
                                            <input
                                                type="text"
                                                x-model="newCategoryName"
                                                maxlength="120"
                                                placeholder="Название новой категории"
                                                class="min-w-[10rem] flex-1 rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                                @keydown.enter.prevent="addNewCategory()"
                                            />
                                            <button
                                                type="button"
                                                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                                                @click="addNewCategory()"
                                            >Добавить</button>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Штрихкод</label>
                                        <input x-model="form.barcode" type="text" maxlength="64" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 font-mono text-sm shadow-sm transition focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" autocomplete="off" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Цена продажи (сом)</label>
                                        <input x-model="form.sale_price" type="text" inputmode="decimal" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm shadow-sm transition focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" autocomplete="off" />
                                        <p x-show="fieldErr('sale_price')" class="mt-1.5 text-xs text-red-700" x-text="fieldErr('sale_price')"></p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Опт (сом)</label>
                                        <input x-model="form.wholesale_price" type="text" inputmode="decimal" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm shadow-sm transition focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Мин. цена (сом)</label>
                                        <input x-model="form.min_sale_price" type="text" inputmode="decimal" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm shadow-sm transition focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Мин. остаток</label>
                                        <input x-model="form.min_stock" type="text" inputmode="decimal" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm shadow-sm transition focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">ОЭМ</label>
                                        <input x-model="form.oem" type="text" maxlength="120" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm shadow-sm transition focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700">Номер завода</label>
                                        <input x-model="form.factory_number" type="text" maxlength="120" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm shadow-sm transition focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" />
                                    </div>
                                    <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-200/90 bg-white/80 pt-4 sm:col-span-2">
                                        <button type="button" class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50" @click="closeModal()">Отмена</button>
                                        <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-60" :disabled="saving" x-text="saving ? 'Сохранение…' : 'Сохранить'"></button>
                                    </div>
                                </form>
                            </div>

                            <div x-show="!loading && !loadError && activeTab === 'movements'" x-cloak class="space-y-3">
                                <p x-show="movements.length === 0" class="rounded-xl border border-dashed border-slate-200 bg-slate-50/70 py-12 text-center text-sm text-slate-600">Нет зарегистрированных движений по этому товару.</p>
                                <div x-show="movements.length > 0" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
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
                                            <template x-for="(m, idx) in movements" :key="idx">
                                                <tr class="bg-white transition hover:bg-emerald-50/50">
                                                    <td class="whitespace-nowrap px-3 py-2.5 tabular-nums text-slate-600" x-text="m.date_human"></td>
                                                    <td class="px-3 py-2.5 font-medium text-slate-900" x-text="m.label"></td>
                                                    <td class="max-w-[10rem] truncate px-3 py-2.5 text-slate-600 sm:max-w-none" x-text="m.warehouse" :title="m.warehouse"></td>
                                                    <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono text-sm font-semibold tabular-nums" :class="m.direction === 'in' ? 'text-emerald-700' : (m.direction === 'out' ? 'text-red-700' : 'text-slate-800')" x-text="fmtQty(m.quantity, m.direction)"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                </template>
            </div>
            @endif
        </div>
    </div>
</x-admin-layout>
