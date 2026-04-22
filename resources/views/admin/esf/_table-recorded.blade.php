@php
    $esfFilter = $esfFilter ?? ['date_from' => '', 'date_to' => ''];
@endphp
<table class="w-full min-w-[1020px] border-collapse border border-slate-300 text-sm">
    <thead>
        <tr class="bg-slate-100">
            <th class="border border-slate-300 px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-700">Дата</th>
            <th class="border border-slate-300 px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-700">Покупатель</th>
            <th class="border border-slate-300 px-2 py-2 text-right text-[10px] font-semibold uppercase tracking-wide text-slate-700">Сумма</th>
            <th class="border border-slate-300 px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-700 whitespace-nowrap">Товары / услуги</th>
            <th class="border border-slate-300 px-2 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-700">Статус</th>
            <th class="border border-slate-300 px-2 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-700">Действия</th>
        </tr>
    </thead>
    <tbody class="bg-white">
        @foreach ($sales as $row)
            @php
                $sale = $row->sale;
                $esfKind = $row->esf_lines;
                $submittedAt = $row->submitted_at;
                $sum = $lineSum($sale);
            @endphp
            <tr class="align-top bg-slate-50/60">
                <td class="border border-slate-300 whitespace-nowrap px-2 py-2 text-slate-900">{{ $sale->document_date->format('d.m.Y') }}</td>
                <td class="border border-slate-300 px-2 py-2 text-slate-800">{{ $sale->buyer_name !== '' ? $sale->buyer_name : '—' }}</td>
                <td class="border border-slate-300 whitespace-nowrap px-2 py-2 text-right tabular-nums text-slate-900">{{ $fmt($sum) }}</td>
                <td class="border border-slate-300 px-2 py-2 align-middle">
                    @if ($esfKind === 'goods')
                        <span class="inline-flex rounded border border-sky-200 bg-sky-50 px-2 py-0.5 text-[11px] font-semibold text-sky-900">Товары</span>
                    @else
                        <span class="inline-flex rounded border border-violet-200 bg-violet-50 px-2 py-0.5 text-[11px] font-semibold text-violet-900">Услуги</span>
                    @endif
                </td>
                <td class="border border-slate-300 px-2 py-2 text-center text-xs">
                    <span class="inline-flex rounded bg-emerald-100 px-2 py-0.5 font-medium text-emerald-900" title="ЭСФ записана в налоговой">
                        записано {{ $submittedAt->format('d.m.Y H:i') }}
                    </span>
                </td>
                <td class="border border-slate-300 px-2 py-2">
                    <div class="flex flex-col gap-2">
                        <form method="POST" action="{{ route('admin.esf.submitted.clear', $sale) }}">
                            @csrf
                            <input type="hidden" name="esf_lines" value="{{ $esfKind }}" />
                            <input type="hidden" name="date_from" value="{{ $esfFilter['date_from'] ?? '' }}" />
                            <input type="hidden" name="date_to" value="{{ $esfFilter['date_to'] ?? '' }}" />
                            <button
                                type="submit"
                                class="w-full rounded-md border border-amber-200 bg-amber-50 px-2 py-1.5 text-xs font-medium text-amber-950 hover:bg-amber-100"
                                onclick="return confirm('Снять отметку «записано в ЭСФ» для этой части? Можно будет снова скачать XML.');"
                            >
                                Снять отметку
                            </button>
                        </form>
                        <a href="{{ route('admin.legal-entity-sales.edit', $sale) }}" class="text-center text-[11px] text-emerald-700 underline">Документ</a>
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
