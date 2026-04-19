@php
    $esfFilter = $esfFilter ?? ['date_from' => '', 'date_to' => ''];
    $bulkFormId = 'esf-queue-bulk-form';
@endphp
<div
    class="space-y-3"
    x-data="{
        toggleAll(checked) {
            this.$root.querySelectorAll('input[type=checkbox][data-esf-sale-id]').forEach((cb) => { cb.checked = checked; });
        },
        anyChecked() {
            return Array.from(this.$root.querySelectorAll('input[type=checkbox][data-esf-sale-id]')).some((cb) => cb.checked);
        },
    }"
>
    <form id="{{ $bulkFormId }}" method="POST" action="{{ route('admin.esf.queue-bulk') }}" class="flex flex-wrap items-center gap-2 border-b border-slate-200/80 pb-3" @submit="if (!anyChecked()) { $event.preventDefault(); alert('Отметьте хотя бы один документ.'); }">
        @csrf
        <input type="hidden" name="date_from" value="{{ $esfFilter['date_from'] ?? '' }}" />
        <input type="hidden" name="date_to" value="{{ $esfFilter['date_to'] ?? '' }}" />
        <button
            type="submit"
            class="rounded-md border border-emerald-600 bg-emerald-600 px-3 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="!anyChecked()"
        >
            Нужна ЭСФ для выбранных
        </button>
        <span class="text-xs text-slate-600">Отметьте строки галочками и нажмите кнопку — или «Нужна ЭСФ» в строке.</span>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[700px] border-collapse border border-slate-300 text-sm">
            <thead>
                <tr class="bg-slate-100">
                    <th class="border border-slate-300 px-1 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-700 w-10">
                        <input
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300 text-emerald-600"
                            title="Выделить все"
                            @change="toggleAll($event.target.checked)"
                        />
                    </th>
                    <th class="border border-slate-300 px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-700">Дата</th>
                    <th class="border border-slate-300 px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-700">Покупатель</th>
                    <th class="border border-slate-300 px-2 py-2 text-right text-[10px] font-semibold uppercase tracking-wide text-slate-700">Сумма</th>
                    <th class="border border-slate-300 px-2 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-700">Действие</th>
                </tr>
            </thead>
            <tbody class="bg-white">
                @foreach ($sales as $sale)
                    @php
                        $sum = $lineSum($sale);
                    @endphp
                    <tr class="align-top hover:bg-slate-50/80">
                        <td class="border border-slate-300 px-1 py-2 text-center">
                            <input
                                type="checkbox"
                                name="ids[]"
                                value="{{ $sale->id }}"
                                form="{{ $bulkFormId }}"
                                data-esf-sale-id
                                class="h-4 w-4 rounded border-slate-300 text-emerald-600"
                            />
                        </td>
                        <td class="border border-slate-300 whitespace-nowrap px-2 py-2 text-slate-900">{{ $sale->document_date->format('d.m.Y') }}</td>
                        <td class="border border-slate-300 px-2 py-2 text-slate-800">{{ $sale->buyer_name !== '' ? $sale->buyer_name : '—' }}</td>
                        <td class="border border-slate-300 whitespace-nowrap px-2 py-2 text-right tabular-nums text-slate-900">{{ $fmt($sum) }}</td>
                        <td class="border border-slate-300 px-2 py-2 text-center">
                            <form method="POST" action="{{ route('admin.esf.queue', $sale) }}" class="inline">
                                @csrf
                                <input type="hidden" name="date_from" value="{{ $esfFilter['date_from'] ?? '' }}" />
                                <input type="hidden" name="date_to" value="{{ $esfFilter['date_to'] ?? '' }}" />
                                <button
                                    type="submit"
                                    class="rounded-md border border-emerald-600 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-900 hover:bg-emerald-100"
                                >
                                    Нужна ЭСФ
                                </button>
                            </form>
                            <a href="{{ route('admin.legal-entity-sales.edit', $sale) }}" class="ml-2 text-[11px] text-sky-700 underline">Документ</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
