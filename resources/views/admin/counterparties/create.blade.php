<x-admin-layout pageTitle="Новый контрагент" main-class="relative min-h-[calc(100vh-4rem)] overflow-hidden px-3 py-6 sm:px-6 lg:px-8">
    {{-- Фон страницы: мягкие цветные пятна --}}
    <div class="pointer-events-none absolute inset-0 -z-10">
        <div class="absolute -left-24 top-0 h-[28rem] w-[28rem] rounded-full bg-gradient-to-br from-teal-200/50 via-emerald-100/40 to-transparent blur-3xl"></div>
        <div class="absolute -right-20 bottom-0 h-[26rem] w-[26rem] rounded-full bg-gradient-to-bl from-indigo-200/45 via-violet-100/35 to-transparent blur-3xl"></div>
        <div class="absolute left-1/2 top-1/3 h-64 w-64 -translate-x-1/2 rounded-full bg-cyan-100/30 blur-[80px]"></div>
    </div>

    <div class="relative mx-auto w-full max-w-4xl space-y-6">
        {{-- Шапка страницы --}}
        <div
            class="overflow-hidden rounded-2xl border border-white/70 bg-white/85 shadow-lg shadow-teal-900/5 ring-1 ring-slate-900/5 backdrop-blur-md"
        >
            <div
                class="relative flex flex-col gap-3 bg-gradient-to-r from-teal-600 via-emerald-600 to-cyan-600 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:py-5"
            >
                <div
                    class="pointer-events-none absolute inset-0 opacity-40"
                    style="background: radial-gradient(ellipse 100% 120% at 0% 0%, rgba(255,255,255,0.35), transparent 50%);"
                ></div>
                <div class="relative flex items-start gap-3 sm:items-center">
                    <span
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/20 text-white shadow-inner ring-1 ring-white/30"
                        aria-hidden="true"
                    >
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.8 4.8 0 00-1.123-1.072A4.78 4.78 0 0015.75 16.5a4.75 4.75 0 00-1.5-.25H9.75A4.75 4.75 0 006 20.25v-.75a9.337 9.337 0 004.875-1.5" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 11.625a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.625 21a3.375 3.375 0 013.375-3.375m0 3.375a3.375 3.375 0 013.375 3.375m-13.125-16.875a9.368 9.368 0 011.125-6.563m2.813-2.063A9.374 9.374 0 0112 2.625c4.036 0 7.387 2.594 8.688 6.281" />
                        </svg>
                    </span>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-teal-100">Справочник</p>
                        <h1 class="text-xl font-extrabold tracking-tight text-white sm:text-2xl">
                            Новый контрагент
                        </h1>
                        <p class="mt-0.5 max-w-xl text-sm text-teal-50/95">
                            Заполните карточку: тип, наименование, реквизиты и при необходимости счета.
                        </p>
                    </div>
                </div>
                <a
                    href="{{ route('admin.counterparties.index') }}"
                    class="relative inline-flex items-center justify-center gap-2 rounded-xl border border-white/35 bg-white/15 px-4 py-2.5 text-sm font-semibold text-white shadow-sm backdrop-blur-sm transition hover:bg-white/25 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                >
                    <svg class="h-4 w-4 opacity-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    К списку контрагентов
                </a>
            </div>
        </div>

        @if (session('status'))
            <div
                class="flex items-start gap-3 rounded-2xl border border-emerald-200/90 bg-gradient-to-r from-emerald-50 to-teal-50/80 px-4 py-3 text-sm text-emerald-950 shadow-sm"
                role="status"
            >
                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-700" aria-hidden="true">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @include('admin.counterparties.partials.form', [
            'submitLabel' => 'Записать',
            'showToolbarBackLink' => false,
        ])
    </div>
</x-admin-layout>
