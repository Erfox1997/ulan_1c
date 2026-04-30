@php
    $prtDocDate = old('document_date', $purchaseReturn->document_date->format('Y-m-d'));
@endphp
<x-admin-layout pageTitle="Возврат поставщику (редактирование)" main-class="px-3 py-5 sm:px-5 lg:px-7 bg-[#f0f4f8]">
    @include('admin.partials.cp-brush')
    <div class="cp-root mx-auto w-full max-w-[min(100%,112rem)] space-y-6">
        @include('admin.partials.status-flash')

        <div>
            <a
                href="{{ route('admin.purchase-returns.index', ['warehouse_id' => $selectedWarehouseId]) }}"
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
            @include('admin.purchase-returns.partials.create-page-styles')

            @php
                $prtFormUrls = [
                    'goodsSearch' => route('admin.goods.search'),
                    'goodsQuickStore' => route('admin.goods.quick-store'),
                    'counterpartySearch' => route('admin.counterparties.search', ['for' => 'purchase']),
                    'counterpartyQuick' => route('admin.counterparties.quick-store'),
                ];
            @endphp
            <script>
                window.__purchaseReturnInit = {
                    lines: @json($linesForForm),
                    urls: @json($prtFormUrls),
                    supplierName: @json(old('supplier_name', $purchaseReturn->supplier_name)),
                    warehouseId: {{ (int) $selectedWarehouseId }},
                    branchName: @json(auth()->user()->branch?->name ?? ''),
                    warehouseName: @json($purchaseReturn->warehouse->name ?? ''),
                    purchaseReturnSwitchWarehouseUrl: '',
                    openFinalizeOnLoad: {{ ($errors->has('warehouse_id') || $errors->has('supplier_name') || $errors->has('document_date')) ? 'true' : 'false' }},
                };
            </script>
            <div
                x-init="openFinalizeIfNeeded()"
                @keydown.escape.window="purchaseReturnEscape()"
                @scroll.window="repositionOpenSuggests()"
                @resize.window="repositionOpenSuggests()"
                x-data="purchaseReturnForm()"
                class="prt-create-page grid min-h-0 w-full min-w-0 max-w-full grid-cols-1 items-stretch gap-5"
            >
                {{-- Левая колонка --}}
                <div class="relative z-20 flex h-full min-h-0 min-w-0 flex-col overflow-visible">
                    <div class="pr-panel-shell flex h-full min-h-0 min-w-0 flex-col overflow-visible border border-teal-800/12 bg-white shadow-[0_4px_24px_-8px_rgba(15,23,42,0.12)] ring-1 ring-teal-900/[0.05]">
                        <div class="pr-panel-header-teal shrink-0">
                            <label for="prt_header_good_q">Наименование товара</label>
                        </div>
                        <div class="relative flex min-h-[min(24rem,50vh)] min-w-0 flex-1 flex-col bg-white px-4 pb-5 pt-4 sm:px-[1.125rem] sm:pb-6 sm:pt-5" x-ref="prtHeaderGoodBlock">
                            @include('admin.purchase-returns.partials.header-goods-search-inner')
                            <div class="min-h-0 flex-1" aria-hidden="true"></div>
                        </div>
                    </div>
                </div>

                {{-- Правая колонка --}}
                <div class="flex h-full min-h-0 min-w-0 w-full max-w-full flex-col">
                    <form
                        id="prt-purchase-return-form"
                        method="POST"
                        action="{{ route('admin.purchase-returns.update', $purchaseReturn) }}"
                        class="flex h-full min-h-0 min-w-0 flex-1 flex-col"
                    >
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="warehouse_id" value="{{ (int) $selectedWarehouseId }}" />

                        <div class="pr-cart-card flex min-h-0 flex-1 flex-col overflow-hidden border bg-white ring-1 ring-teal-900/[0.05]">
                            <div class="pr-panel-header-teal shrink-0">
                                Корзина
                            </div>
                            <p class="border-b border-teal-900/10 bg-slate-50/90 px-3 py-2 text-[11px] leading-snug text-slate-600">
                                Документ № <span class="font-mono font-semibold text-slate-800">{{ $purchaseReturn->id }}</span>
                                · склад <span class="font-medium text-slate-800">{{ $purchaseReturn->warehouse->name }}</span>
                                · пересохранение пересчитывает остатки.
                            </p>
                            <div class="ob-1c-scope flex min-h-0 flex-1 flex-col overflow-hidden bg-white">
                                <div class="shrink-0">
                                    <x-input-error class="mx-3 mt-3 sm:mt-4" :messages="$errors->get('lines')" />
                                    <x-input-error class="mx-3 mt-2" :messages="$errors->get('warehouse_id')" />
                                    <x-input-error class="mx-3 mt-2" :messages="$errors->get('supplier_name')" />
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
                                </div>

                                <div class="min-h-0 flex-1 overflow-x-auto">
                                    <table
                                        class="ob-1c-table"
                                        @focusin="$event.target.classList.contains('ob-inp') && $event.target.select()"
                                        @mouseup="$event.target.classList.contains('ob-inp') && $event.preventDefault()"
                                    >
                                        <thead>
                                            <tr>
                                                <th class="ob-num">N</th>
                                                <th class="min-w-[10rem]">Наименование *</th>
                                                <th>Ед. изм.</th>
                                                <th>Кол-во *</th>
                                                <th>Цена возврата *</th>
                                                <th>Сумма</th>
                                                <th class="pr-line-remove-col" aria-hidden="true"></th>
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
                                                        <input type="hidden" :name="`lines[${index}][barcode]`" x-model="row.barcode" autocomplete="off" />
                                                        <input type="hidden" :name="`lines[${index}][article_code]`" x-model="row.article_code" autocomplete="off" />
                                                        <input type="hidden" :name="`lines[${index}][category]`" x-model="row.category" autocomplete="off" />
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
                                                    <td class="pr-line-remove-cell">
                                                        <button
                                                            type="button"
                                                            class="pr-line-remove-btn"
                                                            title="Убрать строку"
                                                            aria-label="Убрать строку из корзины"
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
                                                <button
                                                    type="button"
                                                    class="flex w-full flex-col items-stretch gap-0.5 px-3 py-1.5 text-left text-xs hover:bg-gradient-to-r hover:from-emerald-50/80 hover:to-sky-50/50"
                                                    role="option"
                                                    @mousedown.prevent="pickGoodFromSuggest(item)"
                                                >
                                                    <span class="font-medium text-slate-900" x-text="item.name"></span>
                                                    <div
                                                        class="mt-0.5 flex flex-wrap gap-1.5"
                                                        x-show="goodsSuggestHasReturnHint(item)"
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
                                                        <span
                                                            class="inline-flex max-w-full items-center rounded-lg border border-violet-200/75 bg-violet-50/80 px-1.5 py-0.5 text-[10px] font-medium leading-tight text-violet-900 shadow-sm"
                                                            x-show="item.sale_price != null && item.sale_price !== ''"
                                                            x-text="'Цена в карточке: ' + formatGoodsUnitCost(item.sale_price)"
                                                        ></span>
                                                    </div>
                                                </button>
                                            </template>
                                            <div
                                                x-show="nameSuggestRow !== null && !nameSuggestLoading && nameSuggestItems.length === 0 && nameSuggestNoHits"
                                                class="px-3 py-2 text-xs text-slate-500"
                                            >
                                                Нет совпадений в базе
                                            </div>
                                        </div>
                                    </template>
                                </template>

                                <div class="shrink-0 border-t border-slate-100 bg-slate-50/50 px-3 py-2">
                                    @if ($organizations->isNotEmpty())
                                        <div
                                            class="flex max-w-full flex-wrap items-center gap-x-2 gap-y-1.5 text-[11px]"
                                            x-data="{ printOrgId: {{ $defaultPrintOrganizationId !== null ? (int) $defaultPrintOrganizationId : 'null' }} }"
                                        >
                                            <span class="font-semibold text-slate-600">Печать накладной:</span>
                                            <select
                                                x-model="printOrgId"
                                                class="max-w-[11rem] rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] text-slate-900 shadow-sm"
                                            >
                                                @foreach ($organizations as $o)
                                                    <option value="{{ $o->id }}">{{ $o->name }}</option>
                                                @endforeach
                                            </select>
                                            <a
                                                :href="`{{ route('admin.purchase-returns.print', $purchaseReturn) }}${printOrgId != null ? '?organization_id=' + printOrgId : ''}`"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="font-semibold text-teal-800 underline decoration-teal-300 underline-offset-2 hover:text-teal-950"
                                            >Открыть</a>
                                            <span class="text-slate-400">·</span>
                                            <a
                                                :href="`{{ route('admin.purchase-returns.pdf', $purchaseReturn) }}${printOrgId != null ? '?organization_id=' + printOrgId : ''}`"
                                                class="font-semibold text-teal-800 underline decoration-teal-300 underline-offset-2 hover:text-teal-950"
                                            >PDF</a>
                                        </div>
                                    @else
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px]">
                                            <a
                                                href="{{ route('admin.purchase-returns.print', $purchaseReturn) }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="font-semibold text-teal-800 underline decoration-teal-300 underline-offset-2 hover:text-teal-950"
                                            >Печать накладной</a>
                                            <a
                                                href="{{ route('admin.purchase-returns.pdf', $purchaseReturn) }}"
                                                class="font-semibold text-teal-800 underline decoration-teal-300 underline-offset-2 hover:text-teal-950"
                                            >Скачать PDF</a>
                                        </div>
                                    @endif
                                </div>

                                <div class="ob-1c-foot flex shrink-0 flex-wrap items-center gap-2 px-3 py-3">
                                    <button
                                        type="button"
                                        class="ob-tb-btn inline-flex items-center gap-1.5 px-3 py-1.5 text-xs"
                                        @click="openDraftPrint()"
                                    >
                                        Печать черновика
                                    </button>
                                    <button
                                        type="button"
                                        class="ob-tb-btn ob-btn-submit prt-checkout-btn ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 !text-xs"
                                        @click="openFinalizeModal()"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                        </svg>
                                        Сохранить
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <template x-teleport="body">
                        <div
                            x-show="finalizeModalOpen"
                            x-cloak
                            class="fixed inset-0 z-[400] flex justify-center overflow-y-auto px-4 py-10 sm:py-16"
                            role="dialog"
                            aria-modal="true"
                            aria-labelledby="prt-edit-finalize-title"
                        >
                            <div class="fixed inset-0 z-0 bg-slate-900/45 backdrop-blur-[2px]" @click.prevent="closeFinalizeModal()" aria-hidden="true"></div>
                            <div
                                class="relative z-10 mt-8 flex w-full max-w-md flex-col self-start overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-[0_25px_50px_-12px_rgba(15,23,42,0.22)] sm:mt-12"
                                @click.stop
                            >
                                <div class="bg-[#008b8b] px-5 py-4 text-white">
                                    <h3 id="prt-edit-finalize-title" class="text-base font-bold tracking-tight">Сохранение возврата</h3>
                                    <p class="mt-1.5 text-sm font-normal leading-snug text-white/85">
                                        Уточните поставщика и дату документа. Склад после проведения сменить нельзя.
                                    </p>
                                </div>
                                <div class="space-y-5 px-5 py-5">
                                    <div>
                                        <p class="mb-0 text-sm font-semibold text-slate-700">Склад документа</p>
                                        <p class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-900">{{ $purchaseReturn->warehouse->name }}</p>
                                    </div>
                                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                        <div class="relative min-w-0" x-ref="supplierRoot">
                                            <label for="prt_finalize_supplier" class="mb-0 block text-sm font-semibold text-slate-700">Поставщик</label>
                                            <input
                                                id="prt_finalize_supplier"
                                                type="text"
                                                name="supplier_name"
                                                form="prt-purchase-return-form"
                                                x-model="supplierName"
                                                autocomplete="off"
                                                autocorrect="off"
                                                autocapitalize="off"
                                                spellcheck="false"
                                                data-lpignore="true"
                                                data-1p-ignore=""
                                                data-form-type="other"
                                                placeholder="Наименование (от 2 букв)"
                                                class="relative z-10 mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm transition-colors placeholder:text-slate-400 focus:border-[#008b8b] focus:outline-none focus:ring-2 focus:ring-[#008b8b]/20"
                                                @focus="onSupplierFocus($event)"
                                                @input="onSupplierInput($event)"
                                                @blur="onSupplierBlur()"
                                            />
                                            <div
                                                x-cloak
                                                x-show="cpQuickOpen || (((supplierName || '').trim().length >= 2) && (cpSuggestLoading || cpSuggestItems.length > 0 || cpSuggestNoHits))"
                                                class="fixed z-[560] max-h-80 overflow-y-auto rounded-xl border border-slate-300 bg-white py-1 text-left shadow-xl ring-1 ring-slate-300/90"
                                                role="listbox"
                                                x-bind:style="{ top: cpSuggestPos.top + 'px', left: cpSuggestPos.left + 'px', width: cpSuggestPos.width + 'px' }"
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
                                                        Добавить поставщика…
                                                    </button>
                                                </div>
                                                <div
                                                    x-show="cpQuickOpen"
                                                    class="space-y-2 border-t border-slate-100 px-3 py-2"
                                                >
                                                    <p class="text-[11px] font-semibold text-slate-800">Новый поставщик</p>
                                                    <label class="block text-[10px] font-semibold text-slate-600" for="prt_edit_cp_quick_legal_form">Правовая форма</label>
                                                    <select
                                                        id="prt_edit_cp_quick_legal_form"
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
                                        <div class="min-w-0">
                                            <label for="prt_finalize_document_date" class="mb-0 block text-sm font-semibold text-slate-700">Дата документа *</label>
                                            <input
                                                id="prt_finalize_document_date"
                                                type="date"
                                                name="document_date"
                                                form="prt-purchase-return-form"
                                                value="{{ $prtDocDate }}"
                                                required
                                                class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm transition-colors focus:border-[#008b8b] focus:outline-none focus:ring-2 focus:ring-[#008b8b]/20"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div class="flex shrink-0 flex-wrap justify-end gap-2.5 border-t border-slate-100 bg-slate-50/70 px-5 py-4">
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:border-slate-300 hover:bg-slate-50"
                                        @click.prevent.stop="closeFinalizeModal()"
                                    >
                                        Отмена
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-xl border border-amber-600/30 bg-gradient-to-b from-[#ffdf6e] to-[#ffd740] px-4 py-2 text-sm font-bold text-slate-900 shadow-sm transition-opacity hover:opacity-95"
                                        @click.prevent.stop="submitPurchaseReturnFromModal()"
                                    >
                                        Сохранить изменения
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                    @include('admin.partials.good-quick-create-modal', ['idPrefix' => 'prt'])
                </div>
            </div>
        @endif
    </div>
</x-admin-layout>
