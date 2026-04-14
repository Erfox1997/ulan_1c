@props([
    'mode' => 'single',
    'extraUnitCost' => false,
    'qtyField' => 'quantity',
    'allowManualNewGood' => false,
])
<div class="stock-inv-lines rounded-xl border border-slate-200/90 bg-white shadow-sm">
    @if ($mode === 'transfer')
        <p class="border-b border-slate-100 bg-slate-50/80 px-3 py-2.5 text-xs text-slate-600">
            Поиск и остаток по складу <span class="font-semibold text-slate-800">отправителя</span> (выберите склад выше).
        </p>
    @endif
    {{-- Не используем overflow-x-auto здесь: у блока с overflow список под ячейкой обрезается по вертикали --}}
    <div class="min-w-0">
        <table class="min-w-full text-left text-xs">
            <thead class="border-b border-slate-200 bg-slate-50/90 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2.5">Товар</th>
                    <th class="w-24 px-2 py-2.5">Остаток</th>
                    <th class="w-28 px-2 py-2.5">Ед.</th>
                    <th class="w-32 px-2 py-2.5" x-show="extraUnitCost">Закуп. цена</th>
                    <th class="w-32 px-2 py-2.5">
                        @if ($qtyField === 'quantity_counted')
                            Факт
                        @else
                            Кол-во
                        @endif
                    </th>
                    <th class="w-10 px-1 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr x-show="rows.length === 0">
                    <td
                        class="px-4 py-12 text-center align-middle"
                        :colspan="extraUnitCost ? 6 : 5"
                    >
                        <p class="text-sm text-slate-600">Строк пока нет — добавьте позиции вручную.</p>
                        <button
                            type="button"
                            class="mt-4 inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-800 transition hover:bg-indigo-100"
                            @click="addLine()"
                        >
                            <span class="text-lg leading-none">+</span>
                            Добавить строку
                        </button>
                    </td>
                </tr>
                <template x-for="(row, i) in rows" :key="i">
                    <tr class="align-top">
                        <td class="relative px-3 py-2">
                            <input type="hidden" :name="'lines[' + i + '][good_id]'" :value="row.goodId" />
                            @if ($allowManualNewGood)
                                <input type="hidden" :name="'lines[' + i + '][article_code]'" :value="row.articleManual" :disabled="!!row.goodId" />
                                <input type="hidden" :name="'lines[' + i + '][manual_name]'" :value="row.nameManual" :disabled="!!row.goodId" />
                                <input type="hidden" :name="'lines[' + i + '][unit]'" :value="row.unitManual" :disabled="!!row.goodId" />
                            @endif
                            <input
                                type="search"
                                class="w-full min-w-[12rem] rounded-lg border border-slate-200 px-2.5 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                x-model="row.query"
                                @input.debounce.300ms="searchRow(i)"
                                @focus="row.open = row.results.length > 0"
                                autocomplete="off"
                                placeholder="Артикул или название…"
                            />
                            <div
                                class="absolute left-3 right-3 top-full z-[9999] mt-1 max-h-48 overflow-auto rounded-lg border border-slate-200 bg-white py-1 shadow-xl ring-1 ring-black/5"
                                x-show="row.open && row.results.length"
                                x-transition
                                @click.outside="row.open = false"
                            >
                                <template x-for="g in row.results" :key="g.id">
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
                            <p class="mt-0.5 truncate text-[11px] text-slate-500" x-show="row.name && row.goodId" x-text="row.name"></p>
                            @if ($allowManualNewGood)
                                <p class="mt-1" x-show="row.goodId">
                                    <button
                                        type="button"
                                        class="text-[11px] font-medium text-indigo-600 hover:underline"
                                        @click="switchToManual(i)"
                                    >
                                        Нет в базе — ввести вручную
                                    </button>
                                </p>
                                <div
                                    class="mt-2 space-y-1.5 rounded-lg border border-dashed border-indigo-200/80 bg-indigo-50/50 px-2.5 py-2"
                                    x-show="!row.goodId"
                                >
                                    <p class="text-[11px] font-semibold text-slate-700">Новая позиция (карточка товара создаётся при проведении)</p>
                                    <input
                                        type="text"
                                        x-model="row.articleManual"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                        placeholder="Артикул *"
                                        autocomplete="off"
                                    />
                                    <input
                                        type="text"
                                        x-model="row.nameManual"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                        placeholder="Название *"
                                        autocomplete="off"
                                    />
                                    <input
                                        type="text"
                                        x-model="row.unitManual"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                        placeholder="Ед. изм. (например шт.)"
                                        autocomplete="off"
                                    />
                                </div>
                            @endif
                        </td>
                        <td class="px-2 py-2">
                            <input
                                type="text"
                                readonly
                                tabindex="-1"
                                class="w-full cursor-default rounded-lg border border-slate-200 bg-slate-50/95 px-2 py-1.5 text-right text-sm tabular-nums text-slate-700 shadow-sm"
                                :value="rowStockDisplay(row)"
                            />
                        </td>
                        <td class="px-2 py-2">
                            <input
                                type="text"
                                readonly
                                tabindex="-1"
                                class="w-full cursor-default rounded-lg border border-slate-200 bg-slate-50/95 px-2 py-1.5 text-sm text-slate-700 shadow-sm"
                                :value="rowUnitDisplay(row)"
                            />
                        </td>
                        <td class="px-2 py-2" x-show="extraUnitCost">
                            <input
                                type="text"
                                inputmode="decimal"
                                class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-right text-sm tabular-nums shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                x-model="row.unitCost"
                                :name="'lines[' + i + '][unit_cost]'"
                                placeholder="необяз."
                            />
                        </td>
                        <td class="px-2 py-2">
                            <input
                                type="text"
                                inputmode="decimal"
                                class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-right text-sm tabular-nums shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                x-model="row.qty"
                                :name="'lines[' + i + '][' + qtyField + ']'"
                                placeholder="0"
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
        class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/50 px-3 py-2.5"
    >
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-800 shadow-sm hover:bg-indigo-50"
            @click="addLine()"
        >
            <span class="text-base leading-none">+</span>
            Добавить строку
        </button>
        <span class="text-[11px] text-slate-500">До 32 позиций</span>
    </div>
    <p class="border-t border-slate-100 px-3 py-2 text-[11px] text-amber-800/90" x-show="effectiveWarehouseId() <= 0">
        Выберите склад, чтобы подставлялись остатки при поиске.
    </p>
</div>
