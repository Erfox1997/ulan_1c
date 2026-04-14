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
                <p class="mt-0.5 text-[11px] font-medium text-emerald-100/90">Прочие расходы из раздела «Банк и касса».</p>
            </div>

            <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
                @include('admin.reports.partials.period-filter', [
                    'action' => route('admin.reports.expenses-by-category'),
                    'filterFrom' => $filterFrom,
                    'filterTo' => $filterTo,
                ])
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5">Категория</th>
                            <th class="px-4 py-2.5 text-right">Сумма</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $r)
                            <tr class="hover:bg-emerald-50/30">
                                <td class="px-4 py-2.5 font-medium text-slate-900">{{ $r['category'] }}</td>
                                <td class="px-4 py-2.5 text-right font-semibold tabular-nums text-slate-900">{{ InvoiceNakladnayaFormatter::formatMoney($r['amount']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-12 text-center text-sm text-slate-500">Нет прочих расходов за период.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-admin-layout>
