@props([
    'formSelector' => '[data-journal-filter-form]',
    'goodsSearchUrl',
    'warehouseId' => 0,
    'filterGoodId' => 0,
    'filterGoodSummary' => '',
    'returnsUrl' => null,
    'boxed' => true,
    'filterTitle' => null,
])
<div
    @class([
        'rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:p-5' => $boxed,
        '-mx-4 border-x-0 border-b-0 border-t border-slate-200 bg-slate-50/50 px-4 py-4 sm:-mx-6 sm:px-6' => ! $boxed,
    ])
    x-data="journalSaleGoodFilter(@js([
        'goodsSearchUrl' => $goodsSearchUrl,
        'warehouseId' => (int) $warehouseId,
        'initialGoodId' => (int) $filterGoodId,
        'initialSummary' => $filterGoodSummary,
        'formSelector' => $formSelector,
    ]))"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <label class="text-xs font-medium text-slate-600">{{ $filterTitle ?? 'Поиск по товару (для контроля продаж и возврата)' }}</label>
                @if ($returnsUrl)
                    <a href="{{ $returnsUrl }}" class="text-xs font-semibold text-emerald-800 hover:underline">Возвраты от клиентов</a>
                @endif
            </div>
            <div class="relative mt-2 max-w-xl">
                <input
                    type="search"
                    autocomplete="off"
                    x-model="query"
                    @focus="focusSuggest()"
                    @blur="scheduleBlurClose()"
                    placeholder="Наименование, артикул или штрихкод…"
                    class="block w-full rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-9 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                />
                <span
                    class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400"
                    x-show="loading"
                    x-cloak
                >…</span>
                <div
                    x-show="open && results.length > 0"
                    x-cloak
                    x-transition
                    class="absolute left-0 right-0 z-30 mt-1 max-h-56 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg"
                    @mousedown.prevent
                >
                    <template x-for="(row, idx) in results" :key="idx">
                        <button
                            type="button"
                            class="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left hover:bg-emerald-50"
                            @click="pick(row)"
                        >
                            <span class="font-medium text-slate-900" x-text="itemLabel(row)"></span>
                            <span class="text-xs text-slate-500" x-show="row.barcode" x-text="row.barcode ? ('ШК ' + row.barcode) : ''"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
        <div class="flex shrink-0 flex-col items-start gap-2 sm:items-end sm:pt-6" x-show="selectedGoodId > 0 && selectedSummary" x-cloak>
            <div class="inline-flex max-w-full flex-wrap items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50/90 px-3 py-2 text-xs text-emerald-950">
                <span class="font-medium">Фильтр:</span>
                <span class="min-w-0 break-words" x-text="selectedSummary"></span>
                <button type="button" class="shrink-0 font-semibold text-emerald-900 underline hover:no-underline" @click="clear()">
                    Сбросить товар
                </button>
            </div>
        </div>
    </div>
</div>
