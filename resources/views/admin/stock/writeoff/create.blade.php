@php
    $isEdit = isset($document) && $document;
    $formAction = $isEdit ? route('admin.stock.writeoff.update', $document) : route('admin.stock.writeoff.store');
    $noteValue = old('note', $isEdit ? ($document->note ?? '') : '');
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="px-3 py-5 sm:px-5 lg:px-7 bg-[#f0f4f8]">
    @include('admin.partials.cp-brush')
    <div class="cp-root mx-auto w-full max-w-[min(100%,112rem)] space-y-6">
        @include('admin.partials.status-flash')

        <div>
            <a
                href="{{ route('admin.stock.writeoff', ['warehouse_id' => $warehouseId]) }}"
                class="text-sm font-semibold text-sky-800 decoration-sky-300 underline-offset-2 hover:text-sky-950 hover:underline"
            >
                ← К журналу списаний
            </a>
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
            @include('admin.stock.partials.doc-create-layout-styles')

            <div
                x-data="stockInventoryDoc({
                    searchUrl: @js(route('admin.goods.search')),
                    mode: 'single',
                    warehouseFromId: 0,
                    warehouseId: {{ (int) $warehouseId }},
                    extraUnitCost: false,
                    qtyField: 'quantity',
                    rowCount: 0,
                    initialRows: @js($initialRows ?? []),
                    allowManualNewGood: false,
                    enableHeaderSearch: true,
                })"
                class="stock-doc-create-page grid min-h-0 w-full min-w-0 max-w-full grid-cols-1 items-stretch gap-5"
            >
                @include('admin.stock.partials.header-good-search-panel')

                <div class="flex h-full min-h-0 min-w-0 w-full max-w-full flex-col">
                    <form method="POST" action="{{ $formAction }}" class="flex h-full min-h-0 min-w-0 flex-1 flex-col">
                        @csrf
                        @if ($isEdit)
                            @method('PUT')
                        @endif

                        <div class="stock-doc-lines-card flex min-h-0 flex-1 flex-col overflow-hidden border bg-white ring-1 ring-teal-900/[0.05]">
                            <div class="pr-panel-header-teal shrink-0">
                                Строки документа
                            </div>
                            <div class="ob-1c-scope flex min-h-0 flex-1 flex-col overflow-visible bg-white">
                                <div
                                    class="border-b border-emerald-200/55 bg-gradient-to-r from-emerald-50/95 via-white to-sky-50/50 px-4 py-3 sm:px-5"
                                >
                                    <p class="mb-0.5 text-[10px] font-semibold uppercase tracking-wider text-teal-700/90">Склад и запасы</p>
                                    <h2 class="text-[15px] font-bold leading-tight text-slate-800">{{ $pageTitle }}</h2>
                                    <p class="mt-1.5 max-w-2xl text-[11px] leading-relaxed text-slate-600">
                                        @if ($isEdit)
                                            Документ № {{ $document->id }}. Старые строки возвращают остаток на склад, новые снова списывают.
                                        @else
                                            Списание по учётному количеству; средняя закупочная на остатке не пересчитывается. Добавьте позиции слева или в таблице.
                                        @endif
                                    </p>
                                </div>

                                <div class="ob-1c-header">
                                    <div>
                                        <label for="wo_wh">Склад *</label>
                                        <select
                                            id="wo_wh"
                                            name="warehouse_id"
                                            x-model.number="warehouseId"
                                            class="mt-0 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                            required
                                        >
                                            @foreach ($warehouses as $w)
                                                <option value="{{ $w->id }}" @selected((int) old('warehouse_id', $warehouseId) === (int) $w->id)>{{ $w->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="wo_date">Дата документа *</label>
                                        <input
                                            id="wo_date"
                                            type="date"
                                            name="document_date"
                                            value="{{ old('document_date', $defaultDocumentDate) }}"
                                            required
                                        />
                                    </div>
                                </div>
                                <div class="border-b border-slate-200/90 bg-gradient-to-b from-slate-50/50 to-white px-4 py-3 sm:px-5">
                                    <label for="wo_note" class="mb-1.5 block text-xs font-semibold text-slate-600">Причина / комментарий</label>
                                    <textarea
                                        id="wo_note"
                                        name="note"
                                        rows="2"
                                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                        placeholder="Например: порча"
                                    >{{ $noteValue }}</textarea>
                                </div>

                                <div class="ob-1c-toolbar">
                                    <button type="button" class="ob-tb-btn" @click="addLine()">Добавить</button>
                                    <button type="button" class="ob-tb-btn ob-tb-btn-icon" title="Вверх" @click="moveUp()">▲</button>
                                    <button type="button" class="ob-tb-btn ob-tb-btn-icon" title="Вниз" @click="moveDown()">▼</button>
                                    <span class="mx-1 h-4 w-px bg-slate-300/90" aria-hidden="true"></span>
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

                                @include('admin.stock.partials.line-form-1c', ['mode' => 'single', 'extraUnitCost' => false, 'qtyField' => 'quantity', 'allowManualNewGood' => false])

                                <div class="ob-1c-foot">
                                    <a
                                        href="{{ route('admin.stock.writeoff', ['warehouse_id' => $warehouseId]) }}"
                                        class="ob-tb-btn border-slate-200 !no-underline hover:!bg-slate-50"
                                    >
                                        Отмена
                                    </a>
                                    <button type="submit" class="ob-tb-btn ob-btn-submit">{{ $isEdit ? 'Сохранить' : 'Провести' }}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-admin-layout>
