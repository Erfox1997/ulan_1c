@php
    /** @var \App\Models\RetailSale $sale */
    $fmt = static fn ($v) => number_format((float) $v, 2, ',', ' ');
    $tz = config('app.timezone') ?: 'UTC';
    $receiptAt = $sale->created_at
        ? $sale->created_at->timezone($tz)->format('d.m.Y H:i')
        : now()->timezone($tz)->format('d.m.Y H:i');
    $storeTitle = trim((string) ($sale->branch?->name ?? '')) !== ''
        ? trim((string) $sale->branch->name)
        : config('app.name', 'Магазин');
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
        /* Слип как на 58/80 мм термопринтере: узкая колонка, моноширинные суммы */
        .receipt-slip {
            max-width: 80mm;
            margin-left: auto;
            margin-right: auto;
            font-family: ui-monospace, 'Cascadia Mono', 'Consolas', 'Roboto Mono', monospace;
            font-size: 11.5px;
            line-height: 1.4;
            color: #0a0a0a;
            background: #fff;
        }
        .receipt-slip .receipt-center {
            text-align: center;
        }
        .receipt-slip .receipt-rule {
            border: none;
            border-top: 1px dashed #1a1a1a;
            margin: 0.55rem 0;
        }
        .receipt-slip .receipt-rule-thick {
            border-top-style: solid;
            border-top-width: 1px;
        }
        .receipt-slip .receipt-row {
            display: flex;
            justify-content: space-between;
            gap: 0.5rem;
            align-items: baseline;
        }
        .receipt-slip .receipt-grow {
            flex: 1;
            min-width: 0;
        }
        .receipt-slip .receipt-num {
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
        @media print {
            .no-print { display: none !important; }
            .receipt-print-wrap {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
                max-width: none !important;
            }
            body {
                background: #fff !important;
            }
            @page {
                margin: 5mm;
                size: auto;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
    <header class="no-print sticky top-0 z-30 border-b border-slate-200/90 bg-white/95 backdrop-blur shadow-sm">
        <div class="mx-auto flex max-w-3xl flex-col gap-3 px-3 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5 sm:py-3.5">
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <a
                    href="{{ route('admin.retail-sales.history') }}"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:border-emerald-300 hover:bg-emerald-50"
                >
                    <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    История продаж
                </a>
                <a
                    href="{{ $backToPosUrl }}"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow hover:bg-emerald-700"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Новая продажа
                </a>
            </div>
            <button
                type="button"
                onclick="window.print()"
                class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-900 shadow-sm hover:bg-slate-50 sm:w-auto"
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
            <div class="no-print mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        <div class="receipt-print-wrap rounded-lg border border-slate-300 bg-white p-4 shadow-md sm:p-5">
            <article class="receipt-slip receipt-print-area">
                <div class="receipt-center">
                    <p class="text-[13px] font-bold uppercase leading-tight tracking-wide">{{ $storeTitle }}</p>
                    @if (trim((string) ($sale->branch?->code ?? '')) !== '')
                        <p class="mt-0.5 text-[10px] text-neutral-600">код филиала: {{ $sale->branch->code }}</p>
                    @endif
                    <p class="mt-1 text-[10px] uppercase tracking-[0.12em] text-neutral-500">товарный чек (розница)</p>
                </div>

                <hr class="receipt-rule">

                <div class="space-y-0.5 text-[11px]">
                    <div class="receipt-row">
                        <span class="text-neutral-600">Чек №</span>
                        <span class="receipt-num font-semibold">{{ $sale->id }}</span>
                    </div>
                    <div class="receipt-row">
                        <span class="text-neutral-600">Дата / время</span>
                        <span class="receipt-num">{{ $receiptAt }}</span>
                    </div>
                    <div class="receipt-row">
                        <span class="text-neutral-600">Дата док.</span>
                        <span class="receipt-num">{{ $sale->document_date->format('d.m.Y') }}</span>
                    </div>
                    @if ($sale->warehouse)
                        <div class="receipt-row">
                            <span class="text-neutral-600">Точка</span>
                            <span class="receipt-grow text-right font-medium">{{ $sale->warehouse->name }}</span>
                        </div>
                    @endif
                </div>

                <hr class="receipt-rule">

                <div class="space-y-3">
                    @foreach ($sale->lines as $line)
                        @php
                            $qtyRaw = rtrim(rtrim((string) $line->quantity, '0'), '.');
                            $unit = trim((string) ($line->unit ?? ''));
                            $qtyLabel = $unit !== '' ? $qtyRaw.' '.$unit : $qtyRaw;
                        @endphp
                        <div>
                            <p class="font-semibold leading-snug">{{ $line->name }}</p>
                            @if (trim((string) ($line->article_code ?? '')) !== '')
                                <p class="mt-0.5 text-[10px] text-neutral-500">арт. {{ $line->article_code }}</p>
                            @endif
                            <div class="receipt-row mt-1 text-[11px]">
                                <span class="text-neutral-600">{{ $qtyLabel }} × {{ $line->unit_price !== null ? $fmt($line->unit_price) : '—' }}</span>
                                <span class="receipt-num font-semibold">{{ $line->line_sum !== null ? $fmt($line->line_sum) : '—' }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <hr class="receipt-rule receipt-rule-thick">

                <div class="receipt-row text-[12px] font-bold">
                    <span>ИТОГО</span>
                    <span class="receipt-num">{{ $fmt($sale->total_amount) }}</span>
                </div>
                <p class="receipt-center mt-0.5 text-[10px] text-neutral-600">сом (KGS)</p>

                @if ($sale->payments->isNotEmpty())
                    <hr class="receipt-rule">
                    <p class="mb-1 text-[10px] font-bold uppercase tracking-wide text-neutral-600">Оплата</p>
                    <div class="space-y-1.5">
                        @foreach ($sale->payments as $p)
                            @php
                                $acc = $p->organizationBankAccount;
                                $payLabel = $acc?->labelWithoutAccountNumber() ?? 'Оплата';
                            @endphp
                            <div>
                                <div class="receipt-row text-[11px]">
                                    <span class="receipt-grow pr-2 text-left leading-tight">{{ $payLabel }}</span>
                                    <span class="receipt-num font-semibold">{{ $fmt($p->amount) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ((float) $sale->debt_amount > 0.004)
                    <hr class="receipt-rule">
                    <div class="rounded border border-dashed border-neutral-800 px-2 py-2">
                        <div class="receipt-row text-[11px] font-bold">
                            <span>ДОЛГ</span>
                            <span class="receipt-num">{{ $fmt($sale->debt_amount) }}</span>
                        </div>
                        @if ($sale->debtor_name || $sale->debtor_phone)
                            <p class="mt-1 text-[10px] leading-snug">
                                <span class="font-semibold">{{ $sale->debtor_name }}</span>
                                @if ($sale->debtor_phone)
                                    <span class="text-neutral-600"> · </span><span class="receipt-num">{{ $sale->debtor_phone }}</span>
                                @endif
                            </p>
                        @endif
                    </div>
                @endif

                <hr class="receipt-rule">

                <div class="receipt-center space-y-1 text-[11px] font-semibold leading-snug">
                    <p>Спасибо за покупку!</p>
                    <p class="text-[10px] font-normal text-neutral-600">Сохраняйте чек до выхода из магазина</p>
                </div>

                <hr class="receipt-rule">

                <div class="text-[10px] text-neutral-600">
                    <div class="receipt-row">
                        <span>Кассир</span>
                        <span class="receipt-grow text-right font-medium text-neutral-900">{{ $sale->user?->name ?? '—' }}</span>
                    </div>
                </div>
            </article>
        </div>

        <p class="no-print mt-6 text-center text-xs text-slate-500">
            После закрытия окна печати вы перейдёте к новой продаже. При печати скрывается эта подсказка и панель сверху.
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
