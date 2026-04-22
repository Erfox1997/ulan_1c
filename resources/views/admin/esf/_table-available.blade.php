@php
    $esfFilter = $esfFilter ?? ['date_from' => '', 'date_to' => ''];
    $bulkFormId = 'esf-queue-bulk-form';
@endphp
<div
    class="space-y-3"
    x-data="{
        toggleAll(checked) {
            this.$root.querySelectorAll('input[type=checkbox][data-esf-queue-item]').forEach((cb) => { cb.checked = checked; });
        },
        anyChecked() {
            return Array.from(this.$root.querySelectorAll('input[type=checkbox][data-esf-queue-item]')).some((cb) => cb.checked);
        },
    }"
>
    <form id="{{ $bulkFormId }}" method="POST" action="{{ route('admin.esf.queue-bulk') }}" class="flex flex-wrap items-center gap-2 border-b border-slate-200/80 pb-3" @submit="if (!anyChecked()) { $event.preventDefault(); alert('Отметьте хотя бы одну часть (товары и/или услуги).'); }">
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
        <span class="text-xs text-slate-600">Для документов с товарами и услугами отмечайте товары и услуги отдельно — в одном XML их нельзя совместить.</span>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[960px] border-collapse border border-slate-300 text-sm">
            <thead>
                <tr class="bg-slate-100">
                    <th class="border border-slate-300 px-1 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-700 w-32">
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
                    <th class="border border-slate-300 px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-700 whitespace-nowrap">Товары / услуги</th>
                    <th class="border border-slate-300 px-2 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-700">Действие</th>
                </tr>
            </thead>
            <tbody class="bg-white">
                @foreach ($sales as $sale)
                    @php
                        $sum = $lineSum($sale);
                        $p = $sale->esfGoodsServicesLinesProfile();
                        $canQueueGoods = $p['has_goods'] && ! $sale->esf_queue_goods && $sale->esf_submitted_goods_at === null;
                        $canQueueServices = $p['has_services'] && ! $sale->esf_queue_services && $sale->esf_submitted_services_at === null;
                        $hasCheckboxes = $canQueueGoods || $canQueueServices;
                    @endphp
                    <tr class="align-top hover:bg-slate-50/80">
                        <td class="border border-slate-300 px-1 py-2 text-left align-top">
                            @if ($hasCheckboxes)
                                <div class="flex flex-col gap-1.5 pl-1">
                                    @if ($canQueueGoods)
                                        <label class="inline-flex items-center gap-1.5 text-[11px] text-slate-800">
                                            <input
                                                type="checkbox"
                                                name="queue_items[]"
                                                value="{{ $sale->id }}:goods"
                                                form="{{ $bulkFormId }}"
                                                data-esf-queue-item
                                                class="h-4 w-4 rounded border-slate-300 text-emerald-600"
                                            />
                                            товары
                                        </label>
                                    @endif
                                    @if ($canQueueServices)
                                        <label class="inline-flex items-center gap-1.5 text-[11px] text-slate-800">
                                            <input
                                                type="checkbox"
                                                name="queue_items[]"
                                                value="{{ $sale->id }}:services"
                                                form="{{ $bulkFormId }}"
                                                data-esf-queue-item
                                                class="h-4 w-4 rounded border-slate-300 text-emerald-600"
                                            />
                                            услуги
                                        </label>
                                    @endif
                                </div>
                            @else
                                <div class="px-1 text-center text-slate-400">—</div>
                            @endif
                        </td>
                        <td class="border border-slate-300 whitespace-nowrap px-2 py-2 text-slate-900">{{ $sale->document_date->format('d.m.Y') }}</td>
                        <td class="border border-slate-300 px-2 py-2 text-slate-800">{{ $sale->buyer_name !== '' ? $sale->buyer_name : '—' }}</td>
                        <td class="border border-slate-300 whitespace-nowrap px-2 py-2 text-right tabular-nums text-slate-900">{{ $fmt($sum) }}</td>
                        <td class="border border-slate-300 px-2 py-2 align-middle">
                            @include('admin.esf._esf-kind-badges', ['esfProfile' => $p])
                        </td>
                        <td class="border border-slate-300 px-2 py-2 text-center">
                            <div class="flex flex-wrap items-center justify-center gap-1.5">
                                @if ($canQueueGoods)
                                    <form method="POST" action="{{ route('admin.esf.queue', $sale) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="esf_lines" value="goods" />
                                        <input type="hidden" name="date_from" value="{{ $esfFilter['date_from'] ?? '' }}" />
                                        <input type="hidden" name="date_to" value="{{ $esfFilter['date_to'] ?? '' }}" />
                                        <button
                                            type="submit"
                                            class="rounded-md border border-emerald-600 bg-emerald-50 px-2 py-1.5 text-[11px] font-semibold text-emerald-900 hover:bg-emerald-100"
                                        >
                                            @if ($p['mixed'] || $p['has_services']) Нужна ЭСФ — товары @else Нужна ЭСФ @endif
                                        </button>
                                    </form>
                                @endif
                                @if ($canQueueServices)
                                    <form method="POST" action="{{ route('admin.esf.queue', $sale) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="esf_lines" value="services" />
                                        <input type="hidden" name="date_from" value="{{ $esfFilter['date_from'] ?? '' }}" />
                                        <input type="hidden" name="date_to" value="{{ $esfFilter['date_to'] ?? '' }}" />
                                        <button
                                            type="submit"
                                            class="rounded-md border border-violet-500 bg-violet-50 px-2 py-1.5 text-[11px] font-semibold text-violet-900 hover:bg-violet-100"
                                        >
                                            @if ($p['mixed'] || $p['has_goods']) Нужна ЭСФ — услуги @else Нужна ЭСФ @endif
                                        </button>
                                    </form>
                                @endif
                                <a href="{{ route('admin.legal-entity-sales.edit', $sale) }}" class="text-[11px] text-sky-700 underline">Документ</a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
