@props(['pageTitle' => 'Панель'])
@php
    $appName = config('app.name', 'Autoelement');
    $initial = mb_strtoupper(mb_substr($appName, 0, 1));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} — {{ $appName }} · Суперадмин</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
    <div class="flex min-h-screen">
        <aside
            class="relative isolate flex w-[260px] shrink-0 flex-col border-r border-white/5 bg-slate-950 text-slate-300"
            style="background-image: linear-gradient(180deg, rgb(15 23 42 / 0.35) 0%, transparent 28%), linear-gradient(180deg, rgb(2 6 23) 0%, rgb(15 23 42) 100%);"
        >
            <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-emerald-500/40 to-transparent" aria-hidden="true"></div>

            <div class="px-4 pb-5 pt-6">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-sm font-bold text-white shadow-md shadow-emerald-900/40 ring-1 ring-white/10">
                        {{ $initial }}
                    </div>
                    <div class="min-w-0 pt-0.5">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">Суперадмин</p>
                        <p class="truncate text-sm font-semibold leading-snug text-white">{{ $appName }}</p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">Филиалы и учётные записи</p>
                    </div>
                </div>
            </div>

            <p class="px-4 pb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Меню</p>

            <nav class="nav-sidebar-scroll flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto px-3 pb-4" aria-label="Суперадмин">
                <a
                    href="{{ route('superadmin.dashboard') }}"
                    @class([
                        'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                        'border-l-2 border-emerald-400 bg-emerald-500/10 text-white' => request()->routeIs('superadmin.dashboard'),
                        'border-l-2 border-transparent text-slate-400 hover:bg-white/[0.04] hover:text-white' => ! request()->routeIs('superadmin.dashboard'),
                    ])
                >
                    <svg class="h-5 w-5 shrink-0 text-emerald-400/90 group-hover:text-emerald-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25A2.25 2.25 0 0113.5 8.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>
                    </svg>
                    <span class="min-w-0">Панель</span>
                </a>

                <a
                    href="{{ route('superadmin.branches.index') }}"
                    @class([
                        'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                        'border-l-2 border-emerald-400 bg-emerald-500/10 text-white' => request()->routeIs('superadmin.branches.*'),
                        'border-l-2 border-transparent text-slate-400 hover:bg-white/[0.04] hover:text-white' => ! request()->routeIs('superadmin.branches.*'),
                    ])
                >
                    <svg class="h-5 w-5 shrink-0 text-emerald-400/90 group-hover:text-emerald-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/>
                    </svg>
                    <span class="min-w-0">Филиалы</span>
                </a>

                <a
                    href="{{ route('superadmin.admins.index') }}"
                    @class([
                        'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                        'border-l-2 border-emerald-400 bg-emerald-500/10 text-white' => request()->routeIs('superadmin.admins.*'),
                        'border-l-2 border-transparent text-slate-400 hover:bg-white/[0.04] hover:text-white' => ! request()->routeIs('superadmin.admins.*'),
                    ])
                >
                    <svg class="h-5 w-5 shrink-0 text-emerald-400/90 group-hover:text-emerald-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                    </svg>
                    <span class="min-w-0">Администраторы</span>
                </a>

                <a
                    href="{{ route('superadmin.clear-database') }}"
                    @class([
                        'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                        'border-l-2 border-rose-400 bg-rose-500/15 text-white' => request()->routeIs('superadmin.clear-database*'),
                        'border-l-2 border-transparent text-slate-400 hover:bg-white/[0.04] hover:text-white' => ! request()->routeIs('superadmin.clear-database*'),
                    ])
                >
                    <svg class="h-5 w-5 shrink-0 text-rose-400/90 group-hover:text-rose-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                    </svg>
                    <span class="min-w-0">Очистить базу</span>
                </a>
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="shrink-0 border-b border-slate-200/90 bg-white/95 shadow-sm shadow-slate-200/40 backdrop-blur-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-8 sm:py-5">
                    <div class="min-w-0 flex-1">
                        @isset($header)
                            {{ $header }}
                        @else
                            <h1 class="text-lg font-semibold tracking-tight text-slate-900 sm:text-xl">{{ $pageTitle }}</h1>
                        @endisset
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-2 sm:gap-3">
                        <span class="hidden max-w-[14rem] truncate text-sm text-slate-600 sm:inline" title="{{ Auth::user()->name }}">{{ Auth::user()->name }}</span>
                        <a href="{{ route('profile.edit') }}" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
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

            <main class="flex-1 px-4 py-6 sm:px-8 sm:py-8">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
