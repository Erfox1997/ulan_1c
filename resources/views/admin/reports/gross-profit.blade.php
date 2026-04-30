@php
    use App\Support\InvoiceNakladnayaFormatter;
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-6xl space-y-4">
        <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
            <div
                class="border-b border-emerald-900/10 px-4 py-3 text-white sm:px-5"
                style="background: linear-gradient(120deg, #047857 0%, #0d9488 50%, #0f766e 100%);"
            >
                <h1 class="text-sm font-bold tracking-tight">{{ $pageTitle }}</h1>
                <p class="mt-0.5 text-[11px] font-medium text-emerald-100/90">Выручка минус учётная себестоимость по текущим ценам остатков (оценка).</p>
            </div>

            <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
                <div class="flex flex-wrap items-end gap-3">
                    @include('admin.reports.partials.period-filter', [
                        'action' => route('admin.reports.gross-profit'),
                        'filterFrom' => $filterFrom,
                        'filterTo' => $filterTo,
                    ])
                    <a
                        href="{{ route('admin.reports.gross-profit.pdf', request()->query()) }}"
                        class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                    >Скачать PDF</a>
                </div>
            </div>

            <div class="grid gap-3 border-b border-slate-100 px-4 py-4 sm:grid-cols-3 sm:px-5">
                <div class="rounded-lg bg-slate-50 px-3 py-2.5 ring-1 ring-slate-200/80">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Выручка</p>
                    <p class="mt-1 text-lg font-bold tabular-nums text-slate-900">{{ InvoiceNakladnayaFormatter::formatMoney($revenue) }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 px-3 py-2.5 ring-1 ring-slate-200/80">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Себестоимость</p>
                    <p class="mt-1 text-lg font-bold tabular-nums text-slate-900">{{ InvoiceNakladnayaFormatter::formatMoney($cost) }}</p>
                </div>
                <div class="rounded-lg bg-emerald-50/80 px-3 py-2.5 ring-1 ring-emerald-200/80">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-800">Валовая прибыль</p>
                    <p class="mt-1 text-lg font-bold tabular-nums text-emerald-900">{{ InvoiceNakladnayaFormatter::formatMoney($profit) }}</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5">Артикул</th>
                            <th class="px-4 py-2.5">Товар</th>
                            <th class="px-4 py-2.5 text-right">Кол-во</th>
                            <th class="px-4 py-2.5 text-right">Выручка</th>
                            <th class="px-4 py-2.5 text-right">Себестоимость</th>
                            <th class="px-4 py-2.5 text-right">Прибыль</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($lines as $r)
                            <tr class="hover:bg-emerald-50/30">
                                <td class="px-4 py-2.5 font-mono text-xs text-slate-600">{{ $r['article'] !== '' ? $r['article'] : '—' }}</td>
                                <td class="px-4 py-2.5 font-medium text-slate-900">{{ $r['name'] }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-slate-800">{{ number_format($r['quantity'], 2, ',', ' ') }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ InvoiceNakladnayaFormatter::formatMoney($r['revenue']) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-slate-700">{{ InvoiceNakladnayaFormatter::formatMoney($r['cost']) }}</td>
                                <td class="px-4 py-2.5 text-right font-semibold tabular-nums text-emerald-900">{{ InvoiceNakladnayaFormatter::formatMoney($r['profit']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500">Нет продаж товаров за период.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-admin-layout>
