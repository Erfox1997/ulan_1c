@php
    $currentKey = request()->route()?->parameter('key');
    /** @var string $variant `desktop` — как в макете для ПК; `mobile` — только выезжающая панель &lt; md */
    $variant = $variant ?? 'desktop';
    $sectionsHeadingClass = $variant === 'mobile'
        ? 'shrink-0 px-4 pb-2.5 pr-12 pt-5 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500'
        : 'shrink-0 px-4 pb-2.5 pt-5 pr-4 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500';
@endphp

<div
    class="relative flex h-full min-h-0 flex-col text-[15px] leading-snug"
    style="background: linear-gradient(165deg, #0c1222 0%, #0f172a 42%, #111827 100%);"
>
    <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-emerald-400/25 to-transparent" aria-hidden="true"></div>

    <p class="{{ $sectionsHeadingClass }}">Разделы</p>

    <nav
        class="nav-sidebar-scroll min-h-0 flex-1 overflow-y-auto px-3 pb-5"
        aria-label="Разделы учёта"
        @if ($variant === 'mobile')
            @click.capture="if ($event.target.closest('a')) $dispatch('close-admin-nav')"
        @endif
    >
        @foreach ($menu as $item)
            @if (($item['type'] ?? null) === 'route')
                <a
                    href="{{ route($item['route']) }}"
                    @class([
                        'group mb-1 flex items-center gap-3 rounded-xl px-3 py-3 font-medium transition-colors duration-150',
                        'bg-emerald-500/[0.14] text-white shadow-[inset_3px_0_0_0_rgb(52,211,153)] ring-1 ring-emerald-500/25' => request()->routeIs($item['route']),
                        'text-slate-300 hover:bg-white/[0.06] hover:text-white' => ! request()->routeIs($item['route']),
                        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/50 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950',
                    ])
                >
                    <x-admin.nav-icon class="shrink-0 text-emerald-400 group-hover:text-emerald-300" :name="$item['icon']" />
                    <span class="min-w-0 leading-[1.35]">{{ $item['label'] }}</span>
                </a>
                @continue
            @endif

            @php
                $groupOpen = collect($item['children'] ?? [])->contains(function ($c) use ($currentKey) {
                    if (isset($c['route_is'])) {
                        return request()->routeIs($c['route_is']);
                    }
                    if (isset($c['route'])) {
                        return request()->routeIs($c['route']);
                    }

                    return ($c['key'] ?? '') === $currentKey;
                });
                if (($item['id'] ?? '') === 'stock') {
                    $groupOpen = $groupOpen || request()->routeIs('admin.stock.*');
                }
                if (($item['id'] ?? '') === 'reports') {
                    $groupOpen = $groupOpen || request()->routeIs('admin.reports.*');
                }
                if (($item['id'] ?? '') === 'payroll') {
                    $groupOpen = $groupOpen || request()->routeIs('admin.payroll*');
                }
            @endphp

            <details class="group mb-1.5" @if($groupOpen) open @endif>
                <summary
                    class="flex cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-3 font-medium text-slate-300 transition-colors duration-150 hover:bg-white/[0.06] hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/50 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 [&::-webkit-details-marker]:hidden"
                >
                    <x-admin.nav-icon class="shrink-0 text-emerald-400/95 group-hover:text-emerald-300" :name="$item['icon']" />
                    <span class="min-w-0 flex-1 leading-[1.35]">{{ $item['label'] }}</span>
                    <svg
                        class="h-4 w-4 shrink-0 text-slate-500 transition-transform duration-200 group-open:rotate-180 group-open:text-slate-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </summary>
                <ul class="ml-1.5 mt-2 space-y-0.5 border-l border-white/[0.12] pb-1 pl-4">
                    @foreach ($item['children'] ?? [] as $child)
                        @php
                            $childHref = isset($child['route'])
                                ? route($child['route'])
                                : route('admin.placeholder', ['key' => $child['key']]);
                            $childActive = isset($child['route_is'])
                                ? request()->routeIs($child['route_is'])
                                : (isset($child['route'])
                                    ? request()->routeIs($child['route'])
                                    : (($child['key'] ?? '') === $currentKey));
                        @endphp
                        <li>
                            <a
                                href="{{ $childHref }}"
                                @class([
                                    'block rounded-lg py-2.5 pl-3 pr-2 text-[14px] leading-[1.45] transition-colors duration-150',
                                    'bg-emerald-500/[0.14] font-semibold text-white shadow-[inset_3px_0_0_0_rgb(52,211,153)] ring-1 ring-emerald-500/20' => $childActive,
                                    'text-slate-300 hover:bg-white/[0.06] hover:text-white' => ! $childActive,
                                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/50 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950',
                                ])
                            >
                                {{ $child['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endforeach
    </nav>
</div>
