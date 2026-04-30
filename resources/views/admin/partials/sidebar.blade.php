@php
    $currentKey = request()->route()?->parameter('key');
    $menuRouteIsNotMatches = function (array $c): bool {
        if (! isset($c['route_is_not'])) {
            return false;
        }
        foreach ((array) $c['route_is_not'] as $pattern) {
            if (is_string($pattern) && $pattern !== '' && request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    };
    /** @var string $variant `desktop` — как в макете для ПК; `mobile` — только выезжающая панель &lt; md */
    $variant = $variant ?? 'desktop';
    /** ПК-боковая панель с режимом «только иконки»; мобильный drawer всегда полный */
    $collapseAware = $collapseAware ?? (($variant ?? 'desktop') !== 'mobile');
    $appName = $appName ?? config('app.name', 'App');
    $sectionsHeadingClass = $variant === 'mobile'
        ? 'shrink-0 px-4 pb-2.5 pr-12 pt-5 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500'
        : 'shrink-0 px-4 pb-2.5 pt-5 pr-4 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500';
@endphp

<div
    class="relative flex h-full min-h-0 flex-col text-[15px] leading-snug"
    style="background: linear-gradient(165deg, #0c1222 0%, #0f172a 42%, #111827 100%);"
>
    <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-emerald-400/25 to-transparent" aria-hidden="true"></div>

    @if ($collapseAware)
        <div
            class="flex shrink-0 items-center gap-2 border-b border-white/[0.07] py-3 transition-[padding] duration-300"
            :class="sidebarCollapsed ? 'justify-center px-2' : 'justify-start pl-2 pr-3'"
        >
            <button
                type="button"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/[0.06] text-slate-100 shadow-sm transition-colors hover:bg-white/[0.11] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/50 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950"
                @click="toggleSidebar()"
                :aria-expanded="sidebarCollapsed ? 'false' : 'true'"
                aria-controls="admin-desktop-nav-inner"
                aria-label="Свернуть или развернуть боковое меню"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16"/>
                </svg>
            </button>
            <span
                class="min-w-0 flex-1 truncate text-[15px] font-semibold leading-tight tracking-tight text-white"
                x-show="!sidebarCollapsed"
                x-transition.opacity.duration.150ms
                title="{{ $appName }}"
            >{{ $appName }}</span>
        </div>
    @endif

    <p
        class="{{ $sectionsHeadingClass }}"
        @if ($collapseAware)
            x-show="!sidebarCollapsed"
            x-transition.opacity.duration.150ms
        @endif
    >Разделы</p>

    <nav
        id="admin-desktop-nav-inner"
        class="nav-sidebar-scroll min-h-0 flex-1 overflow-y-auto pb-5 @unless ($collapseAware) px-3 @endunless md:transition-[padding] md:duration-300"
        @if ($collapseAware)
            :class="sidebarCollapsed ? 'px-1.5' : 'px-3'"
        @endif
        aria-label="Разделы учёта"
        @if ($variant === 'mobile')
            @click.capture="if ($event.target.closest('a')) $dispatch('close-admin-nav')"
        @endif
    >
        @foreach ($menu as $item)
            @if (($item['type'] ?? null) === 'route')
                <a
                    href="{{ route($item['route']) }}"
                    @if ($collapseAware)
                        @click="if (sidebarCollapsed) { $event.preventDefault(); expandSidebar(); }"
                    @endif
                    @class([
                        'group mb-1 flex items-center gap-3 rounded-xl px-3 py-3 font-medium transition-colors duration-150',
                        'bg-emerald-500/[0.14] text-white shadow-[inset_3px_0_0_0_rgb(52,211,153)] ring-1 ring-emerald-500/25' => request()->routeIs($item['route']),
                        'text-slate-300 hover:bg-white/[0.06] hover:text-white' => ! request()->routeIs($item['route']),
                        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/50 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950',
                    ])
                    title="{{ $item['label'] }}"
                    @if ($collapseAware)
                        :class="sidebarCollapsed ? '!gap-0 justify-center px-2' : ''"
                    @endif
                >
                    <x-admin.nav-icon class="shrink-0 text-emerald-400 group-hover:text-emerald-300" :name="$item['icon']" />
                    <span
                        class="min-w-0 leading-[1.35]"
                        @if ($collapseAware)
                            x-show="!sidebarCollapsed"
                        @endif
                    >{{ $item['label'] }}</span>
                </a>
                @continue
            @endif

            @php
                $childRouteMatches = function (array $c) use ($menuRouteIsNotMatches): bool {
                    if (isset($c['route_is'])) {
                        $match = request()->routeIs($c['route_is']);
                        if ($match && $menuRouteIsNotMatches($c)) {
                            $match = false;
                        }

                        return $match;
                    }
                    if (isset($c['route'])) {
                        return request()->routeIs($c['route']);
                    }

                    return false;
                };
                $groupOpen = collect($item['children'] ?? [])->contains(function ($c) use ($currentKey, $childRouteMatches) {
                    if (isset($c['route_is']) || isset($c['route'])) {
                        return $childRouteMatches($c);
                    }

                    return ($c['key'] ?? '') === $currentKey;
                });
                if (($item['id'] ?? '') === 'goods-services') {
                    $groupOpen = $groupOpen || request()->routeIs('admin.stock.*');
                }
                if (($item['id'] ?? '') === 'payroll') {
                    $groupOpen = $groupOpen || request()->routeIs('admin.payroll*');
                }
            @endphp

            @if ($collapseAware)
                <details
                    class="group mb-1.5"
                    x-show="!sidebarCollapsed"
                    x-transition.opacity.duration.150ms
                    @if ($groupOpen) open @endif
                >
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
                    @include('admin.partials.sidebar-nested-links', [
                        'item' => $item,
                        'nestedUlClass' => 'ml-1.5 mt-2 space-y-0.5 border-l border-white/[0.12] pb-1 pl-4',
                        'menuRouteIsNotMatches' => $menuRouteIsNotMatches,
                        'currentKey' => $currentKey,
                    ])
                </details>

                <div
                    class="relative z-[55] mb-1.5"
                    x-show="sidebarCollapsed"
                    x-transition.opacity.duration.150ms
                    x-cloak
                >
                    <button
                        type="button"
                        @class([
                            'flex w-full cursor-pointer items-center justify-center rounded-xl px-2 py-3 font-medium transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/50 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950',
                            'bg-emerald-500/[0.14] text-white shadow-[inset_3px_0_0_0_rgb(52,211,153)] ring-1 ring-emerald-500/25' => $groupOpen,
                            'text-slate-300 hover:bg-white/[0.06] hover:text-white' => ! $groupOpen,
                        ])
                        title="{{ $item['label'] }}"
                        @click.stop="expandSidebar()"
                        aria-label="{{ $item['label'] }}, развернуть меню"
                    >
                        <x-admin.nav-icon class="shrink-0 text-emerald-400/95" :name="$item['icon']" />
                    </button>
                </div>
            @else
                <details class="group mb-1.5" @if ($groupOpen) open @endif>
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
                    @include('admin.partials.sidebar-nested-links', [
                        'item' => $item,
                        'nestedUlClass' => 'ml-1.5 mt-2 space-y-0.5 border-l border-white/[0.12] pb-1 pl-4',
                        'menuRouteIsNotMatches' => $menuRouteIsNotMatches,
                        'currentKey' => $currentKey,
                    ])
                </details>
            @endif
        @endforeach
    </nav>
</div>
