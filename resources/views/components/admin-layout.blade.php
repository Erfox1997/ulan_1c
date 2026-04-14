@props(['pageTitle' => 'Кабинет', 'mainClass' => null])
@php
    $branch = auth()->user()->branch;
    $branchName = $branch?->name ?? 'Филиал';
    $appName = config('app.name', 'Autoelement');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} — {{ $branchName }} — {{ $appName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
    <div
        class="flex min-h-screen flex-col md:flex-row"
        x-data="{ navOpen: false }"
        x-init="window.addEventListener('resize', () => { if (window.matchMedia('(min-width: 768px)').matches) navOpen = false }); $watch('navOpen', (v) => { document.body.style.overflow = v && window.innerWidth < 768 ? 'hidden' : '' })"
        @keydown.escape.window="navOpen = false"
        @close-admin-nav.window="navOpen = false"
    >
        {{-- Только телефон: оверлей + выезжающая панель (admin-mobile-only в app.css скрывает на ПК) --}}
        <div class="admin-mobile-only">
            <div
                x-cloak
                x-show="navOpen"
                x-transition.opacity.duration.200ms
                class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-[1px]"
                @click="navOpen = false"
                aria-hidden="true"
            ></div>

            <aside
                id="admin-nav-drawer"
                class="fixed inset-y-0 left-0 z-50 flex w-[min(19.5rem,90vw)] min-w-0 flex-col border-r border-white/10 bg-slate-950 shadow-xl transition-transform duration-300 ease-out"
                :class="navOpen ? 'translate-x-0' : '-translate-x-full'"
                role="navigation"
                aria-label="Меню разделов"
            >
                @include('admin.partials.sidebar', ['variant' => 'mobile'])
                <button
                    type="button"
                    class="pointer-events-auto absolute right-2 top-2 z-[100] flex h-11 w-11 items-center justify-center rounded-xl bg-white/15 text-white shadow-sm ring-1 ring-white/20 hover:bg-white/25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/80"
                    @click.stop.prevent="navOpen = false"
                    aria-label="Закрыть меню"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </aside>
        </div>

        {{-- Только ПК: колонка в потоке, без fixed/тени/крестика (admin-desktop-sidebar в app.css) --}}
        <aside
            class="admin-desktop-sidebar w-[300px] shrink-0 min-h-screen border-r border-white/5 bg-slate-950"
            aria-label="Разделы учёта"
        >
            @include('admin.partials.sidebar')
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="shrink-0 border-b border-slate-200/90 bg-white/95 shadow-sm shadow-slate-200/40 backdrop-blur-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 flex-1 items-start gap-3">
                        <button
                            type="button"
                            class="admin-mobile-only mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200/90 bg-white text-slate-800 shadow-sm hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
                            @click="navOpen = true"
                            :aria-expanded="navOpen ? 'true' : 'false'"
                            aria-controls="admin-nav-drawer"
                            aria-label="Открыть меню"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16"/>
                            </svg>
                        </button>
                        <div class="hidden min-w-0 flex-1 md:block">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Администратор филиала</p>
                            <h1 class="truncate text-lg font-semibold tracking-tight text-slate-900">{{ $pageTitle }}</h1>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                        <span class="hidden max-w-[12rem] truncate text-sm text-slate-600 md:inline" title="{{ Auth::user()->name }}">{{ Auth::user()->name }}</span>
                        <a href="{{ route('profile.edit') }}" class="hidden rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 md:inline-flex">
                            Профиль
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-slate-800">
                                Выход
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            @isset($header)
                <div class="border-b border-emerald-200/50 bg-emerald-50/60 px-4 py-3 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            @endisset

            <main class="flex-1 {{ $mainClass ?? 'px-4 py-6 sm:px-6 lg:px-8' }}">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
