@php
    $isEdit = isset($document) && $document;
    $formAction = $isEdit ? route('admin.stock.incoming.update', $document) : route('admin.stock.incoming.store');
    $noteValue = old('note', $isEdit ? ($document->note ?? '') : '');
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-5xl space-y-5">
        <a
            href="{{ route('admin.stock.incoming', ['warehouse_id' => $warehouseId]) }}"
            class="inline-flex text-sm font-medium text-indigo-700 hover:text-indigo-900 hover:underline"
        >
            ← К журналу
        </a>

        @if ($warehouses->isEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950">
                <a href="{{ route('admin.warehouses.create') }}" class="font-semibold underline">Создайте склад</a>
            </div>
        @else
            <div
                x-data="stockInventoryDoc({
                    searchUrl: @js(route('admin.goods.search')),
                    mode: 'single',
                    warehouseFromId: 0,
                    warehouseId: {{ (int) $warehouseId }},
                    extraUnitCost: true,
                    qtyField: 'quantity',
                    rowCount: 0,
                    initialRows: @js($initialRows ?? []),
                    allowManualNewGood: true,
                })"
                class="space-y-4"
            >
                <form method="POST" action="{{ $formAction }}" class="space-y-4">
                    @csrf
                    @if ($isEdit)
                        @method('PUT')
                    @endif
                    <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-[0_8px_30px_-12px_rgba(15,23,42,0.12)]">
                        <div class="border-b border-slate-200/80 bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 px-5 py-4 sm:px-6">
                            <h1 class="text-base font-semibold tracking-tight text-white">{{ $pageTitle }}</h1>
                            <p class="mt-1 max-w-2xl text-xs leading-relaxed text-slate-300">
                                @if ($isEdit)
                                    Документ № {{ $document->id }}. Изменения перепроводят остатки: старые строки снимаются, новые проводятся заново.
                                @else
                                    Увеличение остатка на складе; средневзвешенная закупка пересчитывается. Выберите товар из списка или укажите новый — артикул, название и единицу измерения.
                                @endif
                            </p>
                        </div>
                        <div class="space-y-5 px-4 py-5 sm:px-6">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-1">
                                    <label for="inc_wh" class="mb-1.5 block text-xs font-semibold text-slate-700">Склад *</label>
                                    <select
                                        id="inc_wh"
                                        name="warehouse_id"
                                        x-model.number="warehouseId"
                                        class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-3 pr-9 text-sm text-slate-900 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                        required
                                    >
                                        @foreach ($warehouses as $w)
                                            <option value="{{ $w->id }}" @selected((int) old('warehouse_id', $warehouseId) === (int) $w->id)>{{ $w->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="sm:col-span-1">
                                    <label for="inc_date" class="mb-1.5 block text-xs font-semibold text-slate-700">Дата *</label>
                                    <input
                                        id="inc_date"
                                        type="date"
                                        name="document_date"
                                        value="{{ old('document_date', $defaultDocumentDate) }}"
                                        class="w-full max-w-xs rounded-lg border border-slate-200 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                        required
                                    />
                                </div>
                            </div>
                            <div>
                                <label for="inc_note" class="mb-1.5 block text-xs font-semibold text-slate-700">Комментарий</label>
                                <textarea
                                    id="inc_note"
                                    name="note"
                                    rows="2"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                >{{ $noteValue }}</textarea>
                            </div>
                            @error('lines')
                                <p class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">{{ $message }}</p>
                            @enderror
                            @include('admin.stock.partials.line-form', ['mode' => 'single', 'extraUnitCost' => true, 'qtyField' => 'quantity', 'allowManualNewGood' => true])
                            <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-5">
                                <a
                                    href="{{ route('admin.stock.incoming', ['warehouse_id' => $warehouseId]) }}"
                                    class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                                >
                                    Отмена
                                </a>
                                <button
                                    type="submit"
                                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                >
                                    {{ $isEdit ? 'Сохранить' : 'Провести' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-admin-layout>
