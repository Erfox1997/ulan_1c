@php
    $lesDocDate = old('document_date', $legalEntitySale->document_date->format('Y-m-d'));
@endphp
<x-admin-layout pageTitle="Редактирование реализации покупателю (юрлицо)" main-class="px-3 py-5 sm:px-5 lg:px-7 bg-[#f0f4f8]">
    @include('admin.partials.cp-brush')
    <div class="cp-root mx-auto w-full max-w-[min(100%,112rem)] space-y-6">
        @include('admin.partials.status-flash')

        <div>
            <a
                href="{{ route('admin.legal-entity-sales.index', ['warehouse_id' => $selectedWarehouseId]) }}"
                class="text-sm font-semibold text-sky-800 decoration-sky-300 underline-offset-2 hover:text-sky-950 hover:underline"
            >← К журналу реализаций</a>
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
            @include('admin.legal-entity-sales.partials.form-header-extra-styles')
            @include('admin.legal-entity-sales.partials.create-page-styles')

            @php
                $lesFormUrls = [
                    'goodsSearch' => route('admin.goods.search'),
                    'goodsQuickStore' => route('admin.goods.quick-store'),
                    'counterpartySearch' => route('admin.counterparties.search', ['for' => 'sale']),
                    'counterpartyQuick' => route('admin.counterparties.quick-store'),
                ];
            @endphp
            <script>
                window.__legalEntitySaleInit = {
                    lines: @json($linesForForm),
                    urls: @json($lesFormUrls),
                    buyerName: @json(old('buyer_name', $legalEntitySale->buyer_name)),
                    buyerPin: @json(old('buyer_pin', $legalEntitySale->buyer_pin ?? '')),
                    counterpartyId: @json(old('counterparty_id', $legalEntitySale->counterparty_id)),
                    warehouseId: {{ (int) $selectedWarehouseId }},
                    branchName: @json(auth()->user()->branch?->name ?? ''),
                    warehouseName: @json($legalEntitySale->warehouse->name ?? ''),
                };
            </script>
            <div
                x-data="legalEntitySaleForm()"
                @keydown.escape.window="closeAllSuggests()"
                @scroll.window="repositionLesLineNameSuggest()"
                @resize.window="repositionLesLineNameSuggest()"
                class="space-y-5"
            >
                {{-- Реквизиты документа: поля через form= (как на create) --}}
                <div
                    class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50"
                >
                    <div class="rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90 px-4 py-4 sm:px-6 sm:py-5 les-doc-top-card">
                        <div class="les-doc-top-fields">
                            <div class="les-field-cell min-w-0">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-teal-700/80">Склад отгрузки</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $legalEntitySale->warehouse->name }}</p>
                                <p class="mt-1 text-[10px] text-slate-500">Склад нельзя сменить после проведения.</p>
                            </div>

                            <div class="les-field-cell min-w-0" x-ref="buyerRoot">
                                <label for="buyer_name" class="sr-only">Покупатель</label>
                                <input
                                    id="buyer_name"
                                    type="text"
                                    name="buyer_name"
                                    form="les-legal-sale-form"
                                    x-model="buyerName"
                                    autocomplete="organization"
                                    placeholder="Покупатель"
                                    class="les-field-input placeholder:text-slate-400"
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
                                        <label class="block text-[10px] font-semibold text-slate-600" for="les_edit_cp_quick_legal_form">Правовая форма</label>
                                        <select
                                            id="les_edit_cp_quick_legal_form"
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

                            <div class="les-field-cell les-date-doc-wrap min-w-0">
                                <label for="document_date" class="sr-only">Дата документа (обязательно)</label>
                                <input
                                    id="document_date"
                                    type="date"
                                    name="document_date"
                                    form="les-legal-sale-form"
                                    value="{{ $lesDocDate }}"
                                    required
                                    class="les-field-input"
                                    aria-label="Дата документа, обязательное поле"
                                    title="Дата документа *"
                                />
                            </div>
                            <div class="les-field-cell les-buyer-pin-field min-w-0">
                                <label for="buyer_pin" class="sr-only">ИНН / ПИН покупателя</label>
                                <input
                                    id="buyer_pin"
                                    type="text"
                                    name="buyer_pin"
                                    form="les-legal-sale-form"
                                    x-model="buyerPin"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    maxlength="14"
                                    placeholder="ИНН / ПИН покупателя"
                                    class="les-field-input placeholder:text-slate-400"
                                />
                            </div>
                        </div>
                        <div class="les-comment-row mt-4 border-t border-slate-200/90 pt-4">
                            <label for="les_document_comment" class="sr-only">Комментарий к документу</label>
                            <textarea
                                id="les_document_comment"
                                name="comment"
                                form="les-legal-sale-form"
                                rows="1"
                                maxlength="5000"
                                class="les-doc-comment block w-full"
                                placeholder="Комментарий к документу (необязательно: условия оплаты, адрес доставки и т.п.)"
                            >{{ old('comment', $legalEntitySale->comment ?? '') }}</textarea>
                        </div>

                        <div class="mt-4 border-t border-slate-200/80 pt-3">
                            <x-input-error :messages="$errors->get('warehouse_id')" />
                            <x-input-error class="mt-1" :messages="$errors->get('document_date')" />
                            <x-input-error class="mt-1" :messages="$errors->get('buyer_name')" />
                            <x-input-error class="mt-1" :messages="$errors->get('buyer_pin')" />
                            <x-input-error class="mt-1" :messages="$errors->get('comment')" />
                        </div>
                    </div>
                </div>

                <form
                    id="les-legal-sale-form"
                    method="POST"
                    action="{{ route('admin.legal-entity-sales.update', $legalEntitySale) }}"
                    class="flex min-h-0 min-w-0 flex-1 flex-col gap-5"
                >
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}" />
                    <input type="hidden" name="counterparty_id" x-bind:value="counterpartyId !== null ? counterpartyId : ''" />

                    <div
                        class="les-create-page grid min-h-0 w-full min-w-0 max-w-full grid-cols-1 items-stretch gap-5"
                    >
                        <div class="relative z-20 flex h-full min-h-0 min-w-0 flex-col overflow-visible">
                            <div class="pr-panel-shell flex h-full min-h-0 min-w-0 flex-col overflow-visible border border-teal-800/12 bg-white shadow-[0_4px_24px_-8px_rgba(15,23,42,0.12)] ring-1 ring-teal-900/[0.05]">
                                <div class="pr-panel-header-teal shrink-0">
                                    <label for="les_header_good_q">Наименование товара</label>
                                </div>
                                <div class="relative flex min-h-0 flex-1 flex-col bg-white px-4 pb-5 pt-4 sm:px-[1.125rem] sm:pb-6 sm:pt-5">
                                    <div class="relative z-[59990] min-h-0 w-full shrink-0">
                                        @include('admin.legal-entity-sales.partials.header-goods-search-inner', [
                                            'hideOuterLabel' => true,
                                            'lesHeaderOuterClass' => 'relative w-full min-w-0',
                                            'useHeaderSearchSvgIcon' => true,
                                            'lesHeaderPlaceholder' => 'Название, от 2 символов — Enter в таблицу',
                                            'lesHeaderSearchInputClass' => 'box-border min-h-[3.125rem] w-full min-w-0 max-w-full rounded-[0.625rem] border border-sky-300/90 bg-white py-3 pl-[0.875rem] pr-11 text-[15px] leading-snug text-slate-800 shadow-[inset_0_1px_2px_rgba(15,23,42,0.03)] placeholder:text-slate-400 transition-colors focus:border-[#008b8b] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#008b8b]/20',
                                        ])
                                    </div>
                                    <div class="min-h-0 flex-1" aria-hidden="true"></div>
                                </div>
                            </div>
                        </div>

                        <div class="flex h-full min-h-0 min-w-0 w-full max-w-full flex-col">
                            <div class="pr-cart-card flex min-h-0 flex-1 flex-col overflow-hidden border bg-white ring-1 ring-teal-900/[0.05]">
                                <div class="pr-panel-header-teal shrink-0">
                                    Реализация (юрлицо)
                                </div>
                                <p class="border-b border-teal-900/10 bg-slate-50/90 px-3 py-2 text-[11px] leading-snug text-slate-600">
                                    Документ № <span class="font-mono font-semibold text-slate-800">{{ $legalEntitySale->id }}</span>
                                    · склад <span class="font-medium text-slate-800">{{ $legalEntitySale->warehouse->name }}</span>
                                    · сохранение пересчитывает остатки.
                                </p>
                                <div class="ob-1c-scope flex min-h-0 flex-1 flex-col overflow-hidden bg-white">

                                    <x-input-error class="mx-3 mt-3 sm:mt-4" :messages="$errors->get('lines')" />

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

                                    <div class="min-h-0 flex-1 overflow-x-auto">
                                        <table
                                            class="ob-1c-table les-sale-lines-table"
                                            @focusin="$event.target.classList.contains('ob-inp') && $event.target.select()"
                                            @mouseup="$event.target.classList.contains('ob-inp') && $event.preventDefault()"
                                        >
                                            <colgroup>
                                                <col style="width: 2.25rem" />
                                                <col />
                                                <col style="width: 7rem" />
                                                <col style="width: 4rem" />
                                                <col style="width: 4.5rem" />
                                                <col style="width: 6.25rem" />
                                                <col style="width: 5.25rem" />
                                                <col style="width: 2.25rem" />
                                            </colgroup>
                                            <thead>
                                                <tr>
                                                    <th class="ob-num">N</th>
                                                    <th>Наименование *</th>
                                                    <th>Штрихкод</th>
                                                    <th>Ед. изм.</th>
                                                    <th>Кол-во *</th>
                                                    <th>Цена продажи *</th>
                                                    <th>Сумма</th>
                                                    <th class="pr-line-remove-col" aria-hidden="true"><span class="sr-only">Удалить</span></th>
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
                                                        <td class="les-col-name">
                                                            <input type="hidden" :name="`lines[${index}][article_code]`" x-model="row.article_code" autocomplete="off" />
                                                            <input type="hidden" :name="`lines[${index}][category]`" x-model="row.category" autocomplete="off" />
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
                                                        <td>
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
                                                        <td>
                                                            <input type="text" :name="`lines[${index}][unit]`" x-model="row.unit" class="ob-inp" autocomplete="off" />
                                                        </td>
                                                        <td class="ob-numr">
                                                            <input type="text" :name="`lines[${index}][quantity]`" x-model="row.quantity" class="ob-inp" inputmode="decimal" autocomplete="off" />
                                                        </td>
                                                        <td class="ob-numr">
                                                            <input type="text" :name="`lines[${index}][unit_price]`" x-model="row.unit_price" class="ob-inp" inputmode="decimal" autocomplete="off" />
                                                        </td>
                                                        <td class="ob-sum ob-numr">
                                                            <input type="text" class="ob-inp" readonly tabindex="-1" :value="lineSum(row)" />
                                                        </td>
                                                        <td class="pr-line-remove-cell">
                                                            <button
                                                                type="button"
                                                                class="pr-line-remove-btn"
                                                                title="Убрать строку"
                                                                aria-label="Убрать строку из документа"
                                                                :disabled="lines.length <= 1"
                                                                @click.stop.prevent="removeLineAt(index)"
                                                            >
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div
                                        x-cloak
                                        x-show="nameSuggestRow !== null && (nameSuggestLoading || nameSuggestItems.length > 0 || nameSuggestNoHits)"
                                        class="fixed z-[10046] max-h-56 overflow-y-auto rounded-xl border border-slate-200/90 bg-white py-1 text-left shadow-[0_12px_40px_-8px_rgba(15,23,42,0.18)] ring-1 ring-slate-900/5"
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
                                        <button type="submit" class="ob-tb-btn ob-btn-submit">Сохранить изменения</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                @include('admin.partials.good-quick-create-modal', ['idPrefix' => 'les'])
            </div>
        @endif
    </div>
</x-admin-layout>
