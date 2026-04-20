@php
    use App\Support\InvoiceNakladnayaFormatter;
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-6xl space-y-4">
        <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
            <div
                class="border-b border-emerald-900/10 px-4 py-3 text-white sm:px-5"
                style="background: linear-gradient(120deg, #047857 0%, #0d9488 50%, #0f766e 100%);"
            >
                <h1 class="text-sm font-bold tracking-tight">{{ $pageTitle }}</h1>
                <p class="mt-0.5 text-[11px] font-medium text-emerald-100/90">
                    Кассовые смены: кто работал, сколько было на начало и какое движение денег за интервал смены (как при закрытии на «Главном»).
                </p>
            </div>

            <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
                <form method="GET" action="{{ route('admin.reports.shift-report') }}" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700">С даты</label>
                        <input
                            type="date"
                            name="from"
                            value="{{ $filterFrom }}"
                            required
                            class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700">По дату</label>
                        <input
                            type="date"
                            name="to"
                            value="{{ $filterTo }}"
                            required
                            class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700">Кассир</label>
                        <select
                            name="user_id"
                            class="min-w-[12rem] rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        >
                            <option value="0" @selected($selectedUserId === 0)>Все</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected($selectedUserId === (int) $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                        Сформировать
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5">Смена</th>
                            <th class="px-4 py-2.5">Кассир</th>
                            <th class="px-4 py-2.5">Открыта</th>
                            <th class="px-4 py-2.5">Закрыта</th>
                            <th class="px-4 py-2.5 text-right">На начало</th>
                            <th class="px-4 py-2.5 text-right">Движение</th>
                            <th class="px-4 py-2.5">Статус</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($shifts as $shift)
                            @php
                                $s = $summaries[$shift->id] ?? ['opening_total' => 0.0, 'movement_total' => 0.0];
                            @endphp
                            <tr class="hover:bg-emerald-50/30">
                                <td class="px-4 py-2.5 font-medium text-slate-900">№ {{ $shift->id }}</td>
                                <td class="px-4 py-2.5 text-slate-700">{{ $shift->user?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-slate-600">{{ $shift->opened_at?->format('d.m.Y H:i') }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-slate-600">
                                    {{ $shift->closed_at ? $shift->closed_at->format('d.m.Y H:i') : '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-slate-900">
                                    {{ InvoiceNakladnayaFormatter::formatMoney($s['opening_total']) }}
                                </td>
                                <td class="px-4 py-2.5 text-right font-medium tabular-nums text-slate-900">
                                    {{ InvoiceNakladnayaFormatter::formatMoney($s['movement_total']) }}
                                </td>
                                <td class="px-4 py-2.5">
                                    @if ($shift->closed_at)
                                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">Закрыта</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900">Открыта</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <a
                                        href="{{ route('admin.reports.shift-report.show', $shift) }}"
                                        class="text-xs font-semibold text-emerald-700 hover:text-emerald-900 hover:underline"
                                    >
                                        Подробнее
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-sm text-slate-500">
                                    Нет смен за выбранный период.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($shifts->hasPages())
                <div class="border-t border-slate-100 px-4 py-3">{{ $shifts->links() }}</div>
            @endif
        </div>
    </div>
</x-admin-layout>
