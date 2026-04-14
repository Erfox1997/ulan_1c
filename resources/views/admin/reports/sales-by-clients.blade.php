@php
    use App\Support\InvoiceNakladnayaFormatter;
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-4xl space-y-4">
        <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
            <div
                class="border-b border-emerald-900/10 px-4 py-3 text-white sm:px-5"
                style="background: linear-gradient(120deg, #047857 0%, #0d9488 50%, #0f766e 100%);"
            >
                <h1 class="text-sm font-bold tracking-tight">{{ $pageTitle }}</h1>
                <p class="mt-0.5 text-[11px] font-medium text-emerald-100/90">Розница суммарно и реализации юрлицам по покупателю. Себестоимость и валовая прибыль — по товарам (без услуг), по учётной цене остатков; выручка в первой колонке — полная по документам.</p>
            </div>

            <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
                @include('admin.reports.partials.period-filter', [
                    'action' => route('admin.reports.sales-by-clients'),
                    'filterFrom' => $filterFrom,
                    'filterTo' => $filterTo,
                ])
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5">Клиент / сегмент</th>
                            <th class="px-4 py-2.5 text-right">Выручка</th>
                            <th class="px-4 py-2.5 text-right">Себестоимость</th>
                            <th class="px-4 py-2.5 text-right">Валовая прибыль</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $r)
                            <tr class="hover:bg-emerald-50/30">
                                <td class="px-4 py-2.5 font-medium text-slate-900">{{ $r['label'] }}</td>
                                <td class="px-4 py-2.5 text-right font-semibold tabular-nums text-slate-900">{{ InvoiceNakladnayaFormatter::formatMoney($r['revenue']) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-slate-800">{{ InvoiceNakladnayaFormatter::formatMoney($r['cost']) }}</td>
                                <td class="px-4 py-2.5 text-right font-semibold tabular-nums @if ($r['profit'] >= 0) text-emerald-800 @else text-rose-700 @endif">{{ InvoiceNakladnayaFormatter::formatMoney($r['profit']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-12 text-center text-sm text-slate-500">Нет данных за период.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50/90 text-sm font-semibold text-slate-900">
                            <tr>
                                <td class="px-4 py-3">Итого</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ InvoiceNakladnayaFormatter::formatMoney($totals['revenue']) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ InvoiceNakladnayaFormatter::formatMoney($totals['cost']) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums @if ($totals['profit'] >= 0) text-emerald-800 @else text-rose-700 @endif">{{ InvoiceNakladnayaFormatter::formatMoney($totals['profit']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</x-admin-layout>
