@php
    $fmtMoney = static fn ($v): string => number_format((float) $v, 2, ',', ' ');
@endphp
<x-admin-layout pageTitle="Приход: прочие" main-class="bg-slate-100/80 px-3 py-5 sm:px-6 lg:px-8">
    <div class="mx-auto w-full max-w-5xl space-y-4">
        @include('admin.partials.status-flash')

        <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-sky-800/90">Банк и касса</p>
                <p class="mt-0.5 text-sm text-slate-600">Прочие приходы по филиалу (последние 500 записей).</p>
            </div>
            <a
                href="{{ route('admin.bank.income-other.create') }}"
                class="inline-flex w-full shrink-0 items-center justify-center rounded-xl border border-emerald-800/20 bg-emerald-600 px-5 py-3 text-center text-sm font-bold !text-white no-underline shadow-[0_10px_28px_-6px_rgba(5,150,105,0.55)] transition hover:bg-emerald-500 active:bg-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 sm:w-auto visited:!text-white"
            >
                + Создать операцию
            </a>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
            <div
                class="border-b border-sky-900/10 px-4 py-3 text-white sm:px-5"
                style="background: linear-gradient(120deg, #0284c7 0%, #0d9488 45%, #059669 100%);"
            >
                <h2 class="text-sm font-bold tracking-tight">История операций</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                            <th class="whitespace-nowrap px-4 py-2.5">Дата</th>
                            <th class="whitespace-nowrap px-4 py-2.5 text-right">Сумма</th>
                            <th class="min-w-[10rem] px-4 py-2.5">Счёт / касса</th>
                            <th class="min-w-[10rem] px-4 py-2.5">Категория / назначение</th>
                            <th class="min-w-[8rem] px-4 py-2.5">Комментарий</th>
                            <th class="whitespace-nowrap px-4 py-2.5">Кто внёс</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($movements as $m)
                            <tr
                                class="cursor-pointer transition-colors hover:bg-emerald-50/70 focus-visible:outline focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-400"
                                role="link"
                                tabindex="0"
                                title="Открыть для редактирования"
                                onclick="window.location.href={{ json_encode(route('admin.bank.income-other.edit', $m->id)) }}"
                                onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();window.location.href={{ json_encode(route('admin.bank.income-other.edit', $m->id)) }};}"
                            >
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-slate-800">{{ $m->occurred_on?->format('d.m.Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-emerald-800">{{ $fmtMoney($m->amount) }}</td>
                                <td class="max-w-[14rem] px-4 py-3 text-slate-700" title="{{ $m->ourAccount?->summaryLabel() ?? '' }}">
                                    {{ $m->ourAccount?->summaryLabel() ?? '—' }}
                                </td>
                                <td class="max-w-[14rem] px-4 py-3 text-slate-800" title="{{ $m->expense_category ?? '' }}">
                                    <span class="line-clamp-2">{{ $m->expense_category ? \Illuminate\Support\Str::limit((string) $m->expense_category, 64) : '—' }}</span>
                                </td>
                                <td class="max-w-[14rem] px-4 py-3 text-slate-600" title="{{ $m->comment ?? '' }}">
                                    <span class="line-clamp-2">{{ $m->comment ? \Illuminate\Support\Str::limit((string) $m->comment, 80) : '—' }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $m->user?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500">
                                    Пока нет операций. Нажмите «Создать операцию», чтобы записать прочий приход.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-admin-layout>
