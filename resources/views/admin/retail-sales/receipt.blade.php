@php
    /** @var \App\Models\RetailSale $sale */
    $fmt = static fn ($v) => number_format((float) $v, 2, ',', ' ');
    $backToPosUrl = route('admin.retail-sales.index', array_filter([
        'warehouse_id' => $sale->warehouse_id > 0 ? $sale->warehouse_id : null,
    ], static fn ($v) => (int) $v > 0));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Чек № {{ $sale->id }} — {{ config('app.name', 'Autoelement') }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none !important; }
            .receipt-print-area {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
            }
            body {
                background: white !important;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-b from-slate-100 via-slate-50 to-emerald-50/40 font-sans text-slate-900 antialiased">
    {{-- Верхняя панель (не в печать) --}}
    <header class="no-print sticky top-0 z-30 border-b border-white/60 bg-white/85 backdrop-blur-md shadow-sm shadow-slate-900/5">
        <div class="mx-auto flex max-w-3xl flex-col gap-3 px-3 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5 sm:py-3.5">
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <a
                    href="{{ route('admin.retail-sales.history') }}"
                    class="group inline-flex items-center gap-2 rounded-xl border border-slate-200/90 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-800 shadow-sm ring-1 ring-slate-900/5 transition hover:border-emerald-300 hover:bg-emerald-50/90 hover:text-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-400/50 sm:px-4"
                >
                    <svg class="h-4 w-4 shrink-0 text-emerald-600 transition group-hover:-translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    История продаж
                </a>
                <a
                    href="{{ $backToPosUrl }}"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-2.5 text-sm font-bold tracking-tight text-white shadow-lg shadow-emerald-900/20 ring-1 ring-white/20 transition hover:from-emerald-500 hover:to-teal-500 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2"
                >
                    <svg class="h-4 w-4 opacity-95" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Новая продажа
                </a>
            </div>
            <button
                type="button"
                onclick="window.print()"
                class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50/80 px-4 py-2.5 text-sm font-bold text-emerald-900 shadow-sm transition hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-400/50 sm:w-auto sm:shrink-0"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Печать
            </button>
        </div>
    </header>

    <main class="mx-auto max-w-lg px-3 pb-12 pt-6 sm:px-4 sm:pt-8">
        @if (session('status'))
            <div class="no-print mb-5 rounded-2xl border border-emerald-200/90 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 shadow-inner ring-1 ring-emerald-900/5">
                {{ session('status') }}
            </div>
        @endif

        <article class="receipt-print-area overflow-hidden rounded-2xl border border-emerald-900/10 bg-white shadow-xl shadow-emerald-950/10 ring-1 ring-emerald-900/[0.04]">
            {{-- Шапка чека --}}
            <div
                class="relative px-5 py-6 text-white sm:px-7 sm:py-7"
                style="background: linear-gradient(135deg, #047857 0%, #0d9488 48%, #0f766e 100%);"
            >
                <span class="inline-flex rounded-full bg-white/15 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-50/95 ring-1 ring-white/20">
                    Розница
                </span>
                <h1 class="mt-3 text-2xl font-black tracking-tight sm:text-[1.65rem]">
                    Товарный чек <span class="tabular-nums">№ {{ $sale->id }}</span>
                </h1>
                <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm font-medium text-emerald-50/95">
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        {{ $sale->document_date->format('d.m.Y') }}
                    </span>
                    <span class="text-emerald-200/80" aria-hidden="true">·</span>
                    <span>{{ $sale->warehouse->name ?? 'Склад' }}</span>
                </p>
            </div>

            {{-- Таблица позиций --}}
            <div class="overflow-x-auto border-b border-slate-100">
                <table class="w-full min-w-[18rem] text-left text-sm">
                    <thead>
                        <tr class="bg-slate-50/95 text-[11px] font-bold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3 sm:px-5">Наименование</th>
                            <th class="px-2 py-3 text-right tabular-nums sm:px-3">Кол-во</th>
                            <th class="px-2 py-3 text-right tabular-nums sm:px-3">Цена</th>
                            <th class="px-4 py-3 text-right tabular-nums sm:pr-5">Сумма</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($sale->lines as $line)
                            <tr class="bg-white transition-colors hover:bg-emerald-50/30">
                                <td class="max-w-[12rem] px-4 py-3 font-medium leading-snug text-slate-900 sm:max-w-none sm:px-5">
                                    {{ $line->name }}
                                </td>
                                <td class="whitespace-nowrap px-2 py-3 text-right tabular-nums text-slate-700 sm:px-3">
                                    {{ rtrim(rtrim((string) $line->quantity, '0'), '.') }}
                                </td>
                                <td class="whitespace-nowrap px-2 py-3 text-right tabular-nums text-slate-600 sm:px-3">
                                    {{ $line->unit_price !== null ? $fmt($line->unit_price) : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold tabular-nums text-slate-900 sm:pr-5">
                                    {{ $line->line_sum !== null ? $fmt($line->line_sum) : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Итоги --}}
            <div class="space-y-4 px-4 py-5 sm:px-6 sm:py-6">
                <div class="flex flex-wrap items-end justify-between gap-2 border-b border-dashed border-emerald-200/80 pb-4">
                    <span class="text-xs font-bold uppercase tracking-widest text-slate-500">Итого</span>
                    <span class="text-2xl font-black tabular-nums tracking-tight text-emerald-950">{{ $fmt($sale->total_amount) }} <span class="text-base font-bold text-emerald-800">сом</span></span>
                </div>

                @if ($sale->payments->isNotEmpty())
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Оплата</p>
                        <ul class="mt-2 space-y-2">
                            @foreach ($sale->payments as $p)
                                <li class="flex flex-wrap items-baseline justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2.5 text-sm ring-1 ring-slate-900/5">
                                    <span class="font-medium text-slate-800">{{ $p->organizationBankAccount?->labelWithoutAccountNumber() ?? 'Счёт' }}</span>
                                    <span class="font-bold tabular-nums text-emerald-800">{{ $fmt($p->amount) }} сом</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ((float) $sale->debt_amount > 0.004)
                    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 ring-1 ring-rose-900/5">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-rose-800/90">Долг</p>
                        <p class="mt-1 text-lg font-black tabular-nums text-rose-900">{{ $fmt($sale->debt_amount) }} сом</p>
                        @if ($sale->debtor_name || $sale->debtor_phone)
                            <p class="mt-2 text-sm text-rose-950/90">
                                <span class="font-semibold">{{ $sale->debtor_name }}</span>
                                @if($sale->debtor_phone)<span class="text-rose-800/80"> · </span><span class="tabular-nums">{{ $sale->debtor_phone }}</span>@endif
                            </p>
                        @endif
                    </div>
                @endif

                <p class="flex items-center gap-2 text-xs text-slate-500">
                    <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Кассир: <span class="font-semibold text-slate-700">{{ $sale->user?->name ?? '—' }}</span>
                </p>
            </div>
        </article>

        <p class="no-print mt-8 max-w-md mx-auto text-center text-xs leading-relaxed text-slate-500">
            После закрытия окна печати вы перейдёте к новой продаже. При печати скрываются верхняя панель и кнопки.
        </p>
    </main>
    <script>
        (function () {
            var url = @json($backToPosUrl);
            window.addEventListener('afterprint', function () {
                window.location.href = url;
            });
        })();
    </script>
</body>
</html>
