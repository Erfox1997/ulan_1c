<x-admin-layout pageTitle="История машин" main-class="bg-slate-100/80 px-3 py-4 sm:px-5 lg:px-7">
    @include('admin.partials.cp-brush')
    <div
        class="cp-root mx-auto max-w-5xl space-y-5"
        x-data="vehicleHistoryIndexPage(@json([
            'jsonBase' => $vehicleHistoryJsonBase,
            'searchUrl' => $vehicleSearchUrl,
        ]))"
    >
        @include('admin.partials.status-flash')

        <div>
            <h1 class="text-lg font-bold text-slate-900">История машин</h1>
            <p class="mt-1 text-sm text-slate-600">
                Введите не менее <strong>2 символов</strong> — появится список автомобилей (по марке, госномеру, VIN или клиенту). Выберите строку, чтобы открыть историю обслуживания.
            </p>
        </div>

        <div class="rounded-xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]">
            <div class="border-b border-emerald-900/12 px-4 py-3 sm:px-5" style="background: linear-gradient(125deg, #047857 0%, #0d9488 55%, #115e59 100%);">
                <label for="vh_search_input" class="block text-[10px] font-bold uppercase tracking-wide text-teal-100/95">Автомобиль</label>
                <div class="relative mt-2">
                    <input
                        id="vh_search_input"
                        type="text"
                        autocomplete="off"
                        x-model="searchQuery"
                        @input="scheduleSuggest()"
                        @focus="onSearchFocus()"
                        @blur="onSearchBlur()"
                        @keydown.escape.prevent="suggestOpen = false"
                        class="min-h-[2.75rem] w-full rounded-lg border-0 bg-white/95 px-3 py-2.5 pr-10 text-sm font-medium text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-white/80"
                    />
                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                    </span>

                    <div
                        x-cloak
                        x-show="suggestOpen && (suggestLoading || searchQuery.trim().length >= 2)"
                        class="absolute left-0 right-0 top-full z-[500] mt-1 max-h-[min(22rem,70vh)] overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-xl ring-1 ring-slate-900/15"
                        @mousedown.prevent
                    >
                        <div x-show="suggestLoading" class="px-3 py-2 text-xs text-slate-500">Поиск…</div>
                        <div
                            x-show="!suggestLoading && searchQuery.trim().length >= 2 && suggestNoHits"
                            class="px-3 py-2 text-xs text-slate-600"
                        >
                            Ничего не найдено
                        </div>
                        <template x-for="item in suggestItems" :key="item.id">
                            <button
                                type="button"
                                class="flex w-full flex-col items-start gap-0.5 border-b border-slate-50 px-3 py-2.5 text-left text-sm transition hover:bg-emerald-50/90 last:border-b-0"
                                @mousedown.prevent
                                @click="pickVehicle(item)"
                            >
                                <span class="font-medium text-slate-900" x-text="item.label"></span>
                                <span class="text-[11px] text-slate-500">Клиент: <span x-text="item.client_label"></span></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- Модальное окно истории --}}
        <div
            x-cloak
            x-show="modalOpen"
            class="fixed inset-0 z-[300] flex items-end justify-center bg-black/40 p-3 sm:items-center"
            @keydown.escape.window="closeModal()"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
                @click.outside="closeModal()"
            >
                <div class="shrink-0 border-b border-slate-100 bg-slate-50/90 px-4 py-3">
                    <h2 class="text-sm font-bold text-slate-900">История обслуживания</h2>
                    <p
                        class="mt-1 text-xs text-slate-600"
                        x-show="payload && payload.vehicle"
                        x-text="payload && payload.vehicle ? payload.vehicle.label : ''"
                    ></p>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3">
                    <div x-show="loading" class="py-8 text-center text-sm text-slate-500">Загрузка…</div>
                    <p x-show="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" x-text="error"></p>
                    <template x-if="payload && !loading && !error">
                        <div class="space-y-4">
                            <template x-if="!payload.visits || payload.visits.length === 0">
                                <p class="text-sm text-slate-600">По этому автомобилю нет заявок (кроме отменённых).</p>
                            </template>
                            <template x-for="visit in (payload.visits || [])" :key="visit.service_order_id + '-' + visit.document_date">
                                <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-100 pb-2">
                                        <div class="text-sm font-semibold text-slate-900">
                                            <span x-text="formatVisitDate(visit.document_date)"></span>
                                            <span class="text-slate-400"> · </span>
                                            Пробег <span class="tabular-nums text-slate-800" x-text="mileageDisplay(visit.mileage_km)"></span>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-[11px] font-medium text-slate-500">№ <span class="font-mono" x-text="visit.service_order_id"></span></span>
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700" x-text="visitStatusLabel(visit.status)"></span>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Услуги</p>
                                        <template x-if="visit.services && visit.services.length">
                                            <ul class="mt-1 space-y-1 text-sm text-slate-800">
                                                <template x-for="(svc, si) in visit.services" :key="si">
                                                    <li class="flex gap-2">
                                                        <span class="min-w-0 flex-1" x-text="svc.name"></span>
                                                        <span class="shrink-0 tabular-nums text-slate-600" x-text="'× ' + svc.quantity"></span>
                                                    </li>
                                                </template>
                                            </ul>
                                        </template>
                                        <template x-if="!visit.services || visit.services.length === 0">
                                            <p class="mt-1 text-xs text-slate-500">Услуги в строках не найдены (учитываются позиции с типом «услуга» или с исполнителем).</p>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
                <div class="shrink-0 border-t border-slate-100 bg-slate-50/90 px-4 py-3 text-right">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        @click="closeModal()"
                    >
                        Закрыть
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
