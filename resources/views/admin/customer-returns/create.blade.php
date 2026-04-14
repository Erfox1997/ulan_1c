<x-admin-layout pageTitle="Новый возврат от клиента" main-class="px-3 py-5 sm:px-4 lg:px-5">
    <div class="w-full min-w-0 space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200/80 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        <div>
            <a
                href="{{ route('admin.customer-returns.index', ['warehouse_id' => $selectedWarehouseId]) }}"
                class="text-sm font-medium text-emerald-800 hover:underline"
            >← К журналу возвратов</a>
        </div>

        @if ($warehouses->isEmpty())
            <div class="rounded-xl border border-amber-200/80 bg-amber-50 px-4 py-4 text-sm text-amber-950">
                <p class="font-medium">Сначала заведите хотя бы один склад.</p>
                <p class="mt-2 text-amber-900/90">
                    <a href="{{ route('admin.warehouses.create') }}" class="font-semibold text-emerald-800 underline hover:text-emerald-700">Добавить склад</a>
                </p>
            </div>
        @else
            <form
                method="GET"
                action="{{ route('admin.customer-returns.create') }}"
                class="rounded-xl border border-slate-200/90 bg-white px-5 py-4 shadow-sm ring-1 ring-slate-900/5"
            >
                <x-input-label for="cr_warehouse_create" value="Склад приёмки возврата *" />
                <select
                    id="cr_warehouse_create"
                    name="warehouse_id"
                    class="mt-2 block w-full max-w-md rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-10 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                    onchange="this.form.submit()"
                >
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}" @selected((int) $w->id === (int) $selectedWarehouseId)>{{ $w->name }}</option>
                    @endforeach
                </select>
            </form>

            <style>
                .ob-1c-scope { font-family: Tahoma, Arial, 'Segoe UI', sans-serif; font-size: 12px; color: #000; }
                .ob-1c-toolbar {
                    display: flex; flex-wrap: wrap; align-items: center; gap: 4px;
                    background: #f0f0f0; border: 1px solid #c0c0c0; padding: 4px 6px; margin-bottom: 0;
                }
                .ob-tb-btn {
                    display: inline-flex; align-items: center; justify-content: center; gap: 4px;
                    padding: 2px 10px; min-height: 22px; font-size: 11px; line-height: 1.2;
                    border: 1px solid #a0a0a0; background: linear-gradient(180deg, #fff 0%, #e8e8e8 100%);
                    color: #000; cursor: pointer; white-space: nowrap;
                }
                .ob-tb-btn:hover { background: linear-gradient(180deg, #fafafa 0%, #dedede 100%); }
                .ob-tb-btn:active { background: #d0d0d0; }
                .ob-tb-btn-icon { padding: 2px 6px; min-width: 24px; }
                .ob-1c-table { width: 100%; border-collapse: collapse; table-layout: auto; background: #fff; }
                .ob-1c-table th,
                .ob-1c-table td { border: 1px solid #c0c0c0; padding: 0; vertical-align: middle; }
                .ob-1c-table th {
                    background: #f0f0f0; font-weight: 600; text-align: left; padding: 4px 6px;
                    white-space: nowrap;
                }
                .ob-1c-table th.ob-num, .ob-1c-table td.ob-num { text-align: center; width: 2.25rem; color: #333; }
                .ob-1c-table .ob-inp {
                    display: block; width: 100%; min-height: 24px; margin: 0; padding: 2px 6px;
                    border: 0; background: transparent; font: inherit; color: #000;
                    outline: none; box-shadow: none;
                }
                .ob-1c-table .ob-inp:focus { background: #fffef5; }
                .ob-1c-table td.ob-numr .ob-inp { text-align: right; }
                .ob-1c-table td.ob-sum .ob-inp { text-align: right; color: #444; background: #f7f7f7; }
                .ob-row-active { background: #fff9c4 !important; }
                .ob-row-active .ob-inp { background: #fff9c4 !important; }
                .ob-row-active td.ob-sum .ob-inp { background: #fff9c4 !important; }
                .ob-more-wrap { position: relative; margin-left: auto; }
                .ob-more-dd {
                    position: absolute; right: 0; top: 100%; z-index: 40; margin-top: 2px;
                    min-width: 11rem; background: #fff; border: 1px solid #a0a0a0;
                    box-shadow: 2px 2px 6px rgba(0,0,0,.12);
                }
                .ob-more-dd button {
                    display: block; width: 100%; text-align: left; padding: 6px 10px; font-size: 11px;
                    border: 0; background: #fff; cursor: pointer;
                }
                .ob-more-dd button:hover { background: #e8f4fc; }
                .ob-1c-foot {
                    display: flex; justify-content: flex-end; gap: 8px; margin-top: 8px; padding-top: 8px;
                    border-top: 1px solid #c0c0c0;
                }
                .ob-1c-header {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 8px 16px;
                    padding: 8px 10px;
                    background: #fafafa;
                    border-bottom: 1px solid #c0c0c0;
                }
                @media (max-width: 640px) {
                    .ob-1c-header { grid-template-columns: 1fr; }
                }
                .ob-1c-header label { display: block; font-size: 11px; font-weight: 600; color: #333; margin-bottom: 2px; }
                .ob-1c-header input, .ob-1c-header select {
                    width: 100%; padding: 3px 6px; border: 1px solid #a0a0a0; font: inherit;
                    background: #fff;
                }
            </style>

            @if ($selectedWarehouseId !== 0)
                @php
                    $crFormUrls = [
                        'goodsSearch' => route('admin.goods.search'),
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
                <form
                    method="POST"
                    action="{{ route('admin.customer-returns.store') }}"
                    class="space-y-6"
                    @keydown.escape.window="closeAllSuggests()"
                    x-data="legalEntitySaleForm()"
                >
                    @csrf
                    <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}" />

                    <div class="ob-1c-scope rounded-sm border border-[#c0c0c0] bg-white p-0 shadow-sm">
                        <div class="border-b border-[#c0c0c0] bg-[#f5f5f5] px-3 py-2">
                            <h2 class="text-sm font-semibold text-neutral-800">Возврат от клиента</h2>
                            <p class="mt-0.5 text-[11px] text-neutral-600">Товары из номенклатуры филиала; оприходование на склад. Цена — по договорённости возврата (за единицу).</p>
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
                            <span class="mx-1 h-4 w-px bg-[#c0c0c0]" aria-hidden="true"></span>
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
                            <div class="mx-3 mt-2 border border-red-300 bg-red-50 px-3 py-2 text-[11px] text-red-900">
                                <ul class="list-inside list-disc space-y-0.5">
                                    @foreach ($lineFieldErrors->flatten() as $msg)
                                        <li>{{ $msg }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="overflow-x-auto border-t border-[#c0c0c0]">
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
                            class="fixed z-[200] max-h-56 overflow-y-auto rounded border border-slate-300 bg-white py-1 text-left shadow-lg"
                            role="listbox"
                            :style="'top:' + suggestPos.top + 'px;left:' + suggestPos.left + 'px;width:' + suggestPos.width + 'px'"
                        >
                            <div x-show="nameSuggestLoading" class="px-3 py-2 text-xs text-slate-500">Поиск…</div>
                            <template x-for="item in nameSuggestItems" :key="item.id">
                                <button
                                    type="button"
                                    class="flex w-full flex-col items-stretch gap-0.5 px-3 py-1.5 text-left text-xs hover:bg-slate-100"
                                    role="option"
                                    @mousedown.prevent="pickGoodFromSuggest(item)"
                                >
                                    <span class="text-slate-900" x-text="item.name"></span>
                                    <span class="font-mono text-[11px] text-slate-500" x-text="item.article_code"></span>
                                    <span
                                        class="text-[10px] text-slate-600"
                                        x-show="item.stock_quantity != null && item.stock_quantity !== ''"
                                        x-text="'Остаток на складе: ' + item.stock_quantity"
                                    ></span>
                                    <span
                                        class="text-[10px] text-emerald-800"
                                        x-show="item.sale_price != null && item.sale_price !== ''"
                                        x-text="'Цена в карточке: ' + item.sale_price"
                                    ></span>
                                </button>
                            </template>
                            <div
                                x-show="!nameSuggestLoading && nameSuggestItems.length === 0 && nameSuggestNoHits"
                                class="px-3 py-2 text-xs text-slate-500"
                            >
                                Нет совпадений в базе
                            </div>
                        </div>

                        <div class="ob-1c-foot px-3 pb-3">
                            <button
                                type="submit"
                                class="ob-tb-btn min-h-[26px] px-4 text-[12px] font-semibold"
                                style="background: linear-gradient(180deg, #ffffe0 0%, #f0e68c 100%); border-color: #b8a642;"
                            >
                                Провести и закрыть
                            </button>
                        </div>
                    </div>
                </form>
            @else
                <div class="rounded-xl border border-slate-200/80 bg-white px-4 py-6 text-sm text-slate-600 shadow-sm">
                    Выберите склад в форме выше, чтобы открыть таблицу строк документа.
                </div>
            @endif
        @endif
    </div>
</x-admin-layout>
