@php
    use App\Support\InvoiceNakladnayaFormatter;
    $filterGoodId = (int) ($filterGoodId ?? 0);
    $salesByGoodsUrl = function (array $extra = []) use ($filterFrom, $filterTo, $filterGoodId): string {
        $params = array_merge([
            'from' => $filterFrom,
            'to' => $filterTo,
        ], $extra);
        if ($filterGoodId > 0) {
            $params['good_id'] = $filterGoodId;
        }

        return route('admin.reports.sales-by-goods', array_filter($params, static fn ($v) => $v !== null && $v !== ''));
    };
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-3 sm:px-4 lg:px-6 xl:px-8">
    @include('admin.partials.cp-brush')
    <style>
        .sbg-page .folder-card-metrics {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem 0.75rem;
            align-items: baseline;
            margin-top: 0.25rem;
        }
    </style>
    <div class="sbg-page w-full max-w-none space-y-3">
        <div class="overflow-hidden rounded-lg border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.03]">
            <form
                id="sbg-report-filter"
                method="GET"
                action="{{ route('admin.reports.sales-by-goods') }}"
                data-journal-filter-form
            >
                <input type="hidden" name="good_id" value="{{ $filterGoodId > 0 ? $filterGoodId : '' }}">
                <div
                    class="flex flex-col gap-3 border-b border-emerald-900/10 px-3 py-2.5 text-white sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-4"
                    style="background: linear-gradient(120deg, #047857 0%, #0d9488 50%, #0f766e 100%);"
                >
                    <div class="min-w-0">
                        <h1 class="text-sm font-bold tracking-tight">{{ $pageTitle }}</h1>
                    </div>
                    <div
                        class="flex shrink-0 flex-col gap-2 sm:flex-row sm:items-end sm:gap-3 [&_label]:mb-0.5 [&_label]:text-[10px] [&_label]:text-emerald-50/95 [&_input]:rounded-md [&_input]:border-emerald-800/30 [&_input]:bg-white/95 [&_input]:py-1.5 [&_input]:text-xs [&_input]:text-slate-900 [&_button]:rounded-md [&_button]:py-1.5 [&_button]:px-3 [&_button]:text-xs"
                    >
                        @include('admin.reports.partials.period-filter', [
                            'action' => route('admin.reports.sales-by-goods'),
                            'filterFrom' => $filterFrom,
                            'filterTo' => $filterTo,
                            'preserveQuery' => $filterGoodId > 0
                                ? []
                                : array_filter([
                                    'goods_category' => $goodsCategorySelected ?? null,
                                    'services_category' => $servicesCategorySelected ?? null,
                                ]),
                            'wrapForm' => false,
                        ])
                    </div>
                </div>
                @include('admin.partials.journal-good-filter', [
                    'formSelector' => '#sbg-report-filter',
                    'goodsSearchUrl' => route('admin.goods.search'),
                    'warehouseId' => 0,
                    'filterGoodId' => $filterGoodId,
                    'filterGoodSummary' => $filterGoodSummary ?? '',
                    'returnsUrl' => null,
                    'boxed' => false,
                    'filterTitle' => 'Поиск товара или услуги (название, артикул, штрихкод)',
                ])
            </form>

            @php
                $goodsCatsRootHref = $salesByGoodsUrl($servicesCategorySelected ? ['services_category' => $servicesCategorySelected] : []);
            @endphp

            @if (($goodsCategorySelected ?? null) === null)
                <div class="border-b border-emerald-100/80 bg-gradient-to-r from-emerald-50/90 via-white to-slate-50/80 px-3 py-3 sm:px-4">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-900/85">Категории товаров</p>
                    @if ($totalRevenue > 0)
                        <p class="mt-0.5 text-[11px] text-slate-600">
                            Выручка по товарам за период:
                            <span class="font-semibold tabular-nums text-emerald-900">{{ InvoiceNakladnayaFormatter::formatMoney($totalRevenue) }}</span>
                        </p>
                    @endif
                </div>
                <div class="px-3 py-5 sm:px-4">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @forelse ($goodsFolderRows as $fr)
                            <a
                                href="{{ $salesByGoodsUrl(array_filter([
                                    'goods_category' => $fr['category'],
                                    'services_category' => $servicesCategorySelected ?? null,
                                ])) }}"
                                class="group flex min-h-[5.75rem] flex-col justify-center gap-2 rounded-xl border border-emerald-100 bg-white px-4 py-3.5 shadow-sm transition hover:border-emerald-300 hover:shadow-md hover:shadow-emerald-100/60"
                            >
                                <span class="flex items-start gap-3">
                                    <span
                                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 transition group-hover:bg-emerald-200/90"
                                        aria-hidden="true"
                                    >
                                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="line-clamp-2 font-semibold leading-snug text-slate-900">{{ $fr['category'] }}</span>
                                        <span class="folder-card-metrics">
                                            <span class="text-sm font-bold tabular-nums text-emerald-900">{{ InvoiceNakladnayaFormatter::formatMoney($fr['revenue']) }} сом</span>
                                            <span class="text-[12px] font-semibold tabular-nums text-slate-600">{{ number_format((float) $fr['revenue_share_pct'], 2, ',', ' ') }}%</span>
                                        </span>
                                    </span>
                                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-400 transition group-hover:text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </span>
                            </a>
                        @empty
                            <div class="col-span-full rounded-2xl border border-dashed border-emerald-200 bg-emerald-50/40 px-6 py-14 text-center text-[13px] text-slate-600">
                                Нет продаж товаров за период.
                            </div>
                        @endforelse
                    </div>
                </div>
            @else
                @php
                    $gmFolderMatch = $goodsFolderRows->firstWhere('category', $goodsCategorySelected);
                    $catPctGoods = is_array($gmFolderMatch) ? ($gmFolderMatch['revenue_share_pct'] ?? null) : null;
                @endphp
                <nav class="flex flex-wrap items-center gap-2 border-b border-emerald-100/80 bg-emerald-50/35 px-3 py-2 text-[13px] sm:px-4" aria-label="Навигация по категории товаров">
                    <a href="{{ $goodsCatsRootHref }}" class="rounded-lg px-2.5 py-1 font-semibold text-emerald-800 transition hover:bg-white/80 hover:text-emerald-950">
                        Товары: все категории
                    </a>
                    <span class="text-emerald-200" aria-hidden="true">/</span>
                    <span class="font-semibold text-slate-800">{{ $goodsCategorySelected }}</span>
                    @if ($catPctGoods !== null)
                        <span class="ml-2 text-[11px] font-medium tabular-nums text-slate-600">({{ number_format((float) $catPctGoods, 2, ',', ' ') }}% от всех товаров)</span>
                    @endif
                </nav>
                <div class="min-w-0 overflow-x-auto">
                    <table class="w-full min-w-[480px] table-fixed text-left text-xs">
                        <colgroup>
                            <col />
                            <col class="w-[3.25rem]" />
                            <col class="w-[5.75rem]" />
                            <col class="w-[7rem]" />
                            <col class="w-[4.5rem]" />
                        </colgroup>
                        <thead class="border-b border-slate-200 bg-slate-50/95 text-[9px] font-bold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-2 py-2">Наименование</th>
                                <th class="px-2 py-2">Ед.</th>
                                <th class="px-2 py-2 text-right">Кол-во</th>
                                <th class="px-2 py-2 text-right">Выручка</th>
                                <th class="px-2 py-2 text-right">%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($filteredGoodsRows as $r)
                                <tr class="hover:bg-emerald-50/40">
                                    <td class="px-2 py-1.5 font-medium leading-snug text-slate-900">{{ $r['name'] }}</td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-slate-600">{{ $r['unit'] }}</td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-right tabular-nums text-slate-900">{{ number_format((float) $r['quantity'], 2, ',', ' ') }}</td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-right font-semibold tabular-nums text-slate-900">{{ InvoiceNakladnayaFormatter::formatMoney($r['revenue']) }}</td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-right tabular-nums text-slate-600">{{ number_format((float) $r['revenue_share_pct'], 2, ',', ' ') }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-2 py-8 text-center text-slate-500">Нет позиций или категория не найдена.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($filteredGoodsRows->isNotEmpty())
                            @php
                                $gSumQty = round((float) $filteredGoodsRows->sum('quantity'), 2);
                                $gSumRev = round((float) $filteredGoodsRows->sum('revenue'), 2);
                                $gCatShare = $totalRevenue > 0 ? round($gSumRev / $totalRevenue * 100, 2) : 0.0;
                            @endphp
                            <tfoot class="border-t border-slate-200 bg-slate-50/90 text-xs font-semibold text-slate-900">
                                <tr>
                                    <td class="px-2 py-1.5 text-right text-slate-600">Итого категории</td>
                                    <td class="px-2 py-1.5"></td>
                                    <td class="px-2 py-1.5 text-right tabular-nums">{{ number_format($gSumQty, 2, ',', ' ') }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums">{{ InvoiceNakladnayaFormatter::formatMoney($gSumRev) }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums text-slate-600">{{ number_format($gCatShare, 2, ',', ' ') }}%</td>
                                </tr>
                                <tr class="text-[11px] font-normal text-slate-500">
                                    <td colspan="5" class="px-2 pb-2 pt-0.5 italic">
                                        Колонка «%» для строк — доля каждого товара в общей выручке по товарам; в подвале категории — доля всей категории.
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            @endif

            @php
                $svcCatsRootHref = $salesByGoodsUrl($goodsCategorySelected ? ['goods_category' => $goodsCategorySelected] : []);
            @endphp

            @if (($servicesCategorySelected ?? null) === null)
                <div class="border-t border-violet-200 border-b border-violet-100/90 bg-gradient-to-r from-violet-50/90 via-white to-indigo-50/50 px-3 py-3 sm:px-4">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-violet-900/85">Категории услуг</p>
                    @if ($totalServiceRevenue > 0)
                        <p class="mt-0.5 text-[11px] text-slate-600">
                            Выручка по услугам за период:
                            <span class="font-semibold tabular-nums text-violet-900">{{ InvoiceNakladnayaFormatter::formatMoney($totalServiceRevenue) }}</span>
                        </p>
                    @endif
                </div>
                <div class="px-3 py-5 sm:px-4">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @forelse ($serviceFolderRows as $fr)
                            <a
                                href="{{ $salesByGoodsUrl(array_filter([
                                    'services_category' => $fr['category'],
                                    'goods_category' => $goodsCategorySelected ?? null,
                                ])) }}"
                                class="group flex min-h-[5.75rem] flex-col justify-center gap-2 rounded-xl border border-violet-100 bg-white px-4 py-3.5 shadow-sm transition hover:border-violet-300 hover:shadow-md hover:shadow-violet-100/50"
                            >
                                <span class="flex items-start gap-3">
                                    <span
                                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700 transition group-hover:bg-violet-200/90"
                                        aria-hidden="true"
                                    >
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a7.723 7.723 0 010 .255c-.007.378.138.75.43.991l1.004.827c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a7.916 7.916 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.871a7.52 7.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a7.889 7.889 0 010-.254c-.007-.38.139-.752.431-.993l1.004-.827a1.125 1.125 0 00-.26-1.43l-1.299-2.246a1.125 1.125 0 00-1.37-.491l-1.216.457c-.355.133-.751.073-1.076-.124a7.961 7.961 0 01-.219-.129c-.332-.185-.582-.491-.644-.867L9.936 5.94z"/>
                                            <circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"/>
                                        </svg>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="line-clamp-2 font-semibold leading-snug text-slate-900">{{ $fr['category'] }}</span>
                                        <span class="folder-card-metrics">
                                            <span class="text-sm font-bold tabular-nums text-violet-900">{{ InvoiceNakladnayaFormatter::formatMoney($fr['revenue']) }} сом</span>
                                            <span class="text-[12px] font-semibold tabular-nums text-slate-600">{{ number_format((float) $fr['revenue_share_pct'], 2, ',', ' ') }}%</span>
                                        </span>
                                    </span>
                                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-violet-400 transition group-hover:text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </span>
                            </a>
                        @empty
                            <div class="col-span-full rounded-2xl border border-dashed border-violet-200 bg-violet-50/40 px-6 py-14 text-center text-[13px] text-slate-600">
                                Нет продаж услуг за период.
                            </div>
                        @endforelse
                    </div>
                </div>
            @else
                @php
                    $svFolderMatch = $serviceFolderRows->firstWhere('category', $servicesCategorySelected);
                    $catPctSvc = is_array($svFolderMatch) ? ($svFolderMatch['revenue_share_pct'] ?? null) : null;
                @endphp
                <nav class="flex flex-wrap items-center gap-2 border-t border-violet-200 border-b border-violet-100/90 bg-violet-50/30 px-3 py-2 text-[13px] sm:px-4" aria-label="Навигация по категории услуг">
                    <a href="{{ $svcCatsRootHref }}" class="rounded-lg px-2.5 py-1 font-semibold text-violet-900 transition hover:bg-white/80 hover:text-violet-950">
                        Услуги: все категории
                    </a>
                    <span class="text-violet-200" aria-hidden="true">/</span>
                    <span class="font-semibold text-slate-800">{{ $servicesCategorySelected }}</span>
                    @if ($catPctSvc !== null)
                        <span class="ml-2 text-[11px] font-medium tabular-nums text-slate-600">({{ number_format((float) $catPctSvc, 2, ',', ' ') }}% от всех услуг)</span>
                    @endif
                </nav>
                <div class="min-w-0 overflow-x-auto">
                    <table class="w-full min-w-[480px] table-fixed text-left text-xs">
                        <colgroup>
                            <col />
                            <col class="w-[3.25rem]" />
                            <col class="w-[5.75rem]" />
                            <col class="w-[7rem]" />
                            <col class="w-[4.5rem]" />
                        </colgroup>
                        <thead class="border-b border-slate-200 bg-slate-50/95 text-[9px] font-bold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-2 py-2">Наименование</th>
                                <th class="px-2 py-2">Ед.</th>
                                <th class="px-2 py-2 text-right">Кол-во</th>
                                <th class="px-2 py-2 text-right">Выручка</th>
                                <th class="px-2 py-2 text-right">%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($filteredServiceRows as $r)
                                <tr class="hover:bg-violet-50/40">
                                    <td class="px-2 py-1.5 font-medium leading-snug text-slate-900">{{ $r['name'] }}</td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-slate-600">{{ $r['unit'] }}</td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-right tabular-nums text-slate-900">{{ number_format((float) $r['quantity'], 2, ',', ' ') }}</td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-right font-semibold tabular-nums text-slate-900">{{ InvoiceNakladnayaFormatter::formatMoney($r['revenue']) }}</td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-right tabular-nums text-slate-600">{{ number_format((float) $r['revenue_share_pct'], 2, ',', ' ') }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-2 py-8 text-center text-slate-500">Нет позиций или категория не найдена.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($filteredServiceRows->isNotEmpty())
                            @php
                                $sSumQty = round((float) $filteredServiceRows->sum('quantity'), 2);
                                $sSumRev = round((float) $filteredServiceRows->sum('revenue'), 2);
                                $sCatShare = $totalServiceRevenue > 0 ? round($sSumRev / $totalServiceRevenue * 100, 2) : 0.0;
                            @endphp
                            <tfoot class="border-t border-slate-200 bg-slate-50/90 text-xs font-semibold text-slate-900">
                                <tr>
                                    <td class="px-2 py-1.5 text-right text-slate-600">Итого категории</td>
                                    <td class="px-2 py-1.5"></td>
                                    <td class="px-2 py-1.5 text-right tabular-nums">{{ number_format($sSumQty, 2, ',', ' ') }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums">{{ InvoiceNakladnayaFormatter::formatMoney($sSumRev) }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums text-slate-600">{{ number_format($sCatShare, 2, ',', ' ') }}%</td>
                                </tr>
                                <tr class="text-[11px] font-normal text-slate-500">
                                    <td colspan="5" class="px-2 pb-2 pt-0.5 italic">
                                        «%» по строкам — доля каждой услуги во всей выручке по услугам; в подвале — доля категории.
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
