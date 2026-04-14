<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Autoelement') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $appName = config('app.name', 'Autoelement');
    $initial = mb_strtoupper(mb_substr($appName, 0, 1));
@endphp
<body class="min-h-screen bg-slate-950 font-sans text-slate-100 antialiased">
    {{-- Фон: градиент + сетка + мягкие блики --}}
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <div class="absolute -left-1/4 top-0 h-[600px] w-[600px] rounded-full bg-emerald-500/15 blur-[120px]"></div>
        <div class="absolute -right-1/4 bottom-0 h-[500px] w-[500px] rounded-full bg-teal-600/10 blur-[100px]"></div>
        <div class="absolute inset-0 bg-[linear-gradient(to_right,rgb(255_255_255/0.04)_1px,transparent_1px),linear-gradient(to_bottom,rgb(255_255_255/0.04)_1px,transparent_1px)] bg-[size:72px_72px] [mask-image:radial-gradient(ellipse_80%_60%_at_50%_0%,black,transparent)]"></div>
    </div>

    <header class="border-b border-white/5 bg-slate-950/70 backdrop-blur-xl">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-sm font-bold text-white shadow-lg shadow-emerald-900/40 ring-1 ring-white/10">
                    {{ $initial }}
                </div>
                <span class="truncate text-lg font-semibold tracking-tight text-white">{{ $appName }}</span>
            </div>
            <nav class="flex shrink-0 items-center gap-2 sm:gap-3">
                @auth
                    <a
                        href="{{ url('/dashboard') }}"
                        class="inline-flex items-center rounded-lg border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-white transition hover:border-emerald-500/40 hover:bg-emerald-500/10"
                    >
                        Панель
                    </a>
                @else
                    <a
                        href="{{ route('login') }}"
                        class="inline-flex items-center rounded-lg bg-gradient-to-r from-emerald-500 to-teal-600 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-emerald-900/30 ring-1 ring-white/10 transition hover:from-emerald-400 hover:to-teal-500"
                    >
                        Войти
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    <main>
        <section class="mx-auto max-w-4xl px-4 pb-20 pt-16 text-center sm:px-6 sm:pt-24">
            <p class="mb-4 text-xs font-semibold uppercase tracking-[0.25em] text-emerald-400/90 sm:text-sm">
                Учётная система
            </p>
            <h1 class="bg-gradient-to-b from-white to-slate-400 bg-clip-text text-4xl font-bold tracking-tight text-transparent sm:text-5xl sm:leading-tight md:text-6xl">
                Учёт для магазинов автозапчастей
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-slate-400 sm:text-lg">
                Единая платформа для филиалов: запасы, продажи, касса и отчёты — в привычной логике, без лишней сложности.
            </p>
            @guest
                <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                    <a
                        href="{{ route('login') }}"
                        class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 px-8 py-3.5 text-base font-semibold text-white shadow-xl shadow-emerald-900/40 ring-1 ring-white/10 transition hover:from-emerald-400 hover:to-teal-500 hover:shadow-emerald-800/50"
                    >
                        Войти в систему
                    </a>
                </div>
            @else
                <div class="mt-10">
                    <a
                        href="{{ url('/dashboard') }}"
                        class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 px-8 py-3.5 text-base font-semibold text-white shadow-xl shadow-emerald-900/40 ring-1 ring-white/10 transition hover:from-emerald-400 hover:to-teal-500"
                    >
                        Перейти в панель
                    </a>
                </div>
            @endguest
        </section>

        <section class="mx-auto max-w-6xl px-4 pb-24 sm:px-6">
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <article class="group rounded-2xl border border-white/10 bg-white/[0.03] p-6 shadow-xl shadow-black/20 backdrop-blur-sm transition hover:border-emerald-500/30 hover:bg-white/[0.05]">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/20">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008z"/>
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-white">Несколько филиалов</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-400">
                        Суперадмин ведёт филиалы и назначает администраторов — у каждого магазина свой контур данных.
                    </p>
                </article>
                <article class="group rounded-2xl border border-white/10 bg-white/[0.03] p-6 shadow-xl shadow-black/20 backdrop-blur-sm transition hover:border-emerald-500/30 hover:bg-white/[0.05]">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/20">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-white">Запасы и продажи</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-400">
                        Товары, закупки, продажи и движение по складам — в одном интерфейсе администратора филиала.
                    </p>
                </article>
                <article class="group rounded-2xl border border-white/10 bg-white/[0.03] p-6 shadow-xl shadow-black/20 backdrop-blur-sm transition hover:border-emerald-500/30 hover:bg-white/[0.05] sm:col-span-2 lg:col-span-1">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/20">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-white">Касса и отчёты</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-400">
                        Деньги по счетам и кассе, плюс отчёты по остаткам, продажам и прибыли — для контроля бизнеса.
                    </p>
                </article>
            </div>
        </section>
    </main>

    <footer class="border-t border-white/5 py-8">
        <div class="mx-auto max-w-6xl px-4 text-center sm:px-6">
            <p class="text-xs text-slate-600">
                {{ $appName }}
                <span class="mx-2 text-slate-700">·</span>
                <span class="text-slate-500">Laravel {{ app()->version() }}</span>
            </p>
        </div>
    </footer>
</body>
</html>
