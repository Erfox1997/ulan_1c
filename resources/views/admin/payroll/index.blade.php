@php
    $fmtMoney = static fn ($v) => $v === null || $v === '' ? '—' : number_format((float) $v, 2, ',', ' ');
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-7xl space-y-6">
        @include('admin.partials.status-flash')

        @if ($errors->has('shift'))
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">{{ $errors->first('shift') }}</div>
        @endif

        <div>
            <h1 class="text-lg font-semibold text-slate-900">{{ $pageTitle }}</h1>
            <p class="mt-0.5 text-sm text-slate-600">
                Справочник сотрудников, оклады и проценты задаются в
                <a href="{{ route('admin.settings.employees') }}" class="font-medium text-emerald-700 underline decoration-emerald-600/40 hover:text-emerald-900">«Настройки → Сотрудники»</a>.
                Розница и заказы услуг учитываются по пользователю, оформившему документ; авансы и штрафы — по дате записи в выбранном периоде.
            </p>
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
                <h2 class="text-sm font-bold tracking-tight">Расчёт и выплата зарплаты</h2>
                <p class="mt-0.5 text-[11px] font-medium text-emerald-100/90">
                    Укажите период (по умолчанию текущий месяц). Оклад в расчёте — полная сумма из карточки; проценты — от оборота розницы и услуг за период.
                </p>
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
                    Показать расчёт
                </button>
            </form>

            @if ($periodInvalid ?? false)
                <div class="border-t border-slate-100 px-4 py-4 text-sm text-rose-800 sm:px-6">
                    Дата «с» не может быть позже даты «по».
                </div>
            @elseif (count($calculationLines) > 0)
                @php
                    $totGoods = collect($calculationLines)->sum('goods_turnover');
                    $totServ = collect($calculationLines)->sum('services_turnover');
                    $totFixed = collect($calculationLines)->sum('fixed');
                    $totGCom = collect($calculationLines)->sum('goods_commission');
                    $totSCom = collect($calculationLines)->sum('services_commission');
                    $totAdv = collect($calculationLines)->sum('advances');
                    $totPen = collect($calculationLines)->sum('penalties');
                    $totAcc = collect($calculationLines)->sum('accrual');
                    $totNet = collect($calculationLines)->sum('net');
                    $payableCount = collect($calculationLines)->filter(fn ($r) => $r['net'] > 0 && ! $payoutByEmployee->has($r['employee']->id))->count();
                @endphp
                <div class="border-t border-slate-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-2.5">Сотрудник</th>
                                    <th class="px-3 py-2.5 text-right">Оборот розницы</th>
                                    <th class="px-3 py-2.5 text-right">Оборот услуг</th>
                                    <th class="px-3 py-2.5 text-right">Оклад</th>
                                    <th class="px-3 py-2.5 text-right">% с тов.</th>
                                    <th class="px-3 py-2.5 text-right">% с усл.</th>
                                    <th class="px-3 py-2.5 text-right">Авансы</th>
                                    <th class="px-3 py-2.5 text-right">Штрафы</th>
                                    <th class="px-3 py-2.5 text-right">Начислено</th>
                                    <th class="px-3 py-2.5 text-right">К выплате</th>
                                    <th class="px-3 py-2.5">Выплата</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($calculationLines as $row)
                                    @php
                                        $e = $row['employee'];
                                        $paid = $payoutByEmployee->get($e->id);
                                    @endphp
                                    <tr class="hover:bg-emerald-50/30">
                                        <td class="px-3 py-2.5 font-medium text-slate-900">{{ $e->full_name }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums text-slate-800">{{ $fmtMoney($row['goods_turnover']) }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums text-slate-800">{{ $fmtMoney($row['services_turnover']) }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmtMoney($row['fixed']) }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmtMoney($row['goods_commission']) }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmtMoney($row['services_commission']) }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmtMoney($row['advances']) }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmtMoney($row['penalties']) }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums font-medium">{{ $fmtMoney($row['accrual']) }}</td>
                                        <td
                                            @class([
                                                'px-3 py-2.5 text-right tabular-nums font-semibold',
                                                'text-rose-700' => $row['net'] < 0,
                                            ])
                                        >
                                            {{ $fmtMoney($row['net']) }}
                                        </td>
                                        <td class="px-3 py-2.5 text-xs text-slate-600">
                                            @if ($paid)
                                                <span class="font-medium text-emerald-800">Выплачено {{ $fmtMoney($paid->amount) }}</span>
                                            @elseif ($row['net'] <= 0)
                                                —
                                            @else
                                                <span class="text-slate-500">Ожидает</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                <tr class="border-t border-slate-200 bg-slate-50/90 font-semibold">
                                    <td class="px-3 py-2.5 text-slate-800">Итого</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmtMoney($totGoods) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmtMoney($totServ) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmtMoney($totFixed) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmtMoney($totGCom) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmtMoney($totSCom) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmtMoney($totAdv) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmtMoney($totPen) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmtMoney($totAcc) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmtMoney($totNet) }}</td>
                                    <td class="px-3 py-2.5"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($accounts->isEmpty())
                    <div class="border-t border-slate-100 px-4 py-4 text-sm text-amber-950 sm:px-6">
                        Нет счетов/касс организации — добавьте их в «Данные организации», чтобы фиксировать выплату в кассе.
                    </div>
                @else
                    <div class="border-t border-slate-200 px-4 py-4 sm:px-6">
                        <p class="mb-3 text-sm text-slate-600">
                            Выплата создаётся как расход «Прочие» с категорией «Зарплата» на выбранную кассу/счёт. Нужна
                            <span class="font-medium text-slate-800">открытая кассовая смена</span> у текущего пользователя (как при прочих расходах).
                        </p>
                        <form method="POST" action="{{ route('admin.payroll.payout') }}" class="flex flex-wrap items-end gap-4">
                            @csrf
                            <input type="hidden" name="period_from" value="{{ $periodFrom }}" />
                            <input type="hidden" name="period_to" value="{{ $periodTo }}" />
                            <div class="min-w-[220px] flex-1">
                                <label class="mb-1 block text-xs font-semibold text-slate-700">Счёт / касса для списания</label>
                                <select
                                    name="our_account_id"
                                    required
                                    class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                >
                                    <option value="">— выберите —</option>
                                    @foreach ($accounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->organization?->name }} — {{ $acc->summaryLabel() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button
                                type="submit"
                                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                                @disabled($payableCount === 0)
                            >
                                Выдать зарплату ({{ $payableCount }})
                            </button>
                        </form>
                        @if ($payableCount === 0)
                            <p class="mt-2 text-xs text-slate-500">Нет строк с положительной суммой к выплате или всем уже оформлена выплата за этот период.</p>
                        @endif
                    </div>
                @endif
            @endif
        </div>

        <div class="flex flex-wrap gap-3">
            <a
                href="{{ route('admin.payroll.advances.index') }}"
                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
            >
                Авансы
            </a>
            <a
                href="{{ route('admin.payroll.penalties.index') }}"
                class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
            >
                Штрафы
            </a>
            <a
                href="{{ route('admin.settings.employees') }}"
                class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50"
            >
                Сотрудники и оклады
            </a>
        </div>
    </div>
</x-admin-layout>
