@php
    use App\Support\InvoiceNakladnayaFormatter;
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-5xl space-y-4">
        <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
            <div
                class="border-b border-emerald-900/10 px-4 py-3 text-white sm:px-5"
                style="background: linear-gradient(120deg, #047857 0%, #0d9488 50%, #0f766e 100%);"
            >
                <h1 class="text-sm font-bold tracking-tight">{{ $pageTitle }}</h1>
                <p class="mt-0.5 text-[11px] font-medium text-emerald-100/90">Остаток на выбранную дату (касса и банковские счета организаций).</p>
            </div>

            <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
                <form method="GET" action="{{ route('admin.reports.cash-balances') }}" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700">На дату</label>
                        <input
                            type="date"
                            name="on"
                            value="{{ $filterOn }}"
                            class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        />
                    </div>
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Показать</button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5">Счёт</th>
                            <th class="px-4 py-2.5">Валюта</th>
                            <th class="px-4 py-2.5 text-right">Остаток</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $r)
                            <tr class="hover:bg-emerald-50/30">
                                <td class="px-4 py-2.5 font-medium text-slate-900">{{ $r['label'] }}</td>
                                <td class="px-4 py-2.5 text-slate-600">{{ $r['currency'] }}</td>
                                <td class="px-4 py-2.5 text-right text-base font-semibold tabular-nums text-slate-900">{{ InvoiceNakladnayaFormatter::formatMoney((float) $r['balance']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-12 text-center text-sm text-slate-500">
                                    Нет счетов. Добавьте организации и расчётные счета в разделе «Данные организации».
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-admin-layout>
