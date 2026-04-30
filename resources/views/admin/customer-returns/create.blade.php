<x-admin-layout pageTitle="Новый возврат от клиента" main-class="px-3 py-6 sm:px-6 lg:px-8">
    @include('admin.partials.cp-brush')
    <div class="cp-root mx-auto w-full max-w-[min(100%,112rem)] space-y-6">
        @include('admin.partials.status-flash')

        <div>
            <a
                href="{{ route('admin.customer-returns.index', ['warehouse_id' => $selectedWarehouseId]) }}"
                class="text-sm font-semibold text-sky-800 decoration-sky-300 underline-offset-2 hover:text-sky-950 hover:underline"
            >← К журналу возвратов</a>
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
            @include('admin.purchase-receipts.partials.form-document-styles')

            @if ((int) $selectedWarehouseId === 0)
                <div
                    class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50"
                >
                    <form
                        method="GET"
                        action="{{ route('admin.customer-returns.create') }}"
                        class="rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90 px-4 py-4 sm:px-6 sm:py-5"
                    >
                        <x-input-label for="cr_warehouse_create" value="Склад приёмки возврата *" />
                        <select
                            id="cr_warehouse_create"
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

                <div
                    class="rounded-2xl border border-slate-200/90 bg-gradient-to-b from-slate-50/80 to-white px-4 py-6 text-sm text-slate-600 shadow-sm ring-1 ring-slate-100/80"
                >
                    Выберите склад в форме выше, чтобы открыть таблицу строк документа.
                </div>
            @else
                @php
                    $crFormUrls = [
                        'goodsSearch' => route('admin.goods.search'),
                        'goodsQuickStore' => route('admin.goods.quick-store'),
                        'counterpartySearch' => route('admin.counterparties.search', ['for' => 'sale']),
                        'counterpartyQuick' => route('admin.counterparties.quick-store'),
                    ];
                @endphp
                <script>
                    window.__customerReturnInit = {
                        lines: @json($linesForForm),
                        urls: @json($crFormUrls),
                        buyerName: @json(old('buyer_name', '')),
                        warehouseId: {{ (int) $selectedWarehouseId }},
                        branchName: @json(auth()->user()->branch?->name ?? ''),
                        warehouseName: @json($warehouses->firstWhere('id', $selectedWarehouseId)?->name ?? ''),
                    };
                </script>
                <div
                    class="space-y-6"
                    x-data="legalEntitySaleForm()"
                    @keydown.escape.window="closeAllSuggests()"
                    @scroll.window="repositionLesLineNameSuggest()"
                    @resize.window="repositionLesLineNameSuggest()"
                >
                    <div
                        class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50"
                    >
                        <div class="rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90 px-4 py-4 sm:px-6 sm:py-5">
                            <div class="flex flex-wrap items-end gap-x-5 gap-y-4">
                                <form
                                    method="GET"
                                    action="{{ route('admin.customer-returns.create') }}"
                                    class="w-full shrink-0 sm:w-auto sm:min-w-[11rem] sm:max-w-[15rem]"
                                >
                                    <x-input-label for="cr_warehouse_document" value="Склад приёмки возврата *" />
                                    <select
                                        id="cr_warehouse_document"
                                        name="warehouse_id"
                                        class="mt-2 block w-full rounded-xl border border-slate-200/90 bg-white py-2.5 pl-3 pr-10 text-sm text-slate-900 shadow-sm ring-1 ring-slate-900/5 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/25"
                                        onchange="this.form.submit()"
                                    >
                                        @foreach ($warehouses as $w)
                                            <option value="{{ $w->id }}" @selected((int) $w->id === (int) $selectedWarehouseId)>{{ $w->name }}</option>
                                        @endforeach
                                    </select>
                                </form>
                                @include('admin.legal-entity-sales.partials.header-goods-search-inner')
                            </div>
                        </div>
                    </div>

                    <form
                        method="POST"
                        action="{{ route('admin.customer-returns.store') }}"
                        class="space-y-6"
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
                                        <p class="mb-0.5 text-[10px] font-semibold uppercase tracking-wider text-teal-700/90">Продажи</p>
                                        <h2 class="text-[15px] font-bold leading-tight text-slate-800">Возврат от клиента</h2>
                                        <p class="mt-1.5 text-[11px] leading-snug text-slate-600">
                                            Товары из номенклатуры филиала; оприходование на склад. Цена — по договорённости возврата (за единицу).
                                        </p>
                                    </div>

                                    <div class="ob-1c-header">
                                        <div class="relative" x-ref="buyerRoot">
                                            <label for="buyer_name">Клиент</label>
                                            <input
                                                id="buyer_name"
                                                type="text"
                                                name="buyer_name"
                                                x-model="buyerName"
                                                autocomplete="organization"
                                                placeholder="Начните вводить наименование (2+ буквы)"
                                                @focus="onBuyerFocus($event)"
                                                @input="onBuyerInput($event)"
                                                @blur="onBuyerBlur()"
                                            />
                                            <div
                                                x-cloak
                                                x-show="showCpDropdown()"
                                                class="fixed z-[210] max-h-80 overflow-y-auto rounded-xl border border-slate-200/90 bg-white py-1 text-left shadow-[0_12px_40px_-8px_rgba(15,23,42,0.18)] ring-1 ring-slate-900/5"
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
                                                        <span class="text-[10px] text-slate-500" x-show="item.kind === 'buyer'">Покупатель</span>
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
                                                        Добавить покупателя…
                                                    </button>
                                                </div>
                                                <div
                                                    x-show="cpQuickOpen"
                                                    class="space-y-2 border-t border-slate-100 px-3 py-2"
                                                >
                                                    <p class="text-[11px] font-semibold text-slate-800">Новый покупатель</p>
                                                    <label class="block text-[10px] font-semibold text-slate-600" for="cr_cp_quick_legal_form">Правовая форма</label>
                                                    <select
                                                        id="cr_cp_quick_legal_form"
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
                                        <button type="button" class="ob-tb-btn" title="Черновик печати по полям формы" @click="openDraftPrint()">Печать черновика</button>
                                        <div class="ob-more-wrap" @keydown.escape.window="moreOpen = false">
                                            <button type="button" class="ob-tb-btn" @click="moreOpen = !moreOpen" :aria-expanded="moreOpen">Ещё ▾</button>
                                            <div
                                                x-cloak
                                                x-show="moreOpen"
                                                @click.outside="moreOpen = false"
                                                class="ob-more-dd"
                                                x-transition
                                            >
                                                <button type="button" @click="removeSelectedRow(); moreOpen = false">Удалить строку</button>
                                            </div>
                                        </div>
                                    </div>

                                    <x-input-error class="mx-3 mt-2" :messages="$errors->get('lines')" />
                                    <x-input-error class="mx-3 mt-2" :messages="$errors->get('warehouse_id')" />
                                    <x-input-error class="mx-3 mt-2" :messages="$errors->get('document_date')" />
                                    <x-input-error class="mx-3 mt-2" :messages="$errors->get('buyer_name')" />

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
                                                    <th>Категория</th>
                                                    <th>Артикул *</th>
                                                    <th>Ед. изм.</th>
                                                    <th>Кол-во *</th>
                                                    <th>Цена возврата *</th>
                                                    <th>Сумма</th>
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
                                                            <input type="hidden" :name="`lines[${index}][good_id]`" x-model="row.good_id" />
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
                                                                @input="onBarcodeInput(index, $event)"
                                                                @focus="onBarcodeFocus(index, $event)"
                                                                @blur="onBarcodeBlur()"
                                                            />
                                                        </td>
                                                        <td class="min-w-[6rem]">
                                                            <input
                                                                type="text"
                                                                :name="`lines[${index}][category]`"
                                                                x-model="row.category"
                                                                class="ob-inp"
                                                                autocomplete="off"
                                                            />
                                                        </td>
                                                        <td class="min-w-[6rem]">
                                                            <input type="text" :name="`lines[${index}][article_code]`" x-model="row.article_code" class="ob-inp font-mono text-[11px]" autocomplete="off" />
                                                        </td>
                                                        <td class="min-w-[3.5rem]">
                                                            <input type="text" :name="`lines[${index}][unit]`" x-model="row.unit" class="ob-inp" autocomplete="off" />
                                                        </td>
                                                        <td class="min-w-[4rem] ob-numr">
                                                            <input type="text" :name="`lines[${index}][quantity]`" x-model="row.quantity" class="ob-inp" inputmode="decimal" autocomplete="off" />
                                                        </td>
                                                        <td class="min-w-[4.5rem] ob-numr">
                                                            <input type="text" :name="`lines[${index}][unit_price]`" x-model="row.unit_price" class="ob-inp" inputmode="decimal" autocomplete="off" />
                                                        </td>
                                                        <td class="min-w-[4.5rem] ob-sum ob-numr">
                                                            <input type="text" class="ob-inp" readonly tabindex="-1" :value="lineSum(row)" />
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div
                                        x-cloak
                                        x-show="nameSuggestRow !== null && (nameSuggestLoading || nameSuggestItems.length > 0 || nameSuggestNoHits)"
                                        class="fixed z-[200] max-h-56 overflow-y-auto rounded-xl border border-slate-200/90 bg-white/95 py-1 text-left shadow-lg ring-1 ring-slate-200/40"
                                        role="listbox"
                                        :style="'top:' + suggestPos.top + 'px;left:' + suggestPos.left + 'px;width:' + suggestPos.width + 'px'"
                                    >
                                        <div x-show="nameSuggestLoading" class="px-3 py-2 text-xs text-slate-500">Поиск…</div>
                                        <template x-for="item in nameSuggestItems" :key="item.id">
                                            <button
                                                type="button"
                                                class="flex w-full flex-col items-stretch gap-0.5 px-3 py-1.5 text-left text-xs hover:bg-gradient-to-r hover:from-emerald-50/80 hover:to-sky-50/50"
                                                role="option"
                                                @mousedown.prevent="pickGoodFromSuggest(item)"
                                            >
                                                <span class="font-medium text-slate-900" x-text="item.name"></span>
                                                @include('admin.partials.goods-suggest-meta-pills')
                                            </button>
                                        </template>
                                        <div
                                            x-show="!nameSuggestLoading && nameSuggestItems.length === 0 && nameSuggestNoHits"
                                            class="px-3 py-2 text-xs text-slate-500"
                                        >
                                            Нет совпадений в базе
                                        </div>
                                    </div>

                                    <div class="ob-1c-foot">
                                        <button type="submit" class="ob-tb-btn ob-btn-submit">Провести и закрыть</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    @include('admin.partials.good-quick-create-modal', ['idPrefix' => 'les'])
                </div>
            @endif
        @endif
    </div>
</x-admin-layout>
