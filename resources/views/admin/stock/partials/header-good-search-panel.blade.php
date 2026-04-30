{{-- Внутри x-data="stockInventoryDoc(...)" с enableHeaderSearch: true --}}
<div
    class="relative z-20 flex h-full min-h-0 min-w-0 flex-col overflow-visible"
    x-show="enableHeaderSearch"
    x-cloak
>
    <div class="pr-panel-shell flex h-full min-h-0 min-w-0 flex-col overflow-visible border border-teal-800/12 bg-white shadow-[0_4px_24px_-8px_rgba(15,23,42,0.12)] ring-1 ring-teal-900/[0.05]">
        <div class="pr-panel-header-teal shrink-0">
            <label for="stock_header_good_q">Наименование товара</label>
        </div>
        <div class="relative flex min-h-0 flex-1 flex-col bg-white px-4 pb-5 pt-4 sm:px-[1.125rem] sm:pb-6 sm:pt-5">
            <div class="relative z-[59990] shrink-0">
                <input
                    id="stock_header_good_q"
                    type="search"
                    x-model="headerGoodQuery"
                    @focus="onHeaderGoodFocus()"
                    @input.debounce.300ms="onHeaderGoodInput()"
                    @keydown.enter="onHeaderGoodEnter($event)"
                    @blur="onHeaderGoodBlur()"
                    autocomplete="off"
                    placeholder="От 2 символов; клики по строке +1 шт.; Enter если найден один товар"
                    class="box-border min-h-[3.125rem] w-full min-w-0 max-w-full rounded-[0.625rem] border border-sky-300/90 bg-white py-3 pl-[0.875rem] pr-11 text-[15px] leading-snug text-slate-800 shadow-[inset_0_1px_2px_rgba(15,23,42,0.03)] placeholder:text-slate-400 transition-colors focus:border-[#008b8b] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#008b8b]/20"
                />
                <span class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </span>
                <template x-if="headerGoodOpen && (headerGoodLoading || headerGoodQuery.trim() !== '')">
                    <div
                        x-cloak
                        class="absolute left-0 right-0 top-full z-[60000] mt-1 max-h-[min(24rem,70vh)] w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 text-left text-[13px] leading-snug shadow-2xl ring-1 ring-slate-400/40"
                        role="listbox"
                        @mousedown.prevent
                    >
                        <div x-show="headerGoodLoading" class="px-3 py-2 text-xs text-slate-500">Поиск…</div>
                        <div
                            x-show="!headerGoodLoading && headerGoodQuery.trim().length < 2 && headerGoodQuery.trim() !== ''"
                            class="px-3 py-2 text-xs text-amber-800/90"
                        >Введите не менее 2 символов</div>
                        <template x-for="item in headerGoodItems" :key="item.id">
                            <button
                                type="button"
                                role="option"
                                class="flex w-full flex-col items-stretch border-b border-slate-50 px-3 py-2 text-left transition hover:bg-emerald-50/80"
                                @mousedown.prevent="appendLineFromCatalogItem(item)"
                            >
                                <span class="font-medium text-slate-900" x-text="item.name"></span>
                                <span class="mt-0.5 font-mono text-[11px] text-slate-500" x-text="item.article_code"></span>
                                <div
                                    class="mt-1 flex flex-wrap gap-1.5"
                                    x-show="headerSuggestHasStockHint(item)"
                                >
                                    <span
                                        class="inline-flex rounded-lg border px-1.5 py-0.5 text-[10px] font-semibold"
                                        :class="headerStockSoldOut(item.stock_quantity)
                                            ? 'border-red-200/85 bg-gradient-to-r from-red-50/95 to-orange-50/40 text-red-800'
                                            : 'border-emerald-200/85 bg-gradient-to-r from-emerald-50/95 to-sky-50/50 text-teal-900'"
                                        x-show="item.stock_quantity != null && item.stock_quantity !== ''"
                                        x-text="'Остаток: ' + formatHeaderStockQty(item.stock_quantity) + (item.unit ? ' ' + item.unit : '')"
                                    ></span>
                                    <span
                                        class="inline-flex rounded-lg border border-sky-200/80 bg-white px-1.5 py-0.5 text-[10px] font-medium text-slate-700"
                                        x-show="item.opening_unit_cost != null && item.opening_unit_cost !== ''"
                                        x-text="'Закуп. по складу: ' + formatHeaderUnitCost(item.opening_unit_cost)"
                                    ></span>
                                </div>
                            </button>
                        </template>
                        <div
                            x-show="!headerGoodLoading && headerGoodQuery.trim().length >= 2 && headerGoodItems.length === 0"
                            class="border-t border-slate-100 px-3 py-2"
                        >
                            <button
                                type="button"
                                x-show="enableQuickNewGood"
                                class="w-full rounded-lg border border-teal-300/80 bg-teal-50/90 px-3 py-2 text-left text-sm font-semibold text-teal-900 hover:bg-teal-100/80"
                                @mousedown.prevent="openStockQuickNewGoodModal(headerGoodQuery.trim(), 'header')"
                            >
                                + Новый товар…
                            </button>
                            <p x-show="!enableQuickNewGood" class="text-xs text-slate-500">Ничего не найдено.</p>
                        </div>
                    </div>
                </template>
            </div>
            <div class="min-h-0 flex-1" aria-hidden="true"></div>
        </div>
    </div>
</div>
