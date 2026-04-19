@php
    $fmt = static fn ($v) => number_format((float) $v, 2, ',', ' ');
@endphp
<x-admin-layout pageTitle="Должники (физ. лица)" main-class="bg-slate-100/80 px-3 py-5 sm:px-4 lg:px-6">
    <div
        class="mx-auto max-w-6xl space-y-5"
        x-data="retailDebtsPage(@js(['defaultAccountId' => $defaultAccountId, 'limit' => $limit, 'payUrls' => $payUrls, 'groupPayUrl' => $groupPayUrl]))"
        @keydown.escape.window="if (groupModalOpen) { closeGroupModal() } else if (modalOpen) { closeModal() }"
    >
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 shadow-sm">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->has('pay'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                {{ $errors->first('pay') }}
            </div>
        @endif

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ route('admin.retail-sales.index') }}" class="text-sm font-semibold text-emerald-800 hover:underline">← К продаже</a>
                <h1 class="mt-2 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Должники (физ. лица)</h1>
            </div>
            <form method="GET" action="{{ route('admin.retail-sales.debts') }}" class="flex items-center gap-2">
                <label for="deb_limit" class="text-sm text-slate-600">Показать</label>
                <select id="deb_limit" name="limit" class="rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-sm" onchange="this.form.submit()">
                    @foreach ([50, 100, 200, 500] as $opt)
                        <option value="{{ $opt }}" @selected($limit === $opt)>{{ $opt }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        @if ($paymentAccountsPayload === [])
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-950">
                Нет счетов и касс. Добавьте организацию и счета — без них нельзя принять оплату долга.
                <a href="{{ route('admin.organizations.index') }}" class="ml-2 font-semibold text-emerald-800 underline">Организации</a>
            </div>
        @endif

        <div class="space-y-6">
            @if ($debtGroups->isEmpty())
                <div class="rounded-2xl border border-slate-200/90 bg-white px-5 py-12 text-center text-sm text-slate-600 shadow-sm ring-1 ring-slate-900/5">
                    Нет записей с непогашенным долгом.
                </div>
            @else
                @foreach ($debtGroups as $groupSales)
                    @php
                        /** @var \Illuminate\Support\Collection<int, \App\Models\RetailSale> $groupSales */
                        $first = $groupSales->first();
                        $groupDebtSumStr = $groupSales->reduce(
                            fn ($carry, $s) => bcadd($carry, (string) $s->debt_amount, 2),
                            '0'
                        );
                    @endphp
                    <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/5">
                        <div class="border-b border-slate-100 bg-slate-50/90 px-4 py-3 sm:px-5">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-base font-bold text-slate-900">{{ $first->debtor_name ?: '—' }}</p>
                                    <p class="text-sm text-slate-600">
                                        Тел.: <span class="font-mono tabular-nums">{{ $first->debtor_phone ?: '—' }}</span>
                                        @if ($groupSales->count() > 1)
                                            <span class="text-slate-400">·</span>
                                            <span class="text-slate-500">{{ $groupSales->count() }} чека</span>
                                        @endif
                                    </p>
                                </div>
                                <div class="flex flex-col items-end gap-2 sm:flex-row sm:items-center sm:gap-3">
                                    <p class="text-sm font-semibold text-rose-800">
                                        Долг по клиенту: <span class="tabular-nums">{{ $fmt($groupDebtSumStr) }}</span> сом
                                    </p>
                                    @if ($groupSales->count() > 1 && $paymentAccountsPayload !== [])
                                        <button
                                            type="button"
                                            class="inline-flex shrink-0 items-center justify-center rounded-xl border border-emerald-600 bg-white px-4 py-2 text-sm font-bold text-emerald-700 shadow-sm transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                                            @click="openGroupPay(@js($groupSales->pluck('id')->values()->all()), @js($groupDebtSumStr))"
                                        >
                                            Единым платежом
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="overflow-x-auto p-3 sm:p-4">
                            <table class="min-w-full border-collapse border border-slate-200 text-sm">
                                <thead>
                                    <tr class="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                                        <th class="border border-slate-200 px-2 py-2 sm:px-3">№</th>
                                        <th class="border border-slate-200 px-2 py-2 sm:px-3">Дата</th>
                                        <th class="border border-slate-200 px-2 py-2 sm:px-3">Склад</th>
                                        <th class="border border-slate-200 px-2 py-2 text-right sm:px-3">Сумма чека</th>
                                        <th class="border border-slate-200 px-2 py-2 text-right sm:px-3">Остаток долга</th>
                                        <th class="min-w-[10rem] border border-slate-200 px-2 py-2 sm:px-3">Комментарий</th>
                                        @if ($paymentAccountsPayload !== [])
                                            <th class="w-[9rem] border border-slate-200 px-2 py-2 text-center sm:px-3"></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($groupSales as $sale)
                                        <tr class="align-middle hover:bg-slate-50/90">
                                            <td class="border border-slate-200 px-2 py-2.5 font-mono text-slate-800 sm:px-3">{{ $sale->id }}</td>
                                            <td class="whitespace-nowrap border border-slate-200 px-2 py-2.5 sm:px-3">{{ $sale->document_date->format('d.m.Y') }}</td>
                                            <td class="border border-slate-200 px-2 py-2.5 text-slate-700 sm:px-3">{{ $sale->warehouse->name ?? '—' }}</td>
                                            <td class="border border-slate-200 px-2 py-2.5 text-right tabular-nums font-medium sm:px-3">{{ $fmt($sale->total_amount) }}</td>
                                            <td class="border border-slate-200 px-2 py-2.5 text-right tabular-nums font-bold text-rose-800 sm:px-3">{{ $fmt($sale->debt_amount) }}</td>
                                            <td class="max-w-[14rem] border border-slate-200 px-2 py-2.5 text-slate-600 sm:px-3">
                                                {{ $sale->debtor_comment ? \Illuminate\Support\Str::limit($sale->debtor_comment, 160) : '—' }}
                                            </td>
                                            @if ($paymentAccountsPayload !== [])
                                                <td class="border border-slate-200 px-2 py-2 text-center sm:px-3">
                                                    <button
                                                        type="button"
                                                        class="inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                                                        @click="openPay({{ $sale->id }}, @js((string) $sale->debt_amount))"
                                                    >
                                                        Оплатить
                                                    </button>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        {{-- Модальное окно оплаты --}}
        <div
            x-show="modalOpen"
            x-cloak
            class="fixed inset-0 z-[200] flex items-center justify-center p-4"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            role="dialog"
            aria-modal="true"
            aria-labelledby="retail-debt-modal-title"
        >
            <div class="absolute inset-0 bg-slate-900/55 backdrop-blur-[1px]" @click="closeModal()" aria-hidden="true"></div>
            <div
                class="relative z-10 w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl ring-1 ring-slate-900/10"
                @click.stop
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
            >
                <div class="border-b border-slate-100 bg-gradient-to-r from-emerald-50 to-teal-50/80 px-5 py-4">
                    <h2 id="retail-debt-modal-title" class="text-lg font-bold text-slate-900">Оплата долга</h2>
                    <p class="mt-1 text-sm text-slate-600">Чек № <span class="font-mono font-semibold text-slate-800" x-text="saleIdLabel"></span></p>
                </div>
                <form method="POST" class="space-y-4 px-5 py-5" :action="formAction">
                    @csrf
                    <input type="hidden" name="limit" :value="limit" />
                    <div>
                        <label for="debt_pay_account" class="mb-1 block text-xs font-medium text-slate-600">Счёт / касса *</label>
                        <select
                            id="debt_pay_account"
                            name="organization_bank_account_id"
                            x-model="accountId"
                            required
                            class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-3 pr-8 text-sm font-semibold text-slate-900"
                        >
                            @foreach ($paymentAccountsPayload as $acc)
                                <option value="{{ $acc['id'] }}">{{ $acc['label'] }} — {{ $acc['organization'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="debt_pay_amount" class="mb-1 block text-xs font-medium text-slate-600">Сумма, сом *</label>
                        <input
                            id="debt_pay_amount"
                            type="text"
                            name="amount"
                            x-model="amount"
                            inputmode="decimal"
                            autocomplete="off"
                            required
                            @focus="$event.target.select()"
                            @mouseup="$event.preventDefault()"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-right text-base font-bold tabular-nums text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/25"
                            placeholder="0,00"
                        />
                        <p class="mt-1 text-xs text-slate-500">По умолчанию — полный остаток; можно указать меньше для частичной оплаты.</p>
                    </div>
                    <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end sm:gap-3">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeModal()"
                        >
                            Отмена
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                        >
                            Провести оплату
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Модальное окно: единый платёж по нескольким чекам --}}
        <div
            x-show="groupModalOpen"
            x-cloak
            class="fixed inset-0 z-[200] flex items-center justify-center p-4"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            role="dialog"
            aria-modal="true"
            aria-labelledby="retail-debt-group-modal-title"
        >
            <div class="absolute inset-0 bg-slate-900/55 backdrop-blur-[1px]" @click="closeGroupModal()" aria-hidden="true"></div>
            <div
                class="relative z-10 w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl ring-1 ring-slate-900/10"
                @click.stop
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
            >
                <div class="border-b border-slate-100 bg-gradient-to-r from-teal-50 to-emerald-50/90 px-5 py-4">
                    <h2 id="retail-debt-group-modal-title" class="text-lg font-bold text-slate-900">Единый платёж</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Укажите сумму (например, 500 при общем долге 650). Она будет зачислена по чекам по очереди: сначала более ранние.
                    </p>
                </div>
                <form method="POST" action="{{ route('admin.retail-sales.pay-debt-group') }}" class="space-y-4 px-5 py-5">
                    @csrf
                    <input type="hidden" name="limit" :value="limit" />
                    <template x-for="sid in groupSaleIds" :key="sid">
                        <input type="hidden" name="sale_ids[]" :value="sid" />
                    </template>
                    <div>
                        <label for="debt_group_pay_account" class="mb-1 block text-xs font-medium text-slate-600">Счёт / касса *</label>
                        <select
                            id="debt_group_pay_account"
                            name="organization_bank_account_id"
                            x-model="groupAccountId"
                            required
                            class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-3 pr-8 text-sm font-semibold text-slate-900"
                        >
                            @foreach ($paymentAccountsPayload as $acc)
                                <option value="{{ $acc['id'] }}">{{ $acc['label'] }} — {{ $acc['organization'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="debt_group_pay_amount" class="mb-1 block text-xs font-medium text-slate-600">Сумма, сом *</label>
                        <input
                            id="debt_group_pay_amount"
                            type="text"
                            name="amount"
                            x-model="groupAmount"
                            inputmode="decimal"
                            autocomplete="off"
                            required
                            @focus="$event.target.select()"
                            @mouseup="$event.preventDefault()"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-right text-base font-bold tabular-nums text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/25"
                            placeholder="0,00"
                        />
                        <p class="mt-1 text-xs text-slate-500">Не больше суммарного долга по клиенту; можно меньше — остаток останется по чекам.</p>
                    </div>
                    <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end sm:gap-3">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeGroupModal()"
                        >
                            Отмена
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                        >
                            Провести оплату
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
