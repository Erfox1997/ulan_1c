{{-- Внутренность левой колонки (шапка «Наименование товара» — в родительском шаблоне). --}}
<div class="relative z-[59990] shrink-0 w-full min-w-0" x-ref="prtHeaderGoodColumn">
    <input
        id="prt_header_good_q"
        type="search"
        data-prt-header-good-input
        x-model="prtHeaderQuery"
        @focus="onPrtHeaderGoodsFocus($event)"
        @input="onPrtHeaderGoodsInput($event)"
        @keydown.enter="onPrtHeaderGoodsEnter($event)"
        @blur="onPrtHeaderGoodsBlur()"
        autocomplete="off"
        placeholder="Название, от 2 символов — Enter в таблицу"
        class="box-border min-h-[3.125rem] w-full min-w-0 max-w-full rounded-[0.625rem] border border-sky-300/90 bg-white py-3 pl-[0.875rem] pr-11 text-[15px] leading-snug text-slate-800 shadow-[inset_0_1px_2px_rgba(15,23,42,0.03)] placeholder:text-slate-400 transition-colors focus:border-[#008b8b] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#008b8b]/20"
    />
    <span class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
        </svg>
    </span>
    <template x-if="prtHeaderOpen && (prtHeaderLoading || prtHeaderQuery.trim() !== '')">
        <div
            x-cloak
            class="absolute left-0 right-0 top-full z-[60000] mt-1 max-h-[min(24rem,70vh)] w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 text-left text-[13px] leading-snug shadow-2xl ring-1 ring-slate-400/40"
            role="listbox"
            @mousedown.prevent
        >
            <div x-show="prtHeaderLoading" class="px-3 py-2 text-xs text-slate-500">Поиск…</div>
            <div
                x-show="!prtHeaderLoading && prtHeaderQuery.trim().length < 2 && prtHeaderQuery.trim() !== ''"
                class="px-3 py-2 text-xs text-amber-800/90"
            >Введите не менее 2 символов</div>
            <div
                x-show="!prtHeaderLoading && prtHeaderQuery.trim().length >= 2 && prtHeaderItems.length === 0"
                class="border-b border-slate-100 px-3 py-2"
            >
                <button
                    type="button"
                    class="w-full rounded-lg border border-emerald-300/90 bg-emerald-50 px-3 py-2 text-center text-[12px] font-semibold leading-snug text-emerald-950 shadow-sm hover:bg-emerald-100/95"
                    @mousedown.prevent.stop
                    @click.prevent.stop="openNewGoodModal(prtHeaderQuery)"
                >
                    Добавить новый товар
                </button>
            </div>
            <template x-for="item in prtHeaderItems" :key="item.id">
                <div
                    role="option"
                    class="flex w-full items-stretch border-b border-slate-50 hover:bg-emerald-50/80"
                >
                    <button
                        type="button"
                        class="flex min-w-0 flex-1 flex-col items-stretch gap-0.5 px-3 py-2 text-left text-xs"
                        @mousedown.prevent="appendPrtLineFromCatalogItem(item)"
                    >
                        <span class="font-medium text-slate-900" x-text="item.name"></span>
                        @include('admin.partials.goods-suggest-meta-pills')
                    </button>
                    <button
                        type="button"
                        class="flex min-w-[3.75rem] shrink-0 flex-col items-center justify-center gap-0.5 border-l px-1.5 py-2 text-center text-xs transition-colors"
                        :class="String(prtHeaderCopyFeedbackGoodId) === String(item.id) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-100 bg-white text-slate-400 hover:bg-slate-50 hover:text-teal-700'"
                        :title="String(prtHeaderCopyFeedbackGoodId) === String(item.id) ? 'Скопировано в буфер обмена' : 'Копировать наименование'"
                        :aria-label="String(prtHeaderCopyFeedbackGoodId) === String(item.id) ? 'Скопировано' : 'Копировать наименование'"
                        @mousedown.prevent.stop
                        @click.prevent.stop="copyPrtHeaderGoodName(item, $event)"
                    >
                        <span x-show="String(prtHeaderCopyFeedbackGoodId) !== String(item.id)" class="inline-flex shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </span>
                        <span
                            x-show="String(prtHeaderCopyFeedbackGoodId) === String(item.id)"
                            class="flex flex-col items-center gap-0.5"
                            role="status"
                            aria-live="polite"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="max-w-[4rem] text-center text-[10px] font-semibold leading-tight text-emerald-800">Скопировано</span>
                        </span>
                    </button>
                </div>
            </template>
        </div>
    </template>
</div>
