@php
    $fmt = static function ($v): string {
        if ($v === null) {
            return '—';
        }

        return number_format((float) $v, 2, ',', ' ');
    };
@endphp
<x-admin-layout pageTitle="Продажа услуг">
    @include('admin.partials.cp-brush')
    <div class="cp-root mx-auto w-full max-w-6xl space-y-4">
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
            window.__saleGoodsInit = @json($servicesSearchConfig ?? []);
        </script>

        <div class="overflow-visible rounded-2xl border border-sky-200/70 bg-white shadow-[0_8px_30px_-8px_rgba(14,165,233,0.15)] ring-1 ring-sky-100/60">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-emerald-100/80 bg-gradient-to-r from-emerald-50/95 via-white to-sky-50/60 px-4 py-3.5 sm:px-5">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-teal-600 text-white shadow-md shadow-emerald-500/25" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655-5.653a2.548 2.548 0 010-3.586L11.4 2.845a2.548 2.548 0 013.586 0l5.653 4.655a2.548 2.548 0 010 3.586l-1.635 1.635M11.42 15.17L9.15 12.91" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="mb-0.5 text-[10px] font-semibold uppercase tracking-wider text-teal-700/90">Справочник</p>
                        <h2 class="truncate text-[14px] font-bold leading-tight text-slate-800">Продажа услуг</h2>
                        <p class="mt-1 text-[12px] text-slate-600">Исправьте наименование и цену, если данные введены с ошибкой.</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form
                        method="POST"
                        action="{{ route('admin.sale-services.import') }}"
                        enctype="multipart/form-data"
                        class="flex flex-wrap items-center gap-2"
                    >
                        @csrf
                        <input
                            type="file"
                            name="file"
                            id="sale_services_import_file"
                            class="hidden"
                            accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
                            onchange="if (this.files.length) this.form.requestSubmit()"
                        />
                        <button
                            type="button"
                            class="cp-btn border-emerald-200/90 bg-gradient-to-b from-white to-emerald-50/90 text-[11px] font-semibold text-slate-800 shadow-sm hover:to-emerald-50"
                            onclick="document.getElementById('sale_services_import_file').click()"
                        >
                            Excel…
                        </button>
                    </form>
                    <a
                        href="{{ route('admin.sale-services.sample-import') }}"
                        class="cp-btn border-sky-200 bg-gradient-to-b from-sky-50 to-white text-[11px] font-semibold text-sky-900 hover:from-sky-100"
                    >
                        Образец
                    </a>
                    <a href="{{ route('admin.sale-services.create') }}" class="cp-btn cp-btn-primary shadow-md shadow-amber-400/20 ring-1 ring-amber-300/40">
                        <span class="text-[14px] leading-none">+</span>
                        Добавить услугу
                    </a>
                </div>
            </div>
            <div
                class="relative z-20 border-b border-slate-200/80 bg-slate-50/60 px-4 py-3 sm:px-5"
                x-data="saleGoodsSearch()"
                @click.outside="open = false"
                role="search"
            >
                <form
                    x-ref="saleGoodsTableForm"
                    method="GET"
                    action="{{ route('admin.sale-services.index') }}"
                    class="space-y-2"
                >
                    <label for="sale_services_q" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                        Поиск (как в рознице)
                    </label>
                    <p class="mb-1.5 text-[12px] leading-snug text-slate-500">
                        Введите от 2 символов — подсказки из справочника. Клик по строке — сразу в карточку. Кнопка «В список» — отфильтровать таблицу ниже.
                    </p>
                    <div class="relative min-w-0 max-w-2xl">
                        <input
                            type="search"
                            name="q"
                            id="sale_services_q"
                            x-model="query"
                            @input.debounce.300ms="open = true; fetchResults()"
                            @search="results = []; open = false"
                            @focus="onFocus()"
                            @keydown="onInputEnter($event)"
                            @keydown.escape="open = false"
                            autocomplete="off"
                            placeholder="Название, код, штрихкод… (как на быстрой продаже)"
                            class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-3 pr-10 text-sm text-slate-900 shadow-sm ring-1 ring-slate-900/5 placeholder:text-slate-400 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        />
                        <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">⌕</span>
                        <div
                            x-cloak
                            x-show="open && (loading || query.trim() !== '')"
                            class="absolute left-0 right-0 top-full z-50 mt-1 max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-white py-0.5 text-[13px] leading-snug shadow-xl"
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
                                    <span class="text-[11px] text-slate-600">
                                        <span class="font-mono" x-text="row.article_code"></span>
                                        <span x-show="row.barcode" class="text-slate-500"> · <span class="font-mono" x-text="row.barcode"></span></span>
                                    </span>
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
                            class="inline-flex items-center justify-center rounded-xl border border-slate-200/90 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                        >
                            В список
                        </button>
                        @if (trim($searchQuery ?? '') !== '')
                            <a
                                href="{{ route('admin.sale-services.index') }}"
                                class="text-sm font-semibold text-slate-600 underline-offset-2 hover:text-slate-900 hover:underline"
                            >Сбросить</a>
                        @endif
                    </div>
                </form>
                @if (trim($searchQuery ?? '') !== '')
                    <p class="pt-1 text-[12px] text-slate-600">
                        В таблице: <span class="font-semibold tabular-nums text-slate-800">{{ $services->total() }}</span>
                        @if ($services->total() > 0)
                            <span class="text-slate-400">·</span>
                            <span class="text-slate-500">страница {{ $services->currentPage() }} из {{ $services->lastPage() }}</span>
                        @endif
                    </p>
                @endif
            </div>
            <x-input-error class="px-4 pt-3 sm:px-5" :messages="$errors->get('file')" />
            <div class="cp-table-wrap bg-gradient-to-b from-slate-50/40 to-white">
                <table class="cp-table">
                    <thead>
                        <tr>
                            <th>Наименование</th>
                            <th>Ед.</th>
                            <th class="cp-num">Цена, сом</th>
                            <th class="text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($services as $s)
                            <tr>
                                <td class="align-top font-semibold text-neutral-900">{{ $s->name }}</td>
                                <td class="align-top whitespace-nowrap text-neutral-700">{{ $s->unit ?? '—' }}</td>
                                <td class="cp-num align-top tabular-nums">{{ $fmt($s->sale_price) }}</td>
                                <td class="whitespace-nowrap text-right align-top">
                                    <a href="{{ route('admin.sale-services.edit', $s) }}" class="cp-link">Изменить цену</a>
                                    <span class="mx-1.5 text-slate-300">|</span>
                                    <form
                                        action="{{ route('admin.sale-services.destroy', $s) }}"
                                        method="POST"
                                        class="inline"
                                        onsubmit="return confirm('Удалить услугу «{{ $s->name }}»?');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="cp-link text-red-700 hover:text-red-900">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-14 text-center text-[13px] text-slate-600">
                                    @if (trim($searchQuery ?? '') !== '')
                                        По запросу ничего не найдено.
                                        <a href="{{ route('admin.sale-services.index') }}" class="cp-link font-semibold text-sky-700 hover:text-sky-900">Показать все</a>
                                    @else
                                        Нет услуг —
                                        <a href="{{ route('admin.sale-services.create') }}" class="cp-link font-semibold text-sky-700 hover:text-sky-900">добавить</a>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($services->hasPages())
                <div class="border-t border-slate-200/80 bg-slate-50/90 px-4 py-3 text-sm text-slate-700">
                    {{ $services->links() }}
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
