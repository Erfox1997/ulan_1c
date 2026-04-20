@props([
    'mode' => 'single',
    'extraUnitCost' => false,
    'qtyField' => 'quantity',
    'allowManualNewGood' => false,
])
<div class="min-w-0">
    @if ($mode === 'transfer')
        <p class="border-b border-emerald-100 bg-emerald-50/50 px-3 py-2 text-[11px] text-slate-600">
            Поиск и остаток по складу <span class="font-semibold text-slate-800">отправителя</span>.
        </p>
    @endif
    {{-- Без overflow-x-auto: иначе absolute-выпадашка обрезается (см. line-form.blade) --}}
    <div class="min-w-0 border-t border-slate-200/90">
        <table
            class="ob-1c-table"
            @focusin="$event.target.classList.contains('ob-inp') && $event.target.select()"
            @mouseup="$event.target.classList.contains('ob-inp') && $event.preventDefault()"
        >
            <thead>
                <tr>
                    <th class="ob-num">N</th>
                    <th>Товар *</th>
                    <th>Остаток</th>
                    <th>Ед.</th>
                    @if ($extraUnitCost)
                        <th>Закуп. цена *</th>
                    @endif
                    <th>
                        @if ($qtyField === 'quantity_counted')
                            Факт
                        @else
                            Кол-во *
                        @endif
                    </th>
                    <th>Продажная цена</th>
                </tr>
            </thead>
            <tbody>
                <tr x-show="rows.length === 0">
                    <td
                        class="px-4 py-10 text-center align-middle text-sm text-slate-600"
                        :colspan="extraUnitCost ? 7 : 6"
                    >
                        Нет строк — нажмите «Добавить» на панели выше.
                    </td>
                </tr>
                <template x-for="(row, i) in rows" :key="i">
                    <tr
                        class="cursor-pointer"
                        :class="{ 'ob-row-active': selectedRow === i }"
                        @click="selectedRow = i"
                    >
                        <td class="ob-num" x-text="i + 1"></td>
                        <td
                            class="relative min-w-[12rem] align-top"
                            @click.outside="row.open = false"
                        >
                            <input type="hidden" :name="'lines[' + i + '][good_id]'" :value="row.goodId" />
                            @if ($allowManualNewGood)
                                <input type="hidden" :name="'lines[' + i + '][article_code]'" :value="row.articleManual" :disabled="!!row.goodId" />
                                <input type="hidden" :name="'lines[' + i + '][manual_name]'" :value="row.nameManual" :disabled="!!row.goodId" />
                                <input type="hidden" :name="'lines[' + i + '][unit]'" :value="row.unitManual" :disabled="!!row.goodId" />
                            @endif
                            <input
                                type="search"
                                class="ob-inp"
                                x-model="row.query"
                                @input.debounce.300ms="searchRow(i)"
                                @focus="row.open = row.results.length > 0; selectedRow = i"
                                autocomplete="off"
                                placeholder="Артикул или название…"
                            />
                            <div
                                class="absolute left-0 right-0 top-full z-[210] mt-0.5 max-h-48 overflow-auto rounded-lg border border-slate-200 bg-white py-1 shadow-xl ring-1 ring-black/5"
                                x-show="row.open && row.results.length"
                                x-transition
                            >
                                <template x-for="g in row.results" :key="g.id">
                                    <button
                                        type="button"
                                        class="block w-full px-3 py-2 text-left text-xs hover:bg-emerald-50"
                                        @click="pickGood(i, g)"
                                    >
                                        <span class="font-mono text-[10px] text-slate-500" x-text="g.article_code"></span>
                                        <span class="block font-medium text-slate-900" x-text="g.name"></span>
                                    </button>
                                </template>
                            </div>
                            <p class="truncate px-2 pb-0.5 pt-0.5 text-[10px] text-slate-500" x-show="row.name && row.goodId" x-text="row.name"></p>
                            @if ($allowManualNewGood)
                                <p class="px-2 pb-1" x-show="row.goodId">
                                    <button
                                        type="button"
                                        class="text-[10px] font-semibold text-teal-700 hover:underline"
                                        @click.stop="switchToManual(i)"
                                    >
                                        Нет в базе — ввести вручную
                                    </button>
                                </p>
                                <div
                                    class="mx-1 mb-1 space-y-1 rounded border border-dashed border-emerald-200/90 bg-emerald-50/40 px-2 py-1.5"
                                    x-show="!row.goodId"
                                    @click.stop
                                >
                                    <p class="text-[10px] font-semibold text-slate-700">Новая позиция (карточка при проведении)</p>
                                    <input
                                        type="text"
                                        x-model="row.articleManual"
                                        class="ob-inp !min-h-[22px] rounded border border-slate-200 bg-white px-1.5 text-[11px]"
                                        placeholder="Артикул *"
                                        autocomplete="off"
                                    />
                                    <input
                                        type="text"
                                        x-model="row.nameManual"
                                        class="ob-inp !min-h-[22px] rounded border border-slate-200 bg-white px-1.5 text-[11px]"
                                        placeholder="Название *"
                                        autocomplete="off"
                                    />
                                    <input
                                        type="text"
                                        x-model="row.unitManual"
                                        class="ob-inp !min-h-[22px] rounded border border-slate-200 bg-white px-1.5 text-[11px]"
                                        placeholder="Ед. изм."
                                        autocomplete="off"
                                    />
                                </div>
                            @endif
                        </td>
                        <td class="min-w-[4rem] ob-numr">
                            <input
                                type="text"
                                readonly
                                tabindex="-1"
                                class="ob-inp tabular-nums text-slate-600"
                                :value="rowStockDisplay(row)"
                            />
                        </td>
                        <td class="min-w-[3rem]">
                            <input
                                type="text"
                                readonly
                                tabindex="-1"
                                class="ob-inp text-slate-600"
                                :value="rowUnitDisplay(row)"
                            />
                        </td>
                        @if ($extraUnitCost)
                            <td class="min-w-[4.5rem] ob-numr">
                                <input
                                    type="text"
                                    inputmode="decimal"
                                    class="ob-inp tabular-nums"
                                x-model="row.unitCost"
                                :name="'lines[' + i + '][unit_cost]'"
                                placeholder=""
                                autocomplete="off"
                                />
                            </td>
                        @endif
                        <td class="min-w-[4rem] ob-numr">
                            <input
                                type="text"
                                inputmode="decimal"
                                class="ob-inp tabular-nums"
                                x-model="row.qty"
                                :name="'lines[' + i + '][' + qtyField + ']'"
                                placeholder="0"
                            />
                        </td>
                        <td class="min-w-[4.5rem] ob-numr">
                            <input
                                type="text"
                                inputmode="decimal"
                                class="ob-inp tabular-nums"
                                x-model="row.sale_price"
                                :name="'lines[' + i + '][sale_price]'"
                                placeholder=""
                                autocomplete="off"
                                :class="!row.goodId ? 'text-slate-400' : ''"
                            />
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    <p class="border-t border-slate-100 px-3 py-2 text-[10px] text-amber-800/90" x-show="effectiveWarehouseId() <= 0">
        Выберите склад, чтобы подставлялись остатки при поиске.
    </p>
</div>
