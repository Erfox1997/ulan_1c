@php
    $fmtMoney = static fn ($v) => $v === null || $v === '' ? '—' : number_format((float) $v, 2, ',', ' ');
@endphp
<div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]">
    <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3">
        <h2 class="text-xs font-bold uppercase tracking-wide text-slate-500">Расчёт за период</h2>
        <p class="mt-1 text-sm text-slate-700">{{ date('d.m.Y', strtotime($periodFrom)) }} — {{ date('d.m.Y', strtotime($periodTo)) }}</p>
    </div>

    <div class="grid gap-0 lg:grid-cols-2 lg:divide-x lg:divide-slate-100">
        <div class="border-b border-slate-100 p-5 lg:border-b-0">
            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Условия (карточка сотрудника)</h3>
            <dl class="mt-3 space-y-2 text-sm text-slate-800">
                <div class="flex justify-between gap-4 border-b border-dashed border-slate-200 pb-2">
                    <dt class="text-slate-600">Оклад</dt>
                    <dd class="tabular-nums font-medium">{{ $fmtMoney($employee->salary_fixed) }} сом</dd>
                </div>
                <div class="flex justify-between gap-4 border-b border-dashed border-slate-200 pb-2">
                    <dt class="text-slate-600">% с товаров</dt>
                    <dd class="tabular-nums font-medium">{{ $fmtMoney($employee->salary_percent_goods) }}%</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-600">% с услуг</dt>
                    <dd class="tabular-nums font-medium">{{ $fmtMoney($employee->salary_percent_services) }}%</dd>
                </div>
            </dl>
            @if ($employee->salary_contract_separate)
                <div class="mt-3 border-t border-dashed border-slate-200 pt-3">
                    <p class="text-xs font-semibold text-emerald-900/90">Отдельная зарплата по договору</p>
                </div>
            @endif
            @if ($employee->user_id === null)
                <p class="mt-4 rounded-lg border border-amber-200/90 bg-amber-50/90 px-3 py-2 text-xs text-amber-950">
                    Нет привязки к учётной записи — оборот розницы для % с товаров не считается.
                </p>
            @endif
        </div>

        <div class="bg-gradient-to-b from-emerald-50/90 to-white p-5">
            <h3 class="text-xs font-bold uppercase tracking-wide text-emerald-900/80">Итоги</h3>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3 text-slate-700">
                    <dt>Оборот розницы</dt>
                    <dd class="tabular-nums font-medium">{{ $fmtMoney($cr['goods_turnover']) }}</dd>
                </div>
                <div class="flex justify-between gap-3 text-slate-700">
                    <dt>Оборот по услугам</dt>
                    <dd class="tabular-nums font-medium">{{ $fmtMoney($cr['services_turnover']) }}</dd>
                </div>
                <div class="my-2 border-t border-emerald-200/80"></div>
                <div class="flex justify-between gap-3 text-slate-800">
                    <dt>Оклад</dt>
                    <dd class="tabular-nums">{{ $fmtMoney($cr['fixed']) }}</dd>
                </div>
                <div class="flex justify-between gap-3 text-slate-800">
                    <dt>Начислено % с товаров</dt>
                    <dd class="tabular-nums">{{ $fmtMoney($cr['goods_commission']) }}</dd>
                </div>
                <div class="flex justify-between gap-3 text-slate-800">
                    <dt>Начислено % с услуг</dt>
                    <dd class="tabular-nums">{{ $fmtMoney($cr['services_commission']) }}</dd>
                </div>
                @if (($cr['manual_contract'] ?? 0) > 0)
                    <div class="flex justify-between gap-3 text-slate-800">
                        <dt>По договору (за период)</dt>
                        <dd class="tabular-nums">{{ $fmtMoney($cr['manual_contract']) }}</dd>
                    </div>
                @endif
                <div class="flex justify-between gap-3 text-rose-800/95">
                    <dt>Авансы</dt>
                    <dd class="tabular-nums">− {{ $fmtMoney($cr['advances']) }}</dd>
                </div>
                <div class="flex justify-between gap-3 text-rose-800/95">
                    <dt>Штрафы</dt>
                    <dd class="tabular-nums">− {{ $fmtMoney($cr['penalties']) }}</dd>
                </div>
                <div class="my-2 border-t border-emerald-300/80"></div>
                <div class="flex justify-between gap-3 font-semibold text-slate-900">
                    <dt>Всего начислено</dt>
                    <dd class="tabular-nums">{{ $fmtMoney($cr['accrual']) }}</dd>
                </div>
                <div class="flex justify-between gap-3 pt-1 text-base font-bold text-emerald-900">
                    <dt>К выплате</dt>
                    <dd class="tabular-nums">{{ $fmtMoney($cr['net']) }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="border-t border-slate-100 px-5 py-5">
        <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Розница (чеки)</h3>
        @if ($cardRetailSales->isEmpty())
            <p class="mt-2 text-sm text-slate-500">Нет чеков за период или нет привязки к пользователю.</p>
        @else
            <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200/90">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] font-semibold uppercase text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Дата</th>
                            <th class="px-3 py-2">Чек</th>
                            <th class="px-3 py-2 text-right">Сумма</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($cardRetailSales as $rs)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-3 py-2 whitespace-nowrap text-slate-800">{{ $rs->document_date->format('d.m.Y') }}</td>
                                <td class="px-3 py-2">
                                    <a href="{{ route('admin.retail-sales.receipt', $rs) }}" class="font-mono text-emerald-800 font-medium hover:underline">{{ $rs->id }}</a>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $fmtMoney($rs->total_amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <h3 class="mt-8 text-xs font-bold uppercase tracking-wide text-slate-500">Услуги (исполнитель)</h3>
        @if ($cardServiceLines->isEmpty())
            <p class="mt-2 text-sm text-slate-500">Нет строк за период.</p>
        @else
            <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200/90">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] font-semibold uppercase text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Дата</th>
                            <th class="px-3 py-2">Заявка</th>
                            <th class="px-3 py-2">Наименование</th>
                            <th class="px-3 py-2 text-right">Сумма</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($cardServiceLines as $sl)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-3 py-2 whitespace-nowrap text-slate-800">{{ $sl->serviceOrder?->document_date?->format('d.m.Y') ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    <a href="{{ route('admin.service-sales.sell.lines', $sl->service_order_id) }}" class="font-mono text-emerald-800 font-medium hover:underline">{{ $sl->service_order_id }}</a>
                                </td>
                                <td class="px-3 py-2 text-slate-800">{{ $sl->name }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $fmtMoney($sl->line_sum) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
