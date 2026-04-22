@php
    $isEdit = isset($movement) && $movement !== null;
@endphp
<x-admin-layout pageTitle="{{ $isEdit ? 'Редактирование: прочий приход' : 'Новая операция: прочий приход' }}" main-class="bg-slate-100/80 px-3 py-5 sm:px-6 lg:px-8">
    @include('admin.bank.partials.1c-form-document-styles')
    @include('admin.bank.partials.1c-form-modern-inputs')

    <style>
        .page-income-other .bank-1c-doc {
            border-radius: 0.75rem;
            border-color: rgb(148 163 184 / 0.45);
            box-shadow: 0 10px 40px -12px rgb(5 150 105 / 0.15);
        }
        .page-income-other .bank-1c-titlebar {
            border-top: 3px solid #059669;
            border-bottom-color: rgb(148 163 184 / 0.35);
            background: linear-gradient(180deg, #ecfdf5 0%, #f0fdfa 55%, #f8fafc 100%);
        }
        .page-income-other .bank-1c-titlebar h2 {
            font-size: 14px;
            letter-spacing: -0.01em;
            color: #0f172a;
        }
        .page-income-other .bank-1c-toolbar {
            background: linear-gradient(180deg, #d1fae5 0%, #ccfbf1 100%);
            border-bottom-color: rgb(52 211 153 / 0.55);
        }
        .page-income-other .bank-1c-header {
            background: linear-gradient(180deg, #fafafe 0%, #f8fafc 100%);
            border-bottom-color: rgb(203 213 225 / 0.6);
        }
        .page-income-other .bank-1c-foot {
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-top-color: rgb(203 213 225 / 0.65);
            padding: 12px 16px;
        }
        .page-income-other .bank-1c-foot a.bank-foot-link-plain {
            font-size: 14px;
            font-weight: 600;
            color: #047857;
            text-decoration: underline;
            text-decoration-color: rgb(16 185 129 / 0.45);
            text-underline-offset: 2px;
        }
        .page-income-other .bank-1c-foot a.bank-foot-link-plain:hover {
            color: #064e3b;
            text-decoration-color: #059669;
        }
    </style>

    <div class="mx-auto w-full max-w-4xl space-y-4">
        <a
            href="{{ route('admin.bank.income-other') }}"
            class="inline-flex items-center text-sm font-semibold text-emerald-800 underline decoration-emerald-400/50 underline-offset-2 transition hover:text-emerald-950 hover:decoration-emerald-600"
        >← К списку операций</a>

        @include('admin.partials.status-flash')
        @include('admin.bank.partials.no-accounts')

        @if ($accounts->isNotEmpty())
            <div
                class="rounded-[1.75rem] bg-gradient-to-br from-emerald-100/70 via-white to-cyan-50/60 p-[3px] shadow-[0_22px_50px_-18px_rgba(5,150,105,0.35)] ring-1 ring-emerald-200/55"
            >
                <div class="rounded-[1.6rem] bg-gradient-to-b from-white via-white to-slate-50/90 px-4 py-5 sm:px-6 sm:py-7">
                    <div class="mb-5 flex flex-col gap-3 border-b border-slate-200/80 pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-emerald-800/90">Банк и касса</p>
                            <p class="mt-1 max-w-xl text-sm leading-relaxed text-slate-600">
                                @if ($isEdit)
                                    Изменение записи №{{ $movement->id }}: дата, счёт, сумма, категория и комментарий.
                                @else
                                    Поступление без привязки к покупателю: укажите счёт, сумму и при необходимости назначение.
                                @endif
                            </p>
                        </div>
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-700 text-white shadow-lg shadow-emerald-900/25 ring-2 ring-white/50"
                            aria-hidden="true"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>

                    <div class="page-income-other bank-1c-page-modern bank-1c-scope w-full min-w-0">
                        <div class="bank-1c-doc w-full">
                            <div class="bank-1c-titlebar">
                                <h2>Приход денежных средств — прочие @if ($isEdit)<span class="font-normal text-slate-600">(№{{ $movement->id }})</span>@endif</h2>
                            </div>

                            <form
                                method="POST"
                                action="{{ $isEdit ? route('admin.bank.income-other.update', $movement->id) : route('admin.bank.income-other.store') }}"
                                class="contents"
                            >
                                @csrf
                                @if ($isEdit)
                                    @method('PUT')
                                @endif

                                <div class="bank-1c-toolbar">
                                    <button type="submit" class="bank-1c-tb-btn bank-1c-tb-btn-primary">{{ $isEdit ? 'Сохранить изменения' : 'Записать и закрыть' }}</button>
                                </div>

                                <div class="bank-1c-header bank-1c-header--bank-doc-wide">
                                    <div>
                                        <span class="bank-1c-field-label">Дата операции *</span>
                                        <input
                                            type="date"
                                            name="occurred_on"
                                            required
                                            value="{{ old('occurred_on', $isEdit ? $movement->occurred_on->format('Y-m-d') : now()->format('Y-m-d')) }}"
                                        />
                                        <x-input-error class="mt-1" :messages="$errors->get('occurred_on')" />
                                    </div>
                                    <div>
                                        <span class="bank-1c-field-label">Счёт / касса зачисления *</span>
                                        <select name="our_account_id" required>
                                            <option value="">— выберите —</option>
                                            @foreach ($accounts as $acc)
                                                <option value="{{ $acc->id }}" @selected((string) old('our_account_id', $isEdit ? (string) $movement->our_account_id : '') === (string) $acc->id)>
                                                    {{ $acc->summaryLabel() }} ({{ $acc->currency }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <x-input-error class="mt-1" :messages="$errors->get('our_account_id')" />
                                    </div>
                                    <div class="bank-1c-amount-below-pair">
                                        <span class="bank-1c-field-label">Сумма *</span>
                                        <input
                                            type="text"
                                            name="amount"
                                            required
                                            inputmode="decimal"
                                            placeholder="0,00"
                                            value="{{ old('amount', $isEdit ? number_format((float) $movement->amount, 2, ',', ' ') : '') }}"
                                        />
                                        <x-input-error class="mt-1" :messages="$errors->get('amount')" />
                                    </div>
                                    <div class="bank-1c-span-full">
                                        <span class="bank-1c-field-label">Категория / назначение</span>
                                        <input
                                            type="text"
                                            name="expense_category"
                                            value="{{ old('expense_category', $isEdit ? (string) ($movement->expense_category ?? '') : '') }}"
                                            placeholder="Напр.: возврат депозита, субсидия"
                                        />
                                        <x-input-error class="mt-1" :messages="$errors->get('expense_category')" />
                                    </div>
                                    <div class="bank-1c-span-full">
                                        <span class="bank-1c-field-label">Комментарий</span>
                                        <textarea name="comment" rows="4">{{ old('comment', $isEdit ? (string) $movement->comment : '') }}</textarea>
                                        <x-input-error class="mt-1" :messages="$errors->get('comment')" />
                                    </div>
                                </div>

                                <div class="bank-1c-foot flex flex-wrap items-center justify-between gap-3">
                                    <a href="{{ route('admin.organizations.index') }}" class="bank-foot-link-plain">Данные организации — счета</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-admin-layout>
