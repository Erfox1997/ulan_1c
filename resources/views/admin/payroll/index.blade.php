@php
    $fmtMoney = static fn ($v) => $v === null || $v === '' ? '—' : number_format((float) $v, 2, ',', ' ');
    $periodQuery = ['period_from' => $periodFrom, 'period_to' => $periodTo];
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-7xl space-y-6">
        @include('admin.partials.status-flash')

        @if ($errors->has('shift'))
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">{{ $errors->first('shift') }}</div>
        @endif

        @if ($errors->has('payroll'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ $errors->first('payroll') }}</div>
        @endif

        <div>
            <h1 class="text-lg font-semibold text-slate-900">{{ $pageTitle }}</h1>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white p-4 shadow-md ring-1 ring-slate-900/[0.04]">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Сотрудников</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ $employeeCount }}</p>
            </div>
            <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white p-4 shadow-md ring-1 ring-slate-900/[0.04]">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Сумма авансов (всего)</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ $fmtMoney($advanceSum) }}</p>
            </div>
            <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white p-4 shadow-md ring-1 ring-slate-900/[0.04]">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Сумма штрафов (всего)</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ $fmtMoney($penaltySum) }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
            <div
                class="border-b border-emerald-900/10 px-4 py-3 text-white sm:px-5"
                style="background: linear-gradient(120deg, #047857 0%, #0d9488 50%, #0f766e 100%);"
            >
                <h2 class="text-sm font-bold tracking-tight">Период расчёта</h2>
                <p class="mt-0.5 text-[11px] font-medium text-emerald-100/90">По умолчанию — текущий месяц.</p>
            </div>
            <form method="GET" action="{{ route('admin.payroll') }}" class="flex flex-wrap items-end gap-4 px-4 py-4 sm:px-6">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">С даты</label>
                    <input
                        type="date"
                        name="period_from"
                        value="{{ $periodFrom }}"
                        class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">По дату</label>
                    <input
                        type="date"
                        name="period_to"
                        value="{{ $periodTo }}"
                        class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                    />
                </div>
                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                    Применить
                </button>
            </form>
        </div>

        @if ($employees->isEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-950">
                Нет сотрудников. Добавьте их в «Настройки → Сотрудники».
            </div>
        @else
            @php
                $contractEmployees = $employees->where('salary_contract_separate', true);
                $showManualForm = $contractEmployees->isNotEmpty() && ! ($periodInvalid ?? false);
            @endphp
            <form
                id="payroll-manual-accruals"
                method="POST"
                action="{{ route('admin.payroll.manual-accruals') }}"
                class="hidden"
                aria-hidden="true"
            >
                @csrf
                <input type="hidden" name="period_from" value="{{ $periodFrom }}" />
                <input type="hidden" name="period_to" value="{{ $periodTo }}" />
            </form>
            <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
                <div class="flex flex-col gap-2 border-b border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <h2 class="text-sm font-bold text-slate-900">Сотрудники</h2>
                    @if ($showManualForm)
                        <button
                            type="submit"
                            form="payroll-manual-accruals"
                            class="w-full shrink-0 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-900 shadow-sm hover:bg-emerald-100 sm:w-auto"
                        >
                            Сохранить суммы по договору
                        </button>
                    @endif
                </div>
                @if ($showManualForm)
                    <p class="border-b border-slate-100 bg-slate-50/50 px-4 py-2 text-xs text-slate-600 sm:px-5">
                        Сумма за период — только у сотрудников с галочкой «отдельная зарплата по договору» в карточке; входит в «к выплате» вместе с окладом и процентами, минус авансы и штрафы.
                    </p>
                @endif
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[42rem] text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2.5 sm:px-5">Сотрудник</th>
                                <th class="px-2 py-2.5 whitespace-nowrap">Статус</th>
                                <th class="px-2 py-2.5 text-right whitespace-nowrap">К выплате</th>
                                @if ($showManualForm)
                                    <th class="px-2 py-2.5 text-right whitespace-nowrap">По договору</th>
                                @endif
                                <th class="w-28 px-2 py-2.5 text-right sm:w-36"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($employees as $emp)
                                @php
                                    $net = $netByEmployeeId->get($emp->id);
                                    $payout = $payoutByEmployeeId->get($emp->id);
                                    $hasPayout = $payout !== null;
                                    $manualRow = $manualByEmployeeId->get($emp->id);
                                @endphp
                                <tr class="hover:bg-emerald-50/50">
                                    <td class="px-4 py-3 align-top sm:px-5">
                                        <a
                                            href="{{ route('admin.payroll.show', $emp) }}?{{ http_build_query($periodQuery) }}"
                                            class="font-semibold text-emerald-900 underline decoration-emerald-600/35 hover:text-emerald-950 hover:decoration-emerald-700"
                                        >{{ $emp->full_name }}</a>
                                        <p class="mt-0.5 text-xs text-slate-500">{{ $emp->jobTypeLabel() }}</p>
                                    </td>
                                    <td class="px-2 py-3 align-middle whitespace-nowrap">
                                        @if ($periodInvalid ?? false)
                                            <span class="text-xs text-slate-400">—</span>
                                        @elseif ($hasPayout)
                                            <span class="inline-flex rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-900">Выплачено</span>
                                        @elseif ($net !== null && (float) $net > 0)
                                            <span class="inline-flex rounded-md bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-950">Не выплачено</span>
                                        @else
                                            <span class="text-xs text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-3 align-middle text-right tabular-nums">
                                        @if ($periodInvalid ?? false)
                                            <span class="text-slate-400">—</span>
                                        @else
                                            <span
                                                @class([
                                                    'font-semibold',
                                                    'text-rose-700' => $net !== null && (float) $net < 0,
                                                    'text-slate-900' => $net === null || (float) $net >= 0,
                                                ])
                                            >
                                                {{ $net !== null ? $fmtMoney($net) : '—' }}
                                            </span>
                                        @endif
                                    </td>
                                    @if ($showManualForm)
                                        <td class="px-2 py-3 align-middle text-right">
                                            @if ($emp->salary_contract_separate)
                                                <input
                                                    type="text"
                                                    inputmode="decimal"
                                                    name="amounts[{{ $emp->id }}]"
                                                    form="payroll-manual-accruals"
                                                    value="{{ old('amounts.'.$emp->id, $manualRow !== null ? $manualRow->amount : '') }}"
                                                    class="w-[6.5rem] rounded-lg border border-slate-200 px-2 py-1.5 text-right text-sm tabular-nums text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500/25"
                                                    placeholder="0"
                                                    @focus="$event.target.select()"
                                                    @click="$event.target.select()"
                                                />
                                            @else
                                                <span class="text-slate-300">—</span>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="px-2 py-3 align-middle text-right">
                                        @if (! ($periodInvalid ?? false) && $hasPayout)
                                            <form
                                                method="POST"
                                                action="{{ route('admin.payroll.revoke-payout', $emp) }}"
                                                class="inline"
                                                onsubmit="return confirm('Снять отметку «выплачено» за этот период? Запись о расходе в кассе будет удалена.');"
                                            >
                                                @csrf
                                                <input type="hidden" name="period_from" value="{{ $periodFrom }}" />
                                                <input type="hidden" name="period_to" value="{{ $periodTo }}" />
                                                <button
                                                    type="submit"
                                                    class="text-xs font-semibold text-slate-600 underline decoration-slate-400 underline-offset-2 hover:text-rose-800 hover:decoration-rose-700"
                                                >
                                                    Снять выплату
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($periodInvalid ?? false)
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                Дата «с» не может быть позже даты «по» — исправьте период выше.
            </div>
        @endif
    </div>
</x-admin-layout>
