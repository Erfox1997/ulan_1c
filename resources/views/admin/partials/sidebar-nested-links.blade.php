{{-- Один блок ссылок подменю: дерево слева или список во всплывающей панели --}}
<ul class="{{ $nestedUlClass }}">
    @foreach ($item['children'] ?? [] as $child)
        @php
            $childHref = isset($child['route'])
                ? route($child['route'])
                : route('admin.placeholder', ['key' => $child['key']]);
            $childActive = isset($child['route_is'])
                ? (request()->routeIs($child['route_is']) && ! $menuRouteIsNotMatches($child))
                : (isset($child['route'])
                    ? request()->routeIs($child['route'])
                    : (($child['key'] ?? '') === $currentKey));
        @endphp
        <li>
            <a
                href="{{ $childHref }}"
                @class([
                    'flex items-center justify-between gap-2 rounded-lg py-2.5 pl-3 pr-2 text-[14px] leading-[1.45] transition-colors duration-150',
                    'bg-emerald-500/[0.14] font-semibold text-white shadow-[inset_3px_0_0_0_rgb(52,211,153)] ring-1 ring-emerald-500/20' => $childActive,
                    'text-slate-300 hover:bg-white/[0.06] hover:text-white' => ! $childActive,
                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/50 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950',
                ])
                @if (($child['route'] ?? '') === 'admin.reports.goods-characteristics' && ($goodsCharacteristicsIncompleteCount ?? 0) > 0)
                    title="Товаров с неполной характеристикой: {{ $goodsCharacteristicsIncompleteCount }}"
                @endif
                @if (($child['route'] ?? '') === 'admin.service-sales.requests' && ($serviceOrdersAwaitingFulfillmentCount ?? 0) > 0)
                    title="Заявок к оформлению: {{ $serviceOrdersAwaitingFulfillmentCount }}"
                @endif
                @if (($child['route'] ?? '') === 'admin.retail-sales.debts' && ($retailDebtorGroupsCount ?? 0) > 0)
                    title="Групп должников с непогашенным долгом: {{ $retailDebtorGroupsCount }}"
                @endif
                @if (($child['route'] ?? '') === 'admin.purchase-requests.index' && ($purchaseRequestsCount ?? 0) > 0)
                    title="Заявок на закупку в филиале: {{ $purchaseRequestsCount }}"
                @endif
            >
                <span class="min-w-0">{{ $child['label'] }}</span>
                @if (($child['route'] ?? '') === 'admin.reports.goods-characteristics' && ($goodsCharacteristicsIncompleteCount ?? 0) > 0)
                    <span
                        class="shrink-0 rounded-md bg-red-500/30 px-1.5 py-0.5 text-[11px] font-bold tabular-nums text-red-100 ring-1 ring-red-400/45"
                        aria-hidden="true"
                    >+{{ $goodsCharacteristicsIncompleteCount }}</span>
                @endif
                @if (($child['route'] ?? '') === 'admin.service-sales.requests' && ($serviceOrdersAwaitingFulfillmentCount ?? 0) > 0)
                    <span
                        class="shrink-0 rounded-md bg-emerald-500/25 px-1.5 py-0.5 text-[11px] font-bold tabular-nums text-emerald-100 ring-1 ring-emerald-400/40"
                        aria-hidden="true"
                    >+{{ $serviceOrdersAwaitingFulfillmentCount }}</span>
                @endif
                @if (($child['route'] ?? '') === 'admin.retail-sales.debts' && ($retailDebtorGroupsCount ?? 0) > 0)
                    <span
                        class="shrink-0 rounded-md bg-amber-500/30 px-1.5 py-0.5 text-[11px] font-bold tabular-nums text-amber-100 ring-1 ring-amber-400/45"
                        aria-hidden="true"
                    >+{{ $retailDebtorGroupsCount }}</span>
                @endif
                @if (($child['route'] ?? '') === 'admin.purchase-requests.index' && ($purchaseRequestsCount ?? 0) > 0)
                    <span
                        class="shrink-0 rounded-md bg-teal-500/25 px-1.5 py-0.5 text-[11px] font-bold tabular-nums text-teal-100 ring-1 ring-teal-400/40"
                        aria-hidden="true"
                    >+{{ $purchaseRequestsCount }}</span>
                @endif
            </a>
        </li>
    @endforeach
</ul>
