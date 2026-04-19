@php
    $isEdit = isset($document) && $document;
    $formAction = $isEdit ? route('admin.stock.audit.update', $document) : route('admin.stock.audit.store');
    $journalWarehouseId = (int) old('warehouse_id', $warehouseId);
    $noteValue = old('note', $isEdit ? ($document->note ?? '') : '');
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-5xl space-y-5">
        <a
            href="{{ route('admin.stock.audit', ['warehouse_id' => $journalWarehouseId]) }}"
            class="inline-flex text-sm font-medium text-indigo-700 hover:text-indigo-900 hover:underline"
        >
            ← К журналу
        </a>

        @if ($warehouses->isEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950">
                <a href="{{ route('admin.warehouses.create') }}" class="font-semibold text-indigo-800 underline">Создайте склад</a>
            </div>
        @else
            <div
                x-data="stockAuditDoc({
                    searchUrl: @js(route('admin.goods.search')),
                    warehouseId: {{ (int) $warehouseId }},
                    initialRows: @js($initialRows ?? []),
                    linesLoadUrl: @js($linesLoadUrl ?? null),
                    formAction: @js($formAction),
                    csrfToken: @js(csrf_token()),
                    isEdit: @js($isEdit),
                })"
                class="space-y-4"
            >
                <form
                    method="POST"
                    action="{{ $formAction }}"
                    class="space-y-4"
                    x-ref="auditForm"
                    @submit.prevent
                >
                    @csrf
                    <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-[0_8px_30px_-12px_rgba(15,23,42,0.12)]">
                        <div class="border-b border-slate-200/80 bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 px-5 py-4 sm:px-6">
                            <h1 class="text-base font-semibold tracking-tight text-white">{{ $pageTitle }}</h1>
                            @if ($isEdit)
                                <p class="mt-1 text-xs text-slate-300">До проведения остатки не меняются.</p>
                            @endif
                        </div>
                        <div class="space-y-5 px-4 py-5 sm:px-6">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="au_wh" class="mb-1.5 block text-xs font-semibold text-slate-700">Склад *</label>
                                    <select
                                        id="au_wh"
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
                                <div>
                                    <label for="au_date" class="mb-1.5 block text-xs font-semibold text-slate-700">Дата *</label>
                                    <input
                                        id="au_date"
                                        type="date"
                                        name="document_date"
                                        value="{{ old('document_date', $defaultDocumentDate) }}"
                                        class="w-full max-w-xs rounded-lg border border-slate-200 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                        required
                                    />
                                </div>
                            </div>
                            <div>
                                <label for="au_note" class="mb-1.5 block text-xs font-semibold text-slate-700">Комментарий</label>
                                <textarea
                                    id="au_note"
                                    name="note"
                                    rows="2"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                >{{ $noteValue }}</textarea>
                            </div>

                            @error('lines')
                                <p class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">{{ $message }}</p>
                            @enderror

                            <div
                                class="relative rounded-xl border-2 border-dashed p-4 transition-colors sm:p-5"
                                :class="scanOpen && scanResults.length ? 'border-amber-400 bg-amber-50/90 ring-2 ring-amber-400/45' : 'border-indigo-200 bg-indigo-50/40'"
                            >
                                <label for="audit_scan" class="mb-2 block text-xs font-bold uppercase tracking-wide text-indigo-900/80">
                                    Сканер штрихкода
                                </label>
                                <p class="mb-3 text-xs leading-relaxed text-slate-600">
                                    Каждое сканирование добавляет <span class="font-semibold text-slate-800">+1</span> к факту по этой позиции (в любой очередности).
                                    Если по штрихкоду находится несколько товаров — выберите нужный в списке (прозвучит сигнал).
                                </p>
                                <div class="relative">
                                    <input
                                        id="audit_scan"
                                        type="text"
                                        name="audit_scan_dummy"
                                        autocomplete="off"
                                        x-ref="scanEl"
                                        x-model="scanQuery"
                                        @keydown.enter.prevent="handleScanSubmit()"
                                        placeholder="Штрихкод — скан или ввод, Enter…"
                                        class="w-full rounded-xl border border-indigo-200 bg-white px-4 py-3.5 text-base font-mono shadow-inner focus:border-indigo-500 focus:outline-none focus:ring-4 focus:ring-indigo-500/15"
                                    />
                                    <span
                                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-semibold uppercase text-slate-400"
                                        x-show="scanLoading"
                                    >
                                        …
                                    </span>
                                </div>
                                <div
                                    class="absolute left-4 right-4 top-full z-[9999] mt-1 max-h-56 overflow-auto rounded-lg border border-slate-200 bg-white py-1 shadow-xl ring-1 ring-black/5"
                                    x-show="scanOpen && scanResults.length"
                                    x-transition
                                    @click.outside="scanOpen = false"
                                >
                                    <template x-for="g in scanResults" :key="g.id">
                                        <button
                                            type="button"
                                            class="block w-full border-b border-slate-50 px-3 py-2.5 text-left text-sm last:border-b-0 hover:bg-indigo-50"
                                            @click="pickScanResult(g)"
                                        >
                                            <span class="font-mono text-xs text-slate-500" x-text="g.article_code"></span>
                                            <span class="block font-medium text-slate-900" x-text="g.name"></span>
                                            <span class="mt-0.5 block text-[10px] text-slate-400" x-show="g.barcode">
                                                ШК: <span class="font-mono" x-text="g.barcode"></span>
                                            </span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <div class="rounded-xl border border-slate-200/90 bg-white shadow-sm">
                                <div class="border-b border-slate-100 bg-slate-50/80 px-3 py-2 text-xs font-semibold text-slate-700">
                                    Строки ревизии
                                </div>
                                <p x-show="linesLoading" class="px-3 py-6 text-center text-sm text-slate-600">Загрузка строк документа…</p>
                                <p
                                    x-show="!linesLoading && linesLoadError"
                                    class="px-3 py-4 text-center text-sm text-rose-700"
                                    x-text="linesLoadError"
                                ></p>
                                <div class="min-w-0 overflow-x-auto" x-show="!linesLoading">
                                    <table class="min-w-full text-left text-xs">
                                        <thead class="border-b border-slate-200 bg-slate-50/90 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                            <tr>
                                                <th class="px-3 py-2.5">#</th>
                                                <th class="min-w-[14rem] px-3 py-2.5">Товар</th>
                                                <th class="w-24 px-2 py-2.5">Остаток</th>
                                                <th class="w-24 px-2 py-2.5">Ед.</th>
                                                <th class="w-32 px-2 py-2.5">Факт</th>
                                                <th class="w-10 px-1 py-2.5"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <tr x-show="rows.length === 0 && !linesLoading">
                                                <td colspan="6" class="px-4 py-10 text-center align-middle">
                                                    <p class="text-sm text-slate-600">Пока пусто — сканируйте штрихкод выше или добавьте строку вручную.</p>
                                                    <button
                                                        type="button"
                                                        class="mt-4 inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-800 transition hover:bg-indigo-100"
                                                        @click="addManualLine()"
                                                    >
                                                        <span class="text-lg leading-none">+</span>
                                                        Добавить строку вручную
                                                    </button>
                                                </td>
                                            </tr>
                                            <template x-for="i in auditRowIndices()" :key="'audit-line-' + i">
                                                <tr class="align-top">
                                                    <td class="px-3 py-2.5 tabular-nums text-slate-500" x-text="i + 1"></td>
                                                    <td class="relative px-3 py-2">
                                                        <div x-show="rows[i].manual" class="relative" x-cloak>
                                                            <input
                                                                type="search"
                                                                class="w-full min-w-[12rem] rounded-lg border border-slate-200 px-2.5 py-2 text-sm shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                                                x-model="rows[i].query"
                                                                :data-audit-search="i"
                                                                @input.debounce.300ms="searchRow(i)"
                                                                @focus="rows[i].open = rows[i].results.length > 0"
                                                                autocomplete="off"
                                                                placeholder="Артикул или название…"
                                                            />
                                                            <div
                                                                class="absolute left-0 right-0 top-full z-[9999] mt-1 max-h-48 overflow-auto rounded-lg border border-slate-200 bg-white py-1 shadow-xl ring-1 ring-black/5"
                                                                x-show="rows[i].open && rows[i].results.length"
                                                                x-transition
                                                                @click.outside="rows[i].open = false"
                                                            >
                                                                <template x-for="g in rows[i].results" :key="g.id">
                                                                    <button
                                                                        type="button"
                                                                        class="block w-full px-3 py-2 text-left text-sm hover:bg-indigo-50"
                                                                        @click="pickGood(i, g)"
                                                                    >
                                                                        <span class="font-mono text-xs text-slate-500" x-text="g.article_code"></span>
                                                                        <span class="block font-medium text-slate-900" x-text="g.name"></span>
                                                                    </button>
                                                                </template>
                                                            </div>
                                                        </div>
                                                        <div x-show="!rows[i].manual" class="space-y-0.5">
                                                            <p class="font-mono text-[11px] text-slate-500" x-text="rows[i].article"></p>
                                                            <p class="text-sm font-medium text-slate-900" x-text="rows[i].name"></p>
                                                            <p class="text-[11px] text-slate-400" x-show="rows[i].barcode">
                                                                ШК: <span x-text="rows[i].barcode"></span>
                                                            </p>
                                                        </div>
                                                    </td>
                                                    <td class="px-2 py-2">
                                                        <input
                                                            type="text"
                                                            readonly
                                                            tabindex="-1"
                                                            class="w-full cursor-default rounded-lg border border-slate-200 bg-slate-50/95 px-2 py-1.5 text-right text-sm tabular-nums text-slate-700 shadow-sm"
                                                            :value="rowStockDisplay(rows[i])"
                                                        />
                                                    </td>
                                                    <td class="px-2 py-2">
                                                        <input
                                                            type="text"
                                                            readonly
                                                            tabindex="-1"
                                                            class="w-full cursor-default rounded-lg border border-slate-200 bg-slate-50/95 px-2 py-1.5 text-sm text-slate-700 shadow-sm"
                                                            :value="rowUnitDisplay(rows[i])"
                                                        />
                                                    </td>
                                                    <td class="px-2 py-2">
                                                        <input
                                                            type="text"
                                                            inputmode="decimal"
                                                            autocomplete="off"
                                                            class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-right text-sm tabular-nums shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                                            x-model="rows[i].qty"
                                                            :id="'audit-qty-' + i"
                                                            :data-audit-qty="i"
                                                            placeholder="Факт"
                                                            @keydown.enter.prevent="onQtyEnter($event)"
                                                            @keydown.tab="onQtyTab($event)"
                                                        />
                                                    </td>
                                                    <td class="px-1 py-2">
                                                        <button
                                                            type="button"
                                                            class="rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                                                            title="Удалить строку"
                                                            @click="removeLine(i)"
                                                        >
                                                            ×
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                                <div
                                    class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/50 px-3 py-2.5"
                                    x-show="rows.length > 0"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-800 shadow-sm hover:bg-indigo-50"
                                        @click="addManualLine()"
                                        :disabled="linesLoading"
                                    >
                                        <span class="text-base leading-none">+</span>
                                        Добавить строку вручную
                                    </button>
                                    <div
                                        class="flex flex-wrap items-center gap-2 text-[11px] text-slate-600"
                                        x-show="auditTotalPages() > 1 || rows.length > auditPageSize"
                                    >
                                        <span class="tabular-nums">Страница <span x-text="auditPageLabel()"></span></span>
                                        <div class="flex items-center gap-1">
                                            <button
                                                type="button"
                                                class="rounded border border-slate-200 bg-white px-2 py-0.5 font-medium hover:bg-slate-50 disabled:opacity-40"
                                                @click="auditPage = Math.max(1, auditPage - 1)"
                                                :disabled="auditPage <= 1"
                                            >
                                                ←
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded border border-slate-200 bg-white px-2 py-0.5 font-medium hover:bg-slate-50 disabled:opacity-40"
                                                @click="auditPage = Math.min(auditTotalPages(), auditPage + 1)"
                                                :disabled="auditPage >= auditTotalPages()"
                                            >
                                                →
                                            </button>
                                        </div>
                                        <label class="inline-flex items-center gap-1.5">
                                            <span class="text-slate-500">По</span>
                                            <select
                                                x-model.number="auditPageSize"
                                                class="rounded border border-slate-200 bg-white py-0.5 pl-1.5 pr-6 text-[11px] font-medium"
                                            >
                                                <option value="50">50</option>
                                                <option value="100">100</option>
                                                <option value="200">200</option>
                                                <option value="500">500</option>
                                            </select>
                                            <span class="text-slate-500">в листе</span>
                                        </label>
                                    </div>
                                    <span class="text-[11px] text-slate-500">
                                        Всего позиций: <span class="font-semibold tabular-nums text-slate-800" x-text="rows.length"></span>
                                        <span class="text-slate-400">· макс. <span x-text="maxRows"></span></span>
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-5">
                                <a
                                    href="{{ route('admin.stock.audit', ['warehouse_id' => $journalWarehouseId]) }}"
                                    class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                                >
                                    Отмена
                                </a>
                                <button
                                    type="button"
                                    class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-900 shadow-sm hover:bg-indigo-100 disabled:opacity-50"
                                    @click="submitStockAudit('draft')"
                                    :disabled="auditSubmitting || linesLoading"
                                >
                                    Сохранить черновик
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                                    @click="submitStockAudit('post')"
                                    :disabled="auditSubmitting || linesLoading"
                                >
                                    Провести ревизию
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-admin-layout>
