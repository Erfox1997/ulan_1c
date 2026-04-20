@php
    use App\Support\InvoiceNakladnayaFormatter;
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-3 sm:px-4 lg:px-6 xl:px-8">
    <div class="w-full max-w-none space-y-3">
        {{-- Шапка + фильтр в одной компактной полосе --}}
        <div class="overflow-hidden rounded-lg border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.03]">
            <div
                class="flex flex-col gap-3 border-b border-emerald-900/10 px-3 py-2.5 text-white sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-4"
                style="background: linear-gradient(120deg, #047857 0%, #0d9488 50%, #0f766e 100%);"
            >
                <div class="min-w-0">
                    <h1 class="text-sm font-bold tracking-tight">{{ $pageTitle }}</h1>
                    <p class="mt-0.5 text-[10px] font-medium leading-snug text-emerald-100/90">
                        Розница и оптовые продажи юрлицам: отдельно товары и услуги (номенклатура с признаком «услуга»).
                    </p>
                </div>
                <div
                    class="flex shrink-0 flex-col gap-2 sm:flex-row sm:items-end sm:gap-3 [&_label]:mb-0.5 [&_label]:text-[10px] [&_label]:text-emerald-50/95 [&_input]:rounded-md [&_input]:border-emerald-800/30 [&_input]:bg-white/95 [&_input]:py-1.5 [&_input]:text-xs [&_input]:text-slate-900 [&_button]:rounded-md [&_button]:py-1.5 [&_button]:px-3 [&_button]:text-xs"
                >
                    @include('admin.reports.partials.period-filter', [
                        'action' => route('admin.reports.sales-by-goods'),
                        'filterFrom' => $filterFrom,
                        'filterTo' => $filterTo,
                    ])
                    <a
                        href="{{ route('admin.reports.expenses-by-category', ['from' => $filterFrom, 'to' => $filterTo]) }}"
                        class="inline-flex shrink-0 items-center justify-center rounded-md border border-white/45 bg-white/15 px-3 py-2 text-center text-xs font-semibold text-white shadow-sm hover:bg-white/25 sm:py-1.5"
                    >
                        Расходы по категориям
                    </a>
                </div>
            </div>

            {{-- Товары --}}
            <div class="border-b border-slate-200 bg-slate-50/60 px-3 py-2 sm:px-4">
                <h2 class="text-xs font-bold tracking-tight text-slate-800">Товары</h2>
                <p class="mt-0.5 text-[10px] leading-snug text-slate-500">Выручка по позициям без признака услуги.</p>
            </div>
            <div class="lg:grid lg:grid-cols-[minmax(0,1fr)_min(100%,20rem)] lg:items-start lg:gap-0 lg:divide-x lg:divide-slate-100">
                <div class="min-w-0 overflow-x-auto">
                    <table class="w-full min-w-[560px] table-fixed text-left text-xs">
                        <colgroup>
                            <col />
                            <col class="w-[9rem]" />
                            <col class="w-[3rem]" />
                            <col class="w-[5.5rem]" />
                            <col class="w-[7rem]" />
                            <col class="w-[4.5rem]" />
                        </colgroup>
                        <thead class="border-b border-slate-200 bg-slate-50/95 text-[9px] font-bold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-2 py-1.5">Наименование</th>
                                <th class="px-2 py-1.5">Категория</th>
                                <th class="px-2 py-1.5">Ед.</th>
                                <th class="px-2 py-1.5 text-right">Кол-во</th>
                                <th class="px-2 py-1.5 text-right">Выручка</th>
                                <th class="px-2 py-1.5 text-right">%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($rows as $r)
                                <tr class="hover:bg-emerald-50/40">
                                    <td class="px-2 py-1.5 font-medium leading-snug text-slate-900">{{ $r['name'] }}</td>
                                    <td class="max-w-0 truncate px-2 py-1.5 text-slate-600" title="{{ $r['category'] }}">{{ $r['category'] }}</td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-slate-600">{{ $r['unit'] }}</td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-right tabular-nums text-slate-900">{{ number_format($r['quantity'], 2, ',', ' ') }}</td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-right font-semibold tabular-nums text-slate-900">{{ InvoiceNakladnayaFormatter::formatMoney($r['revenue']) }}</td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-right tabular-nums text-slate-600">{{ number_format($r['revenue_share_pct'], 2, ',', ' ') }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-2 py-8 text-center text-slate-500">Нет продаж за период.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($rows->isNotEmpty())
                            <tfoot class="border-t border-slate-200 bg-slate-50/90 text-xs font-semibold text-slate-900">
                                <tr>
                                    <td colspan="4" class="px-2 py-1.5 text-right text-slate-600">Итого</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums">{{ InvoiceNakladnayaFormatter::formatMoney($totalRevenue) }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums text-slate-500">100,00%</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                <aside class="border-t border-slate-100 bg-slate-50/40 lg:border-t-0 lg:bg-white">
                    <div class="border-b border-slate-100 px-3 py-2 lg:border-b lg:bg-gradient-to-r lg:from-emerald-800/95 lg:to-teal-800/90 lg:px-3 lg:py-2 lg:text-white">
                        <h3 class="text-xs font-bold tracking-tight text-slate-800 lg:text-white">По категориям (товары)</h3>
                        <p class="mt-0.5 text-[10px] leading-snug text-slate-500 lg:text-emerald-100/85">
                            Доля категории в выручке по товарам.
                        </p>
                    </div>
                    <div class="overflow-x-auto lg:max-h-[min(70vh,48rem)] lg:overflow-y-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="sticky top-0 z-[1] border-b border-slate-200 bg-slate-50/95 text-[9px] font-bold uppercase tracking-wide text-slate-500 backdrop-blur-sm">
                                <tr>
                                    <th class="px-2 py-1.5">Категория</th>
                                    <th class="px-2 py-1.5 text-right">Выручка</th>
                                    <th class="w-14 px-2 py-1.5 text-right">%</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($categoryRows as $cr)
                                    <tr class="hover:bg-emerald-50/50">
                                        <td class="max-w-0 truncate px-2 py-1.5 font-medium text-slate-900" title="{{ $cr['category'] }}">{{ $cr['category'] }}</td>
                                        <td class="whitespace-nowrap px-2 py-1.5 text-right font-semibold tabular-nums text-slate-900">{{ InvoiceNakladnayaFormatter::formatMoney($cr['revenue']) }}</td>
                                        <td class="whitespace-nowrap px-2 py-1.5 text-right tabular-nums text-slate-600">{{ number_format($cr['revenue_share_pct'], 2, ',', ' ') }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-2 py-8 text-center text-slate-500">Нет данных.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if ($categoryRows->isNotEmpty())
                                <tfoot class="border-t border-slate-200 bg-slate-50/90 text-xs font-semibold text-slate-900">
                                    <tr>
                                        <td class="px-2 py-1.5 text-slate-600">Итого</td>
                                        <td class="px-2 py-1.5 text-right tabular-nums">{{ InvoiceNakladnayaFormatter::formatMoney($totalRevenue) }}</td>
                                        <td class="px-2 py-1.5 text-right tabular-nums text-slate-500">100,00%</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </aside>
            </div>

            {{-- Услуги --}}
            <div class="border-t border-slate-200 bg-violet-50/50 px-3 py-2 sm:px-4">
                <h2 class="text-xs font-bold tracking-tight text-slate-800">Услуги</h2>
                <p class="mt-0.5 text-[10px] leading-snug text-slate-500">Выручка по номенклатуре с признаком «услуга» (розница и юрлица).</p>
            </div>
            <div class="lg:grid lg:grid-cols-[minmax(0,1fr)_min(100%,20rem)] lg:items-start lg:gap-0 lg:divide-x lg:divide-slate-100">
                <div class="min-w-0 overflow-x-auto">
                    <table class="w-full min-w-[560px] table-fixed text-left text-xs">
                        <colgroup>
                            <col />
                            <col class="w-[9rem]" />
                            <col class="w-[3rem]" />
                            <col class="w-[5.5rem]" />
                            <col class="w-[7rem]" />
                            <col class="w-[4.5rem]" />
                        </colgroup>
                        <thead class="border-b border-slate-200 bg-slate-50/95 text-[9px] font-bold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-2 py-1.5">Наименование</th>
                                <th class="px-2 py-1.5">Категория</th>
                                <th class="px-2 py-1.5">Ед.</th>
                                <th class="px-2 py-1.5 text-right">Кол-во</th>
                                <th class="px-2 py-1.5 text-right">Выручка</th>
                                <th class="px-2 py-1.5 text-right">%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($serviceRows as $r)
                                <tr class="hover:bg-violet-50/50">
                                    <td class="px-2 py-1.5 font-medium leading-snug text-slate-900">{{ $r['name'] }}</td>
                                    <td class="max-w-0 truncate px-2 py-1.5 text-slate-600" title="{{ $r['category'] }}">{{ $r['category'] }}</td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-slate-600">{{ $r['unit'] }}</td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-right tabular-nums text-slate-900">{{ number_format($r['quantity'], 2, ',', ' ') }}</td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-right font-semibold tabular-nums text-slate-900">{{ InvoiceNakladnayaFormatter::formatMoney($r['revenue']) }}</td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-right tabular-nums text-slate-600">{{ number_format($r['revenue_share_pct'], 2, ',', ' ') }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-2 py-8 text-center text-slate-500">Нет продаж услуг за период.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($serviceRows->isNotEmpty())
                            <tfoot class="border-t border-slate-200 bg-slate-50/90 text-xs font-semibold text-slate-900">
                                <tr>
                                    <td colspan="4" class="px-2 py-1.5 text-right text-slate-600">Итого</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums">{{ InvoiceNakladnayaFormatter::formatMoney($totalServiceRevenue) }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums text-slate-500">100,00%</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                <aside class="border-t border-slate-100 bg-slate-50/40 lg:border-t-0 lg:bg-white">
                    <div class="border-b border-slate-100 px-3 py-2 lg:border-b lg:bg-gradient-to-r lg:from-violet-800/95 lg:to-indigo-800/90 lg:px-3 lg:py-2 lg:text-white">
                        <h3 class="text-xs font-bold tracking-tight text-slate-800 lg:text-white">По категориям (услуги)</h3>
                        <p class="mt-0.5 text-[10px] leading-snug text-slate-500 lg:text-violet-100/85">
                            Доля категории в выручке по услугам.
                        </p>
                    </div>
                    <div class="overflow-x-auto lg:max-h-[min(70vh,48rem)] lg:overflow-y-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="sticky top-0 z-[1] border-b border-slate-200 bg-slate-50/95 text-[9px] font-bold uppercase tracking-wide text-slate-500 backdrop-blur-sm">
                                <tr>
                                    <th class="px-2 py-1.5">Категория</th>
                                    <th class="px-2 py-1.5 text-right">Выручка</th>
                                    <th class="w-14 px-2 py-1.5 text-right">%</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($serviceCategoryRows as $cr)
                                    <tr class="hover:bg-violet-50/50">
                                        <td class="max-w-0 truncate px-2 py-1.5 font-medium text-slate-900" title="{{ $cr['category'] }}">{{ $cr['category'] }}</td>
                                        <td class="whitespace-nowrap px-2 py-1.5 text-right font-semibold tabular-nums text-slate-900">{{ InvoiceNakladnayaFormatter::formatMoney($cr['revenue']) }}</td>
                                        <td class="whitespace-nowrap px-2 py-1.5 text-right tabular-nums text-slate-600">{{ number_format($cr['revenue_share_pct'], 2, ',', ' ') }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-2 py-8 text-center text-slate-500">Нет данных.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if ($serviceCategoryRows->isNotEmpty())
                                <tfoot class="border-t border-slate-200 bg-slate-50/90 text-xs font-semibold text-slate-900">
                                    <tr>
                                        <td class="px-2 py-1.5 text-slate-600">Итого</td>
                                        <td class="px-2 py-1.5 text-right tabular-nums">{{ InvoiceNakladnayaFormatter::formatMoney($totalServiceRevenue) }}</td>
                                        <td class="px-2 py-1.5 text-right tabular-nums text-slate-500">100,00%</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</x-admin-layout>
