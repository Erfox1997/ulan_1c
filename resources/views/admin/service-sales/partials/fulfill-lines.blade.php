@php
    /** @var list<array{line_id: int, name: string, unit: string, quantity: string, unit_price: string}> $fulfillLinesPayload */
@endphp
<div
    x-data="{
        rows: @js($fulfillLinesPayload),
        removeRow(i) {
            if (this.rows.length <= 1) {
                window.alert('Должна остаться хотя бы одна позиция.');
                return;
            }
            this.rows.splice(i, 1);
        },
        formatQty(row) {
            const v = String(row.quantity).replace(',', '.').replace(/\s/g, '').trim();
            const n = parseFloat(v);
            if (Number.isFinite(n)) {
                row.quantity = n.toFixed(2);
            }
        },
        formatPrice(row) {
            const v = String(row.unit_price).replace(',', '.').replace(/\s/g, '').trim();
            const n = parseFloat(v);
            if (Number.isFinite(n)) {
                row.unit_price = n.toFixed(2);
            }
        },
    }"
>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[18rem] border-collapse text-sm">
            <thead>
                <tr class="border-b border-slate-200 bg-gradient-to-r from-slate-50 to-slate-100/90 text-left text-[10px] font-bold uppercase tracking-wide text-slate-600">
                    <th class="px-3 py-2.5">Наименование</th>
                    <th class="w-[7rem] whitespace-nowrap px-2 py-2.5 font-medium">Кол-во</th>
                    <th class="w-[6.5rem] whitespace-nowrap px-2 py-2.5 text-right font-medium">Цена</th>
                    <th class="w-10 px-1 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <template x-for="(row, idx) in rows" :key="row.line_id">
                    <tr class="align-middle text-slate-800 transition-colors hover:bg-emerald-50/30">
                        <td class="max-w-[10rem] px-3 py-2.5 text-sm font-medium leading-snug text-slate-900" x-text="row.name"></td>
                        <td class="px-2 py-2">
                            <input type="hidden" :name="'lines[' + idx + '][line_id]'" :value="row.line_id" />
                            <div class="flex items-center gap-1">
                                <input
                                    type="text"
                                    inputmode="decimal"
                                    :name="'lines[' + idx + '][quantity]'"
                                    x-model="row.quantity"
                                    @blur="formatQty(row)"
                                    required
                                    class="w-[4.5rem] rounded-md border border-slate-200 bg-white px-1.5 py-1 text-right text-xs font-semibold tabular-nums text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                />
                                <span class="shrink-0 text-[11px] text-slate-500" x-text="row.unit"></span>
                            </div>
                        </td>
                        <td class="px-2 py-2">
                            <input
                                type="text"
                                inputmode="decimal"
                                :name="'lines[' + idx + '][unit_price]'"
                                x-model="row.unit_price"
                                @blur="formatPrice(row)"
                                required
                                class="w-full max-w-[5.5rem] rounded-md border border-slate-200 bg-white px-1.5 py-1 text-right text-xs font-semibold tabular-nums text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                            />
                        </td>
                        <td class="px-1 py-2 text-center">
                            <button
                                type="button"
                                class="rounded-md p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                                title="Убрать из оформления"
                                @click="removeRow(idx)"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>
