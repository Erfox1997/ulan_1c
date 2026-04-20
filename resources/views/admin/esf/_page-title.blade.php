<div
    class="relative flex w-full min-w-0 flex-wrap items-center gap-2"
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
>
    <h2 class="text-lg font-semibold tracking-tight text-slate-900">Электронный счёт-фактура (ЭСФ)</h2>
    <div class="inline-flex shrink-0">
        <button
            type="button"
            class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-slate-300 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
            @click="open = !open"
            :aria-expanded="open"
            aria-controls="esf-help-panel"
            title="Справка по выгрузке ЭСФ"
        >
            <span class="sr-only">Справка</span>
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path
                    fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z"
                    clip-rule="evenodd"
                />
            </svg>
        </button>
    </div>
    <div
        id="esf-help-panel"
        x-show="open"
        x-cloak
        x-transition
        @click.outside="open = false"
        class="absolute left-0 top-full z-50 mt-2 w-full max-w-[56rem] rounded-lg border border-slate-200 bg-white p-4 text-left text-sm leading-snug text-slate-700 shadow-lg ring-1 ring-slate-900/5 sm:p-5"
    >
        <p class="mb-4 font-medium text-slate-900">Чтобы ЭСФ без ошибок приняли в ГНС, проверьте:</p>
        <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-5">
            <div class="min-w-0 rounded-md border border-slate-100 bg-slate-50/80 p-3">
                <p class="font-semibold text-slate-900">1. Ваша организация (продавец)</p>
                <p class="mt-2 text-sm text-slate-700">
                    В справочнике организаций укажите верные ИНН и расчётные счета; они должны совпадать с личным кабинетом налоговой и с тем, что выбираете при выгрузке XML.
                </p>
            </div>
            <div class="min-w-0 rounded-md border border-slate-100 bg-slate-50/80 p-3">
                <p class="font-semibold text-slate-900">2. Контрагент (покупатель)</p>
                <p class="mt-2 text-sm text-slate-700">
                    В карточке контрагента должны быть верны ИНН и банковский счёт; сведения должны согласовываться с учётом в ГНС и попадать в XML.
                </p>
            </div>
            <div class="min-w-0 rounded-md border border-slate-100 bg-slate-50/80 p-3 sm:col-span-2 lg:col-span-1">
                <p class="font-semibold text-slate-900">3. Товары и услуги</p>
                <p class="mt-2 text-sm text-slate-700">
                    Наименования и классификация в документе должны совпадать со справочником в налоговой: ведите позиции одинаково в программе и в кабинете ГНС. Если в одной реализации есть и товары, и услуги (по номенклатуре), XML выгружается <span class="font-semibold">двумя файлами</span> — отдельно товары и отдельно услуги.
                </p>
            </div>
        </div>
        <p class="mt-4 border-t border-slate-100 pt-3 text-xs leading-relaxed text-slate-500">
            При расхождениях портал может отклонить файл — исправьте данные в программе и сформируйте XML заново.
        </p>
    </div>
</div>
