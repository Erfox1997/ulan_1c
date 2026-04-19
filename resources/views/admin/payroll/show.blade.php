@php
    $backQuery = ['period_from' => $periodFrom, 'period_to' => $periodTo];
    $slipUrl = $canPrintSlip ?? false
        ? route('admin.payroll.pay-slip', $employee).'?'.http_build_query(['period_from' => $periodFrom, 'period_to' => $periodTo])
        : '#';
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-4xl space-y-5">
        @include('admin.partials.status-flash')

        @if ($errors->has('shift'))
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">{{ $errors->first('shift') }}</div>
        @endif

        @if ($errors->has('payroll'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                {{ $errors->first('payroll') }}
            </div>
        @endif

        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <a
                    href="{{ route('admin.payroll', $backQuery) }}"
                    class="text-sm font-semibold text-emerald-800 hover:underline"
                >← К списку сотрудников</a>
                <h1 class="mt-2 text-xl font-bold tracking-tight text-slate-900">{{ $employee->full_name }}</h1>
                <p class="mt-0.5 text-sm text-slate-600">{{ $employee->jobTypeLabel() }}</p>
            </div>
            <a
                href="{{ route('admin.settings.employees.edit', $employee) }}"
                class="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
            >Редактировать карточку</a>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]">
            <div
                class="border-b border-emerald-900/10 px-4 py-3 text-white sm:px-5"
                style="background: linear-gradient(120deg, #047857 0%, #0d9488 50%, #0f766e 100%);"
            >
                <h2 class="text-sm font-bold tracking-tight">Период расчёта</h2>
                <p class="mt-0.5 text-[11px] font-medium text-emerald-100/90">Даты оборота и начислений в таблице ниже.</p>
            </div>
            <form method="GET" action="{{ route('admin.payroll.show', $employee) }}" class="flex flex-wrap items-end gap-4 px-4 py-4 sm:px-6">
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
        </div>

        @if ($periodInvalid ?? false)
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                Дата «с» не может быть позже даты «по».
            </div>
        @elseif ($cardCalculationRow !== null)
            {{-- Выплата и печать --}}
            <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]">
                <div class="border-b border-slate-100 px-5 py-3">
                    <h2 class="text-sm font-bold text-slate-900">Выплата и документ</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Оформление через кассу (нужна открытая кассовая смена). Расписка — для подписи работника.</p>
                </div>
                <div class="space-y-4 px-5 py-4">
                    @if ($payoutRecord)
                        <div class="flex flex-col gap-3 rounded-xl border border-emerald-200 bg-emerald-50/80 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-emerald-950">
                                <span class="font-semibold">Выплата в кассе оформлена:</span>
                                {{ number_format((float) $payoutRecord->amount, 2, ',', ' ') }} сом.
                            </p>
                            @if ($canPrintSlip)
                                <a
                                    href="{{ $slipUrl }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex shrink-0 items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                                >Печать расписки</a>
                            @endif
                        </div>
                    @else
                        @if ($canIssuePayout && $accounts->isNotEmpty())
                            <form method="POST" action="{{ route('admin.payroll.payout-employee', $employee) }}" class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                                @csrf
                                <input type="hidden" name="period_from" value="{{ $periodFrom }}" />
                                <input type="hidden" name="period_to" value="{{ $periodTo }}" />
                                <div class="min-w-[min(100%,14rem)] flex-1">
                                    <label class="mb-1 block text-xs font-semibold text-slate-700">Счёт / касса</label>
                                    <select
                                        name="our_account_id"
                                        required
                                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                    >
                                        <option value="">— выберите —</option>
                                        @foreach ($accounts as $acc)
                                            <option value="{{ $acc->id }}">{{ $acc->organization?->name }} — {{ $acc->summaryLabel() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                                    Выдать зарплату
                                </button>
                            </form>
                        @elseif ($canIssuePayout && $accounts->isEmpty())
                            <p class="text-sm text-amber-900">Нет счетов/касс — добавьте их в «Данные организации», чтобы оформить выплату.</p>
                        @else
                            <p class="text-sm text-slate-600">Оформление выплаты недоступно: сумма к выплате не положительная.</p>
                        @endif

                        @if ($canPrintSlip)
                            <div class="flex flex-col gap-1 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:gap-4">
                                <a
                                    href="{{ $slipUrl }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex w-fit items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                                >Печать черновика (до выплаты в кассе)</a>
                                <span class="text-xs text-slate-500">Та же форма расписки по расчётной сумме.</span>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            @include('admin.payroll.partials.employee-card', [
                'employee' => $employee,
                'cr' => $cardCalculationRow,
                'cardRetailSales' => $cardRetailSales,
                'cardServiceLines' => $cardServiceLines,
                'periodFrom' => $periodFrom,
                'periodTo' => $periodTo,
            ])
        @endif
    </div>
</x-admin-layout>
