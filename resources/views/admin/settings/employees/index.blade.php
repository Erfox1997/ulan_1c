@php
    $fmtMoney = static fn ($v) => $v === null || $v === '' ? '—' : number_format((float) $v, 2, ',', ' ');
    $fmtPct = static fn ($v) => $v === null || $v === '' ? '—' : number_format((float) $v, 2, ',', ' ').' %';
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-6xl space-y-6">
        @include('admin.partials.status-flash')

        @if ($errors->has('employee'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ $errors->first('employee') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-slate-900">{{ $pageTitle }}</h1>
                <p class="mt-0.5 text-sm text-slate-600">Учётные записи для входа в кабинет филиала; доступ задаётся ролью.</p>
            </div>
            <a
                href="{{ route('admin.settings.employees.create') }}"
                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
            >
                Добавить сотрудника
            </a>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5">ФИО</th>
                            <th class="px-4 py-2.5">Должность</th>
                            <th class="px-4 py-2.5">Логин (e-mail)</th>
                            <th class="px-4 py-2.5">Роль</th>
                            <th class="px-4 py-2.5 text-right">Оклад</th>
                            <th class="px-4 py-2.5 text-right">% товары</th>
                            <th class="px-4 py-2.5 text-right">% услуги</th>
                            <th class="px-4 py-2.5">Отд. по дог.</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($employees as $e)
                            <tr class="hover:bg-emerald-50/30">
                                <td class="px-4 py-2.5 font-medium text-slate-900">{{ $e->full_name }}</td>
                                <td class="px-4 py-2.5 text-slate-700">{{ $e->jobTypeLabel() }}</td>
                                <td class="px-4 py-2.5 font-mono text-xs text-slate-600">{{ $e->user->email }}</td>
                                <td class="px-4 py-2.5 text-slate-700">
                                    @if ($e->user->branchRole)
                                        {{ $e->user->branchRole->name }}
                                    @else
                                        <span class="text-emerald-800">Полный доступ</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ $fmtMoney($e->salary_fixed) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ $fmtPct($e->salary_percent_goods) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ $fmtPct($e->salary_percent_services) }}</td>
                                <td class="px-4 py-2.5 text-xs text-slate-600">
                                    @if ($e->salary_contract_separate)
                                        <span class="font-semibold text-emerald-800">Да</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <a href="{{ route('admin.settings.employees.edit', $e) }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-900">Изменить</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-10 text-center text-sm text-slate-500">Сотрудников пока нет.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-admin-layout>
