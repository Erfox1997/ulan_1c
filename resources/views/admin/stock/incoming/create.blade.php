@php
    $isEdit = isset($document) && $document;
    $formAction = $isEdit ? route('admin.stock.incoming.update', $document) : route('admin.stock.incoming.store');
    $noteValue = old('note', $isEdit ? ($document->note ?? '') : '');
    $whName = $warehouses->firstWhere('id', $selectedWarehouseId)?->name ?? '';
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="px-3 py-6 sm:px-6 lg:px-8">
    @include('admin.partials.cp-brush')
    <div class="cp-root mx-auto w-full max-w-[min(100%,112rem)] space-y-6">
        @include('admin.partials.status-flash')

        <div>
            <a
                href="{{ route('admin.stock.incoming', ['warehouse_id' => $selectedWarehouseId]) }}"
                class="text-sm font-semibold text-sky-800 decoration-sky-300 underline-offset-2 hover:text-sky-950 hover:underline"
            >← К журналу оприходований</a>
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
            @if (! $isEdit)
                <div
                    class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50"
                >
                    <form
                        method="GET"
                        action="{{ route('admin.stock.incoming.create') }}"
                        class="rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90 px-4 py-4 sm:px-6 sm:py-5"
                    >
                        <x-input-label for="inc_wh_create" value="Склад документа *" />
                        <select
                            id="inc_wh_create"
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

            @if (! $isEdit && $selectedWarehouseId === 0)
                <div
                    class="rounded-2xl border border-slate-200/90 bg-gradient-to-b from-slate-50/80 to-white px-4 py-6 text-sm text-slate-600 shadow-sm ring-1 ring-slate-100/80"
                >
                    Выберите склад в форме выше.
                </div>
            @else
                <div
                    x-data="stockInventoryDoc({
                        searchUrl: @js(route('admin.goods.search')),
                        mode: 'single',
                        warehouseFromId: 0,
                        warehouseId: {{ (int) $selectedWarehouseId }},
                        extraUnitCost: true,
                        qtyField: 'quantity',
                        rowCount: {{ $isEdit ? 0 : 1 }},
                        initialRows: @js($initialRows ?? []),
                        allowManualNewGood: false,
                    })"
                    class="space-y-6"
                >
                    <form method="POST" action="{{ $formAction }}" class="space-y-6">
                        @csrf
                        @if ($isEdit)
                            @method('PUT')
                        @endif

                        @if (! $isEdit)
                            <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}" />
                        @endif

                        <div
                            class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50"
                        >
                            <div class="overflow-visible rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90">
                                <div class="ob-1c-scope overflow-visible rounded-[1.5rem] bg-white/95">
                                    <div
                                        class="border-b border-emerald-200/55 bg-gradient-to-r from-emerald-50/95 via-white to-sky-50/50 px-4 py-3 sm:px-5"
                                    >
                                        <p class="mb-0.5 text-[10px] font-semibold uppercase tracking-wider text-teal-700/90">Склад и запасы</p>
                                        <h2 class="text-[15px] font-bold leading-tight text-slate-800">{{ $pageTitle }}</h2>
                                    </div>

                                    <div class="ob-1c-header">
                                        @if ($isEdit)
                                            <div>
                                                <label for="inc_wh_edit">Склад *</label>
                                                <select
                                                    id="inc_wh_edit"
                                                    name="warehouse_id"
                                                    x-model.number="warehouseId"
                                                    class="mt-0 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                                    required
                                                >
                                                    @foreach ($warehouses as $w)
                                                        <option value="{{ $w->id }}" @selected((int) old('warehouse_id', $selectedWarehouseId) === (int) $w->id)>{{ $w->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @else
                                            <div>
                                                <span class="mb-1.5 block text-xs font-semibold text-slate-600">Склад</span>
                                                <p class="rounded-lg border border-slate-200/90 bg-slate-50/90 px-3 py-2 text-sm font-semibold text-slate-900">{{ $whName }}</p>
                                            </div>
                                        @endif
                                        <div>
                                            <label for="inc_date">Дата документа *</label>
                                            <input
                                                id="inc_date"
                                                type="date"
                                                name="document_date"
                                                value="{{ old('document_date', $defaultDocumentDate) }}"
                                                required
                                            />
                                        </div>
                                    </div>
                                    <div class="border-b border-slate-200/90 bg-gradient-to-b from-slate-50/50 to-white px-4 py-3 sm:px-5">
                                        <label for="inc_note" class="mb-1.5 block text-xs font-semibold text-slate-600">Комментарий</label>
                                        <textarea
                                            id="inc_note"
                                            name="note"
                                            rows="2"
                                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
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

                                    @include('admin.stock.partials.line-form-1c', ['mode' => 'single', 'extraUnitCost' => true, 'qtyField' => 'quantity', 'allowManualNewGood' => false])

                                    <div class="ob-1c-foot">
                                        <a
                                            href="{{ route('admin.stock.incoming', ['warehouse_id' => $selectedWarehouseId]) }}"
                                            class="ob-tb-btn border-slate-200 !no-underline hover:!bg-slate-50"
                                        >
                                            Отмена
                                        </a>
                                        <button type="submit" class="ob-tb-btn ob-btn-submit">{{ $isEdit ? 'Сохранить' : 'Провести и закрыть' }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            @endif
        @endif
    </div>
</x-admin-layout>
