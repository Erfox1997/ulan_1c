<x-admin-layout pageTitle="Новое поступление" main-class="px-3 py-6 sm:px-6 lg:px-8">
    @include('admin.partials.cp-brush')
    <div class="cp-root mx-auto w-full max-w-[min(100%,112rem)] space-y-6">
        @include('admin.partials.status-flash')

        <div>
            <a
                href="{{ route('admin.purchase-receipts.index', ['warehouse_id' => $selectedWarehouseId]) }}"
                class="text-sm font-semibold text-sky-800 decoration-sky-300 underline-offset-2 hover:text-sky-950 hover:underline"
            >← К журналу поступлений</a>
        </div>

        @if ($warehouses->isEmpty())
            <div
                class="rounded-2xl border border-amber-200/90 bg-gradient-to-r from-amber-50 via-white to-orange-50/40 px-5 py-4 text-sm text-amber-950 shadow-sm ring-1 ring-amber-100/80"
            >
                <p class="font-semibold text-amber-950">Сначала заведите хотя бы один склад.</p>
                <p class="mt-2 text-amber-900/90">
                    <a
                        href="{{ route('admin.warehouses.create') }}"
                        class="font-semibold text-emerald-700 underline decoration-emerald-300 underline-offset-2 hover:text-emerald-800"
                    >Добавить склад</a>
                </p>
            </div>
        @else
            @if ((int) $selectedWarehouseId === 0)
            <div
                class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50"
            >
                <form
                    method="GET"
                    action="{{ route('admin.purchase-receipts.create') }}"
                    class="rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90 px-4 py-4 sm:px-6 sm:py-5"
                >
                    <x-input-label for="pr_warehouse_create" value="Склад документа *" />
                    <select
                        id="pr_warehouse_create"
                        name="warehouse_id"
                        class="mt-2 block w-full max-w-md rounded-xl border border-slate-200/90 bg-white py-2.5 pl-3 pr-10 text-sm text-slate-900 shadow-sm ring-1 ring-slate-900/5 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/25"
                        onchange="this.form.submit()"
                    >
                        @foreach ($warehouses as $w)
                            <option value="{{ $w->id }}" @selected((int) $w->id === (int) $selectedWarehouseId)>{{ $w->name }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            @endif

            @include('admin.purchase-receipts.partials.form-document-styles')

            @if ($selectedWarehouseId !== 0)
                @php
                    $prFormUrls = [
                        'goodsSearch' => route('admin.goods.search'),
                        'counterpartySearch' => route('admin.counterparties.search', ['for' => 'purchase']),
                        'counterpartyQuick' => route('admin.counterparties.quick-store'),
                    ];
                @endphp
                <script>
                    (function () {
                        if (window.obGenEan13) {
                            return;
                        }
                        window.obGenEan13 = function () {
                            var base = '2';
                            var a = new Uint8Array(11);
                            crypto.getRandomValues(a);
                            for (var i = 0; i < 11; i++) {
                                base += String(a[i] % 10);
                            }
                            var sum = 0;
                            for (var j = 0; j < 12; j++) {
                                var d = parseInt(base[j], 10);
                                sum += j % 2 === 0 ? d : d * 3;
                            }
                            var check = (10 - (sum % 10)) % 10;
                            return base + check;
                        };
                    })();
                </script>
                <script>
                    window.__purchaseReceiptInit = {
                        lines: @json($linesForForm),
                        urls: @json($prFormUrls),
                        supplierName: @json(old('supplier_name', '')),
                        warehouseId: {{ (int) $selectedWarehouseId }},
                        branchName: @json(auth()->user()->branch?->name ?? ''),
                        warehouseName: @json($warehouses->firstWhere('id', $selectedWarehouseId)?->name ?? ''),
                    };
                </script>
                <div @keydown.escape.window="closeAllSuggests()" @scroll.window="repositionOpenSuggests()" @resize.window="repositionOpenSuggests()" x-data="purchaseReceiptForm()" class="space-y-6">
                    <div
                        class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50"
                    >
                        <div class="rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90 px-4 py-4 sm:px-6 sm:py-5">
                            <div class="flex flex-wrap items-end gap-x-5 gap-y-4">
                                <form
                                    method="GET"
                                    action="{{ route('admin.purchase-receipts.create') }}"
                                    class="w-full shrink-0 sm:w-auto sm:min-w-[11rem] sm:max-w-[15rem]"
                                >
                                    <x-input-label for="pr_warehouse_create_in_form" value="Склад документа *" />
                                    <select
                                        id="pr_warehouse_create_in_form"
                                        name="warehouse_id"
                                        class="mt-2 block w-full rounded-xl border border-slate-200/90 bg-white py-2.5 pl-3 pr-10 text-sm text-slate-900 shadow-sm ring-1 ring-slate-900/5 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/25"
                                        onchange="this.form.submit()"
                                    >
                                        @foreach ($warehouses as $w)
                                            <option value="{{ $w->id }}" @selected((int) $w->id === (int) $selectedWarehouseId)>{{ $w->name }}</option>
                                        @endforeach
                                    </select>
                                </form>
                                <div class="relative min-w-[min(100%,14rem)] max-w-6xl flex-1" x-ref="headerGoodBlock">
                                    <label
                                        for="pr_header_good_q"
                                        class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-600"
                                    >Наименование товара</label>
                                    <div class="relative z-[10040]">
                                        <input
                                            id="pr_header_good_q"
                                            type="search"
                                            data-pr-header-good-input
                                            x-model="headerGoodQuery"
                                            @focus="onHeaderGoodFocus($event)"
                                            @input="onHeaderGoodInput($event)"
                                            @keydown.enter="onHeaderGoodEnter($event)"
                                            @blur="onHeaderGoodBlur()"
                                            autocomplete="off"
                                            placeholder="от 2 символов, Enter — в список"
                                            class="block w-full rounded-xl border border-slate-200/90 bg-white py-2 pl-3 pr-9 text-sm text-slate-900 shadow-sm ring-1 ring-slate-900/5 placeholder:text-slate-400 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/25"
                                        />
                                        <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">⌕</span>
                                    <template x-if="headerGoodOpen && (headerGoodLoading || headerGoodQuery.trim() !== '')">
                                        <div
                                            x-cloak
                                            class="absolute left-0 right-0 top-full z-[10050] mt-1 max-h-[min(24rem,70vh)] w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 text-left shadow-xl ring-1 ring-slate-300/90"
                                            role="listbox"
                                            @mousedown.prevent
                                        >
                                            <div x-show="headerGoodLoading" class="px-3 py-2 text-xs text-slate-500">Поиск…</div>
                                            <div
                                                x-show="!headerGoodLoading && headerGoodQuery.trim().length < 2 && headerGoodQuery.trim() !== ''"
                                                class="px-3 py-2 text-xs text-amber-800/90"
                                            >Введите не менее 2 символов</div>
                                            <div
                                                x-show="!headerGoodLoading && headerGoodQuery.trim().length >= 2 && headerGoodItems.length === 0"
                                                class="border-b border-slate-100 px-3 py-2"
                                            >
                                                <button
                                                    type="button"
                                                    class="w-full rounded-lg border border-emerald-300/90 bg-emerald-50 px-3 py-2 text-center text-[12px] font-semibold leading-snug text-emerald-950 shadow-sm hover:bg-emerald-100/95"
                                                    @mousedown.prevent.stop
                                                    @click.prevent.stop="appendLineFromHeaderFreeText()"
                                                >
                                                    Добавить новый товар
                                                </button>
                                            </div>
                                            <template x-for="item in headerGoodItems" :key="item.id">
                                                <div
                                                    role="option"
                                                    class="flex w-full items-stretch border-b border-slate-50 hover:bg-emerald-50/80"
                                                >
                                                    <button
                                                        type="button"
                                                        class="flex min-w-0 flex-1 flex-col items-stretch gap-0.5 px-3 py-2 text-left text-xs"
                                                        @mousedown.prevent="appendLineFromCatalogItem(item)"
                                                    >
                                                        <span class="font-medium text-slate-900" x-text="item.name"></span>
                                                        <div
                                                            class="mt-0.5 flex flex-wrap gap-1.5"
                                                            x-show="goodsSuggestHasWarehouseHint(item)"
                                                        >
                                                            <span
                                                                class="inline-flex rounded-lg border px-1.5 py-0.5 text-[10px] font-semibold"
                                                                :class="goodsStockQtySoldOut(item.stock_quantity)
                                                                    ? 'border-red-200/85 bg-gradient-to-r from-red-50/95 to-orange-50/40 text-red-800'
                                                                    : 'border-emerald-200/85 bg-gradient-to-r from-emerald-50/95 to-sky-50/50 text-teal-900'"
                                                                x-show="item.stock_quantity != null && item.stock_quantity !== ''"
                                                                x-text="'Остаток: ' + formatGoodsStockQty(item.stock_quantity) + (item.unit ? ' ' + item.unit : '')"
                                                            ></span>
                                                            <span
                                                                class="inline-flex rounded-lg border border-sky-200/80 bg-white px-1.5 py-0.5 text-[10px] font-medium text-slate-700"
                                                                x-show="item.opening_unit_cost != null && item.opening_unit_cost !== ''"
                                                                x-text="'Закуп. по складу: ' + formatGoodsUnitCost(item.opening_unit_cost)"
                                                            ></span>
                                                        </div>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="flex min-w-[3.75rem] shrink-0 flex-col items-center justify-center gap-0.5 border-l px-1.5 py-2 text-center text-xs transition-colors"
                                                        :class="String(copyFeedbackGoodId) === String(item.id) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-100 bg-white text-slate-400 hover:bg-slate-50 hover:text-teal-700'"
                                                        :title="String(copyFeedbackGoodId) === String(item.id) ? 'Скопировано в буфер обмена' : 'Копировать наименование'"
                                                        :aria-label="String(copyFeedbackGoodId) === String(item.id) ? 'Скопировано' : 'Копировать наименование'"
                                                        @mousedown.prevent.stop
                                                        @click.prevent.stop="copyGoodName(item, $event)"
                                                    >
                                                        <span x-show="String(copyFeedbackGoodId) !== String(item.id)" class="inline-flex shrink-0">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                            </svg>
                                                        </span>
                                                        <span
                                                            x-show="String(copyFeedbackGoodId) === String(item.id)"
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
                                </div>
                            </div>
                        </div>
                    </div>
                <form
                    method="POST"
                    action="{{ route('admin.purchase-receipts.store') }}"
                    class="w-full min-w-0 space-y-6"
                >
                    @csrf
                    <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}" />

                    <div
                        class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50"
                    >
                        <div class="overflow-hidden rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90">
                            <div class="ob-1c-scope overflow-hidden rounded-[1.5rem] bg-white/95">
                        <div
                            class="border-b border-emerald-200/55 bg-gradient-to-r from-emerald-50/95 via-white to-sky-50/50 px-4 py-3 sm:px-5"
                        >
                            <p class="mb-0.5 text-[10px] font-semibold uppercase tracking-wider text-teal-700/90">Закупки</p>
                            <h2 class="text-[15px] font-bold leading-tight text-slate-800">Новое поступление товаров</h2>
                        </div>

                        <div class="ob-1c-header">
                            <div class="relative" x-ref="supplierRoot">
                                <label for="supplier_name">Поставщик</label>
                                <input
                                    id="supplier_name"
                                    type="text"
                                    name="supplier_name"
                                    x-model="supplierName"
                                    autocomplete="organization"
                                    placeholder="Начните вводить наименование (2+ буквы)"
                                    @focus="onSupplierFocus($event)"
                                    @input="onSupplierInput($event)"
                                    @blur="onSupplierBlur()"
                                />
                                <div
                                    x-cloak
                                    x-show="showCpDropdown()"
                                    class="fixed z-[210] max-h-80 overflow-y-auto rounded border border-slate-300 bg-white py-1 text-left shadow-lg"
                                    role="listbox"
                                    :style="'top:' + cpSuggestPos.top + 'px;left:' + cpSuggestPos.left + 'px;width:' + cpSuggestPos.width + 'px'"
                                    @mousedown.prevent
                                >
                                    <div x-show="cpSuggestLoading" class="px-3 py-2 text-xs text-slate-500">Поиск контрагентов…</div>
                                    <template x-for="item in cpSuggestItems" :key="item.id">
                                        <button
                                            type="button"
                                            class="flex w-full flex-col items-start gap-0.5 px-3 py-1.5 text-left text-xs hover:bg-slate-100"
                                            role="option"
                                            @mousedown.prevent
                                            @click="pickCounterparty(item)"
                                        >
                                            <span class="text-slate-900" x-text="item.full_name || item.name"></span>
                                            <span class="text-[10px] text-slate-500" x-show="item.kind === 'supplier'">Поставщик</span>
                                        </button>
                                    </template>
                                    <div
                                        x-show="!cpSuggestLoading && cpSuggestNoHits && cpSuggestItems.length === 0 && !cpQuickOpen"
                                        class="border-t border-slate-100 px-3 py-2 text-xs text-slate-600"
                                    >
                                        <p class="mb-2">Нет совпадений в справочнике.</p>
                                        <button
                                            type="button"
                                            class="ob-tb-btn w-full justify-center"
                                            @mousedown.prevent
                                            @click="openCpQuickAdd($event)"
                                        >
                                            Добавить контрагента…
                                        </button>
                                    </div>
                                    <div
                                        x-show="cpQuickOpen"
                                        class="space-y-2 border-t border-slate-100 px-3 py-2"
                                    >
                                        <p class="text-[11px] font-semibold text-slate-800">Новый поставщик</p>
                                        <label class="block text-[10px] font-semibold text-slate-600" for="pr_create_cp_quick_legal_form">Правовая форма</label>
                                        <select
                                            id="pr_create_cp_quick_legal_form"
                                            x-model="cpQuickLegalForm"
                                            class="w-full rounded border border-slate-300 bg-white px-2 py-1 text-xs"
                                            @mousedown.stop
                                        >
                                            @foreach (\App\Models\Counterparty::legalFormLabels() as $k => $label)
                                                <option value="{{ $k }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <p x-show="cpQuickError" class="text-[11px] text-red-700" x-text="cpQuickError"></p>
                                        <div class="flex flex-wrap gap-2 pt-1">
                                            <button
                                                type="button"
                                                class="ob-tb-btn min-h-[24px] px-3 text-[11px]"
                                                :disabled="cpQuickSaving"
                                                @mousedown.prevent
                                                @click="submitCpQuickAdd()"
                                            >
                                                <span x-show="!cpQuickSaving">Создать и подставить</span>
                                                <span x-show="cpQuickSaving">Сохранение…</span>
                                            </button>
                                            <button
                                                type="button"
                                                class="ob-tb-btn min-h-[24px] px-3 text-[11px]"
                                                @mousedown.prevent
                                                @click="cpQuickOpen = false; cpQuickError = ''"
                                            >
                                                Отмена
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label for="document_date">Дата документа *</label>
                                <input
                                    id="document_date"
                                    type="date"
                                    name="document_date"
                                    value="{{ old('document_date', $defaultDocumentDate) }}"
                                    required
                                />
                            </div>
                        </div>

                        <div class="ob-1c-toolbar">
                            <button type="button" class="ob-tb-btn" @click="addRow()">Добавить</button>
                            <button type="button" class="ob-tb-btn ob-tb-btn-icon" title="Вверх" @click="moveUp()">▲</button>
                            <button type="button" class="ob-tb-btn ob-tb-btn-icon" title="Вниз" @click="moveDown()">▼</button>
                            <span class="mx-1 h-4 w-px bg-slate-300/90" aria-hidden="true"></span>
                            <span
                                class="inline-flex flex-wrap items-center gap-1"
                                title="Одна наценка для всех строк; продажная цена считается от закупки там, где она указана"
                            >
                                <label class="text-[10px] font-semibold text-slate-600 whitespace-nowrap" for="pr_bulk_markup_create">Наценка всем</label>
                                <input
                                    id="pr_bulk_markup_create"
                                    type="text"
                                    x-model="bulkMarkupPercent"
                                    class="h-[24px] w-[3.5rem] rounded-md border border-sky-200/90 bg-white px-1.5 text-center text-[11px] font-semibold text-slate-800 shadow-inner outline-none focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400/35"
                                    inputmode="decimal"
                                    placeholder="30"
                                    autocomplete="off"
                                    @keydown.enter.prevent="applyBulkMarkupToAllLines()"
                                />
                                <button type="button" class="ob-tb-btn" title="Подставить эту наценку во все строки и пересчитать продажу" @click="applyBulkMarkupToAllLines()">Ко всем</button>
                            </span>
                            <span class="mx-1 h-4 w-px bg-slate-300/90" aria-hidden="true"></span>
                            <button type="button" class="ob-tb-btn" title="EAN-13 только для пустых ячеек" @click="genBarcodesEmptyOnly()">Штрихкоды</button>
                            <span class="mx-1 h-4 w-px bg-slate-300/90" aria-hidden="true"></span>
                            <button type="button" class="ob-tb-btn" title="Печать по текущим полям (черновик). PDF — в диалоге печати." @click="openDraftPrint()">Печать</button>
                            <span class="mx-1 h-4 w-px bg-slate-300/90" aria-hidden="true"></span>
                            <button type="button" class="ob-tb-btn" title="Удалить выделенную строку таблицы (кликните строку или используйте Вверх/Вниз)" @click="removeSelectedRow()">Удалить строку</button>
                            <button type="button" class="ob-tb-btn" title="Очистить все поля выделенной строки без удаления" @click="clearSelectedRow()">Очистить строку</button>
                        </div>

                        <x-input-error class="mx-3 mt-2" :messages="$errors->get('lines')" />
                        <x-input-error class="mx-3 mt-2" :messages="$errors->get('warehouse_id')" />
                        <x-input-error class="mx-3 mt-2" :messages="$errors->get('document_date')" />

                        @php
                            $lineFieldErrors = collect($errors->getMessages())->filter(fn ($_, $k) => str_starts_with((string) $k, 'lines.'));
                        @endphp
                        @if ($lineFieldErrors->isNotEmpty())
                            <div class="mx-3 mt-2 rounded-xl border border-red-200/90 bg-red-50/95 px-3 py-2 text-[11px] text-red-900 shadow-sm">
                                <ul class="list-inside list-disc space-y-0.5">
                                    @foreach ($lineFieldErrors->flatten() as $msg)
                                        <li>{{ $msg }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="overflow-x-auto border-t border-slate-200/90">
                            <table
                                class="ob-1c-table"
                                @focusin="$event.target.classList.contains('ob-inp') && $event.target.select()"
                                @mouseup="$event.target.classList.contains('ob-inp') && $event.preventDefault()"
                            >
                                <thead>
                                    <tr>
                                        <th class="ob-num">N</th>
                                        <th>Наименование *</th>
                                        <th>Штрихкод</th>
                                        <th>Наценка %</th>
                                        <th>Ед. изм.</th>
                                        <th>Кол-во *</th>
                                        <th>Цена (закуп.) *</th>
                                        <th>Сумма</th>
                                        <th>Продажная цена</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(row, index) in lines" :key="index">
                                        <tr
                                            class="cursor-pointer"
                                            :class="{ 'ob-row-active': selectedRow === index }"
                                            @click="selectedRow = index"
                                        >
                                            <td class="ob-num" x-text="index + 1"></td>
                                            <td class="min-w-[10rem]">
                                                <input type="hidden" :name="`lines[${index}][article_code]`" x-model="row.article_code" autocomplete="off" />
                                                <input
                                                    type="text"
                                                    :name="`lines[${index}][name]`"
                                                    x-model="row.name"
                                                    class="ob-inp"
                                                    autocomplete="off"
                                                    @input="onNameInput(index, $event)"
                                                    @focus="onNameFocus(index, $event)"
                                                    @blur="onNameBlur()"
                                                />
                                            </td>
                                            <td class="min-w-[7.5rem]">
                                                <input
                                                    type="text"
                                                    :name="`lines[${index}][barcode]`"
                                                    x-model="row.barcode"
                                                    class="ob-inp font-mono text-[11px]"
                                                    inputmode="numeric"
                                                    autocomplete="off"
                                                />
                                            </td>
                                            <td class="min-w-[4.25rem] ob-numr">
                                                <input
                                                    type="text"
                                                    :name="`lines[${index}][markup_percent]`"
                                                    x-model="row.markup_percent"
                                                    class="ob-inp"
                                                    autocomplete="off"
                                                    placeholder="30"
                                                    inputmode="decimal"
                                                    title="Процент к закупочной цене; при вводе пересчитывается продажная цена"
                                                    @input="applySaleFromMarkup(index)"
                                                />
                                            </td>
                                            <td class="min-w-[3.5rem]">
                                                <input type="text" :name="`lines[${index}][unit]`" x-model="row.unit" class="ob-inp" autocomplete="off" />
                                            </td>
                                            <td class="min-w-[4rem] ob-numr">
                                                <input type="text" :name="`lines[${index}][quantity]`" x-model="row.quantity" class="ob-inp" inputmode="decimal" autocomplete="off" />
                                            </td>
                                            <td class="min-w-[4.5rem] ob-numr">
                                                <input type="text" :name="`lines[${index}][unit_price]`" x-model="row.unit_price" class="ob-inp" inputmode="decimal" autocomplete="off" @input="applySaleFromMarkup(index)" />
                                            </td>
                                            <td class="min-w-[4.5rem] ob-sum ob-numr">
                                                <input type="text" class="ob-inp" readonly tabindex="-1" :value="lineSum(row)" />
                                            </td>
                                            <td class="min-w-[4.5rem] ob-numr">
                                                <input type="text" :name="`lines[${index}][sale_price]`" x-model="row.sale_price" class="ob-inp" inputmode="decimal" autocomplete="off" />
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <template x-if="nameSuggestRow !== null && (nameSuggestLoading || nameSuggestItems.length > 0 || nameSuggestNoHits)">
                        <template x-teleport="body">
                        <div
                            x-cloak
                            class="fixed z-[10045] max-h-[min(24rem,70vh)] overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 text-left shadow-xl ring-1 ring-slate-300/90"
                            role="listbox"
                            :style="'top:' + suggestPos.top + 'px;left:' + suggestPos.left + 'px;width:' + suggestPos.width + 'px'"
                            @mousedown.prevent
                        >
                            <div x-show="nameSuggestLoading" class="px-3 py-2 text-xs text-slate-500">Поиск…</div>
                            <template x-for="item in nameSuggestItems" :key="item.id">
                                <div role="option" class="flex w-full items-stretch hover:bg-gradient-to-r hover:from-emerald-50/80 hover:to-sky-50/50">
                                    <button
                                        type="button"
                                        class="flex min-w-0 flex-1 flex-col items-stretch gap-0.5 px-3 py-1.5 text-left text-xs"
                                        @mousedown.prevent="pickGoodFromSuggest(item)"
                                    >
                                        <span class="font-medium text-slate-900" x-text="item.name"></span>
                                        <div
                                            class="mt-0.5 flex flex-wrap gap-1.5"
                                            x-show="goodsSuggestHasWarehouseHint(item)"
                                        >
                                            <span
                                                class="inline-flex max-w-full items-center rounded-lg border px-1.5 py-0.5 text-[10px] font-semibold leading-tight shadow-sm"
                                                :class="goodsStockQtySoldOut(item.stock_quantity)
                                                    ? 'border-red-200/85 bg-gradient-to-r from-red-50/95 to-orange-50/40 text-red-800'
                                                    : 'border-emerald-200/85 bg-gradient-to-r from-emerald-50/95 to-sky-50/50 text-teal-900'"
                                                x-show="item.stock_quantity != null && item.stock_quantity !== ''"
                                                x-text="'Остаток: ' + formatGoodsStockQty(item.stock_quantity) + (item.unit ? ' ' + item.unit : '')"
                                            ></span>
                                            <span
                                                class="inline-flex max-w-full items-center rounded-lg border border-sky-200/80 bg-white px-1.5 py-0.5 text-[10px] font-medium leading-tight text-slate-700 shadow-sm"
                                                x-show="item.opening_unit_cost != null && item.opening_unit_cost !== ''"
                                                x-text="'Закуп. по складу: ' + formatGoodsUnitCost(item.opening_unit_cost)"
                                            ></span>
                                        </div>
                                    </button>
                                    <button
                                        type="button"
                                        class="flex min-w-[3.75rem] shrink-0 flex-col items-center justify-center gap-0.5 border-l px-1.5 py-1.5 text-center text-xs transition-colors"
                                        :class="String(copyFeedbackGoodId) === String(item.id) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-100 bg-white text-slate-400 hover:bg-slate-50 hover:text-teal-700'"
                                        :title="String(copyFeedbackGoodId) === String(item.id) ? 'Скопировано в буфер обмена' : 'Копировать наименование'"
                                        :aria-label="String(copyFeedbackGoodId) === String(item.id) ? 'Скопировано' : 'Копировать наименование'"
                                        @mousedown.prevent.stop
                                        @click.prevent.stop="copyGoodName(item, $event)"
                                    >
                                        <span x-show="String(copyFeedbackGoodId) !== String(item.id)" class="inline-flex shrink-0">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                        </span>
                                        <span
                                            x-show="String(copyFeedbackGoodId) === String(item.id)"
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
                            <div
                                x-show="nameSuggestRow !== null && !nameSuggestLoading && nameSuggestItems.length === 0 && nameSuggestNoHits"
                                class="space-y-2 border-t border-slate-100 px-3 py-2.5"
                            >
                                <p class="text-[11px] leading-snug text-slate-600">
                                    Новый товар (в справочнике ещё нет записи) — наименование уже подставлено в ячейку. Укажите артикул, количество и цены ниже.
                                </p>
                                <button
                                    type="button"
                                    class="w-full rounded-lg border border-emerald-300/90 bg-emerald-50 px-3 py-2 text-[12px] font-semibold leading-snug text-emerald-950 shadow-sm hover:bg-emerald-100/95"
                                    @mousedown.prevent.stop
                                    @click.prevent.stop="closeNameSuggestKeepingTypedName()"
                                >
                                    Закрыть список и продолжить
                                </button>
                            </div>
                        </div>
                        </template>
                        </template>

                        <div class="ob-1c-foot">
                            <button type="submit" class="ob-tb-btn ob-btn-submit">Провести и закрыть</button>
                        </div>
                            </div>
                        </div>
                    </div>
                </form>
                </div>
            @else
                <div
                    class="rounded-2xl border border-slate-200/90 bg-gradient-to-b from-slate-50/80 to-white px-4 py-6 text-sm text-slate-600 shadow-sm ring-1 ring-slate-100/80"
                >
                    Выберите склад в форме выше, чтобы открыть таблицу строк документа.
                </div>
            @endif
        @endif
    </div>
</x-admin-layout>
