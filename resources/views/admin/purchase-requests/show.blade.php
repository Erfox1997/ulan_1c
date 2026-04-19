@php
    /** @var \App\Models\PurchaseRequest $purchaseRequest */
    $fmtQty = static fn ($v) => number_format((float) $v, 2, ',', ' ');
    $fmtDt = static fn ($d) => $d ? $d->format('d.m.Y H:i') : '—';
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="px-3 py-6 sm:px-6 lg:px-8">
    @include('admin.partials.cp-brush')
    <div class="cp-root mx-auto w-full max-w-[min(100%,112rem)] space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Заявка на закупку</p>
                <h1 class="mt-1 text-lg font-semibold text-slate-900">№ {{ $purchaseRequest->id }}</h1>
                <p class="mt-2 text-sm text-slate-600">
                    <span class="tabular-nums">{{ $fmtDt($purchaseRequest->created_at) }}</span>
                    · {{ $purchaseRequest->user?->name ?? '—' }}
                </p>
                @if ($purchaseRequest->note)
                    <p class="mt-3 max-w-2xl rounded-lg border border-slate-200/90 bg-slate-50/90 px-3 py-2 text-sm text-slate-800">
                        {{ $purchaseRequest->note }}
                    </p>
                @endif
            </div>
            <a
                href="{{ route('admin.purchase-requests.index') }}"
                class="inline-flex shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm ring-1 ring-slate-900/5 hover:bg-slate-50"
            >
                ← К списку заявок
            </a>
        </div>

        <div
            class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50"
        >
            <div class="overflow-hidden rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90">
                <div class="border-b border-slate-200/90 px-4 py-3 sm:px-5">
                    <h2 class="text-sm font-semibold text-slate-900">Позиции</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Количество к закупке и снимки остатка на момент создания заявки.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full border-collapse text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2.5">Наименование</th>
                                <th class="whitespace-nowrap px-4 py-2.5">Артикул</th>
                                <th class="min-w-[7rem] px-4 py-2.5">Склад</th>
                                <th class="whitespace-nowrap px-4 py-2.5 text-right">Остаток (снимок)</th>
                                <th class="whitespace-nowrap px-4 py-2.5 text-right">К закупке</th>
                                <th class="whitespace-nowrap px-4 py-2.5 text-right">Мин. ост.</th>
                                <th class="whitespace-nowrap px-4 py-2.5">ОЭМ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($purchaseRequest->lines as $line)
                                <tr class="align-top hover:bg-emerald-50/30">
                                    <td class="px-4 py-2.5 font-medium text-slate-900">{{ $line->good?->name ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-slate-700">{{ $line->good?->article_code ?: '—' }}</td>
                                    <td class="px-4 py-2.5 text-slate-800">{{ $line->warehouse?->name ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-slate-800">
                                        {{ $fmtQty($line->quantity_snapshot) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums font-semibold text-emerald-900">
                                        {{ $fmtQty($line->quantity_requested) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-slate-700">
                                        {{ $line->min_stock_snapshot !== null ? $fmtQty($line->min_stock_snapshot) : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-slate-800">{{ $line->oem_snapshot ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
