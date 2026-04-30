@php
    use App\Support\InvoiceNakladnayaFormatter;
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="min-h-0 bg-gradient-to-b from-slate-100 via-slate-50 to-slate-100 px-3 py-6 sm:px-6 lg:px-10">
    <div class="mx-auto max-w-5xl space-y-6">
        <div
            class="overflow-hidden rounded-[1.25rem] border border-slate-200/80 bg-white shadow-[0_22px_60px_-12px_rgba(15,23,42,0.18)] ring-1 ring-slate-900/[0.04]"
        >
            <header class="border-b border-slate-200 bg-white">
                <div class="h-1 bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700" aria-hidden="true"></div>
                <div class="px-5 py-5 sm:px-7">
                    <p class="inline-flex rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-emerald-900 ring-1 ring-emerald-200/90">
                        Отчёты
                    </p>
                    <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-[1.75rem]">
                        {{ $pageTitle }}
                    </h1>
                </div>
            </header>

            <div
                class="border-b border-slate-100 bg-gradient-to-b from-slate-50/90 to-white px-5 py-5 sm:px-7"
            >
                <form
                    method="GET"
                    action="{{ route('admin.reports.shift-report') }}"
                    class="flex flex-wrap items-end gap-4 rounded-xl border border-slate-200/70 bg-white/90 p-4 shadow-sm shadow-slate-200/40"
                >
                    <div class="min-w-[9.5rem]">
                        <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-slate-700">С даты</label>
                        <input
                            type="date"
                            name="from"
                            value="{{ $filterFrom }}"
                            required
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/25"
                        />
                    </div>
                    <div class="min-w-[9.5rem]">
                        <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-slate-700">По дату</label>
                        <input
                            type="date"
                            name="to"
                            value="{{ $filterTo }}"
                            required
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/25"
                        />
                    </div>
                    <div class="min-w-[12rem] flex-1 sm:max-w-xs">
                        <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-slate-700">Кассир</label>
                        <select
                            name="user_id"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/25"
                        >
                            <option value="0" @selected($selectedUserId === 0)>Все</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected($selectedUserId === (int) $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button
                        type="submit"
                        class="rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-emerald-600/25 transition hover:from-emerald-500 hover:to-teal-500 hover:shadow-lg hover:shadow-emerald-600/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2"
                    >
                        Сформировать
                    </button>
                    <a
                        href="{{ route('admin.reports.shift-report.pdf', request()->query()) }}"
                        class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                    >Скачать PDF</a>
                </form>
            </div>

            <div class="px-5 py-6 sm:px-7 sm:pb-7">
                <div
                    class="overflow-hidden rounded-xl border border-slate-200/90 shadow-[inset_0_1px_0_0_rgba(255,255,255,0.06)] ring-1 ring-slate-900/[0.03]"
                >
                    <div class="overflow-x-auto">
                        <table class="shift-report-table w-full border-collapse text-left text-sm">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3.5">Смена</th>
                                    <th class="px-4 py-3.5">Кассир</th>
                                    <th class="whitespace-nowrap px-4 py-3.5">Открыта</th>
                                    <th class="whitespace-nowrap px-4 py-3.5">Закрыта</th>
                                    <th class="px-4 py-3.5 text-right">На начало</th>
                                    <th class="px-4 py-3.5 text-right">Движение</th>
                                    <th class="px-4 py-3.5">Статус</th>
                                    <th class="px-4 py-3.5"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                @forelse ($shifts as $loopIndex => $shift)
                                    @php
                                        $s = $summaries[$shift->id] ?? ['opening_total' => 0.0, 'movement_total' => 0.0];
                                    @endphp
                                    <tr
                                        class="{{ $loopIndex % 2 === 1 ? 'bg-slate-100/90' : 'bg-white' }} transition-colors hover:bg-emerald-50/45"
                                    >
                                        <td class="border border-slate-200 px-4 py-2.5 font-semibold text-slate-900">
                                            № {{ $shift->id }}
                                        </td>
                                        <td class="border border-slate-200 px-4 py-2.5 text-slate-900">{{ $shift->user?->name ?? '—' }}</td>
                                        <td class="whitespace-nowrap border border-slate-200 px-4 py-2.5 tabular-nums text-slate-900">
                                            {{ $shift->opened_at?->format('d.m.Y H:i') }}
                                        </td>
                                        <td class="whitespace-nowrap border border-slate-200 px-4 py-2.5 tabular-nums text-slate-900">
                                            {{ $shift->closed_at ? $shift->closed_at->format('d.m.Y H:i') : '—' }}
                                        </td>
                                        <td class="border border-slate-200 px-4 py-2.5 text-right tabular-nums text-slate-900">
                                            {{ InvoiceNakladnayaFormatter::formatMoney($s['opening_total']) }}
                                        </td>
                                        <td class="border border-slate-200 px-4 py-2.5 text-right font-semibold tabular-nums text-slate-900">
                                            {{ InvoiceNakladnayaFormatter::formatMoney($s['movement_total']) }}
                                        </td>
                                        <td class="border border-slate-200 px-4 py-2.5">
                                            @if ($shift->closed_at)
                                                <span
                                                    class="inline-flex rounded-full border border-slate-300 bg-slate-200/90 px-2.5 py-0.5 text-[11px] font-semibold text-slate-900"
                                                >
                                                    Закрыта
                                                </span>
                                            @else
                                                <span
                                                    class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[11px] font-semibold text-amber-900"
                                                >
                                                    Открыта
                                                </span>
                                            @endif
                                        </td>
                                        <td class="border border-slate-200 px-4 py-2.5 text-right">
                                            <a
                                                href="{{ route('admin.reports.shift-report.show', $shift) }}"
                                                class="inline-flex rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-200/80 transition hover:bg-emerald-100 hover:text-emerald-950"
                                            >
                                                Подробнее
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="border border-slate-200 px-4 py-14 text-center text-sm font-medium text-slate-700">
                                            Нет смен за выбранный период.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if ($shifts->hasPages())
                <div class="border-t border-slate-100 bg-slate-50/50 px-5 py-4 sm:px-7">{{ $shifts->links() }}</div>
            @endif
        </div>
    </div>
</x-admin-layout>
