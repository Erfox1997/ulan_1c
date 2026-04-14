@php
    $fmtMoney = static fn ($v) => $v === null || $v === '' ? '—' : number_format((float) $v, 2, ',', ' ');
    $fmtDate = static fn ($d) => $d ? $d->format('d.m.Y') : '—';
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-6xl space-y-6">
        @include('admin.partials.status-flash')

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-slate-900">{{ $pageTitle }}</h1>
                <p class="mt-0.5 text-sm text-slate-600">Удержания и штрафы по сотрудникам филиала.</p>
            </div>
            @if ($employees->isNotEmpty())
                <a
                    href="{{ route('admin.payroll.penalties.create') }}"
                    class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
                >
                    Добавить штраф
                </a>
            @endif
        </div>

        @if ($employees->isEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                Сначала добавьте сотрудников в
                <a href="{{ route('admin.settings.employees') }}" class="font-medium underline">«Настройки → Сотрудники»</a>.
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5">Дата</th>
                            <th class="px-4 py-2.5">Сотрудник</th>
                            <th class="px-4 py-2.5 text-right">Сумма</th>
                            <th class="px-4 py-2.5">Комментарий</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($penalties as $row)
                            <tr class="hover:bg-emerald-50/30">
                                <td class="px-4 py-2.5 tabular-nums text-slate-800">{{ $fmtDate($row->entry_date) }}</td>
                                <td class="px-4 py-2.5 font-medium text-slate-900">{{ $row->employee->full_name }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ $fmtMoney($row->amount) }}</td>
                                <td class="px-4 py-2.5 text-slate-600">{{ \Illuminate\Support\Str::limit($row->note, 80) ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                    <a href="{{ route('admin.payroll.penalties.edit', $row) }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-900">Изменить</a>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.payroll.penalties.destroy', $row) }}"
                                        class="ml-3 inline"
                                        onsubmit="return confirm('Удалить запись?');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-medium text-rose-700 hover:text-rose-900">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">Записей пока нет.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <p class="text-sm text-slate-600">
            <a href="{{ route('admin.payroll') }}" class="font-medium text-emerald-700 hover:text-emerald-900">← К разделу «Зарплата»</a>
        </p>
    </div>
</x-admin-layout>
