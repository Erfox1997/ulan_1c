@php
    $isEdit = isset($movement) && $movement !== null;
@endphp
<x-admin-layout pageTitle="{{ $isEdit ? 'Редактирование: перевод между счетами' : 'Новый перевод между счетами' }}" main-class="bg-slate-100/80 px-3 py-5 sm:px-6 lg:px-8">
    @include('admin.bank.partials.1c-form-document-styles')
    @include('admin.bank.partials.1c-form-modern-inputs')

    <style>
        .page-transfers .bank-1c-doc {
            border-radius: 0.75rem;
            border-color: rgb(148 163 184 / 0.45);
            box-shadow: 0 10px 40px -12px rgb(8 145 178 / 0.14);
        }
        .page-transfers .bank-1c-titlebar {
            border-top: 3px solid #0891b2;
            border-bottom-color: rgb(148 163 184 / 0.35);
            background: linear-gradient(180deg, #ecfeff 0%, #f0fdfa 55%, #f8fafc 100%);
        }
        .page-transfers .bank-1c-titlebar h2 {
            font-size: 14px;
            letter-spacing: -0.01em;
            color: #0f172a;
        }
        .page-transfers .bank-1c-toolbar {
            background: linear-gradient(180deg, #cffafe 0%, #ccfbf1 100%);
            border-bottom-color: rgb(103 232 249 / 0.55);
        }
        .page-transfers .bank-1c-header {
            background: linear-gradient(180deg, #fafeff 0%, #f8fafc 100%);
            border-bottom-color: rgb(203 213 225 / 0.6);
        }
        .page-transfers .bank-1c-foot {
            background: linear-gradient(180deg, #f8fafc 0%, #ecfeff 100%);
            border-top-color: rgb(203 213 225 / 0.65);
            padding: 12px 16px;
        }
        .page-transfers .bank-1c-foot a.bank-foot-link-plain {
            font-size: 14px;
            font-weight: 600;
            color: #0e7490;
            text-decoration: underline;
            text-decoration-color: rgb(6 182 212 / 0.45);
            text-underline-offset: 2px;
        }
        .page-transfers .bank-1c-foot a.bank-foot-link-plain:hover {
            color: #164e63;
            text-decoration-color: #0891b2;
        }
    </style>

    <div class="mx-auto w-full max-w-4xl space-y-4">
        <a
            href="{{ route('admin.bank.transfers') }}"
            class="inline-flex items-center text-sm font-semibold text-cyan-800 underline decoration-cyan-400/50 underline-offset-2 transition hover:text-cyan-950 hover:decoration-cyan-600"
        >← К списку операций</a>

        @include('admin.partials.status-flash')
        @include('admin.bank.partials.no-accounts')

        @if ($isEdit && $accounts->count() < 2)
            <div class="rounded-xl border border-amber-200/80 bg-amber-50 px-4 py-4 text-sm text-amber-950">
                <p class="font-medium">Недостаточно счетов для редактирования перевода №{{ $movement->id }}.</p>
                <p class="mt-2 text-amber-900/90">
                    Добавьте счета в
                    <a href="{{ route('admin.organizations.index') }}" class="font-semibold text-emerald-800 underline hover:text-emerald-700">данных организации</a>
                    или вернитесь к
                    <a href="{{ route('admin.bank.transfers') }}" class="font-semibold underline">списку переводов</a>.
                </p>
            </div>
        @elseif ($accounts->count() >= 2)
            <div
                class="rounded-[1.75rem] bg-gradient-to-br from-cyan-100/70 via-white to-emerald-50/55 p-[3px] shadow-[0_22px_50px_-18px_rgba(8,145,178,0.32)] ring-1 ring-cyan-200/60"
            >
                <div class="rounded-[1.6rem] bg-gradient-to-b from-white via-white to-slate-50/90 px-4 py-5 sm:px-6 sm:py-7">
                    <div class="mb-5 flex flex-col gap-3 border-b border-slate-200/80 pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-cyan-800/90">Банк и касса</p>
                            <p class="mt-1 max-w-xl text-sm leading-relaxed text-slate-600">
                                @if ($isEdit)
                                    Изменение записи №{{ $movement->id }}: дата, счета списания и зачисления, сумма и комментарий.
                                @else
                                    Перевод между своими счетами: выберите счёт списания, счёт зачисления и сумму.
                                @endif
                            </p>
                        </div>
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-600 to-emerald-600 text-white shadow-lg shadow-cyan-900/20 ring-2 ring-white/50"
                            aria-hidden="true"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                        </div>
                    </div>

                    <div class="page-transfers bank-1c-page-modern bank-1c-scope w-full min-w-0">
                        <div class="bank-1c-doc w-full">
                            <div class="bank-1c-titlebar">
                                <h2>Перевод между счетами @if ($isEdit)<span class="font-normal text-slate-600">(№{{ $movement->id }})</span>@endif</h2>
                            </div>

                            <form
                                method="POST"
                                action="{{ $isEdit ? route('admin.bank.transfers.update', $movement->id) : route('admin.bank.transfers.store') }}"
                                class="contents"
                            >
                                @csrf
                                @if ($isEdit)
                                    @method('PUT')
                                @endif

                                <div class="bank-1c-toolbar">
                                    <button type="submit" class="bank-1c-tb-btn bank-1c-tb-btn-primary">{{ $isEdit ? 'Сохранить изменения' : 'Записать и закрыть' }}</button>
                                </div>

                                <div class="bank-1c-header bank-1c-header--transfer-wide">
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
                                        <span class="bank-1c-field-label">Списать со счёта *</span>
                                        <select name="from_account_id" required>
                                            <option value="">— выберите —</option>
                                            @foreach ($accounts as $acc)
                                                <option value="{{ $acc->id }}" @selected((string) old('from_account_id', $isEdit ? (string) $movement->from_account_id : '') === (string) $acc->id)>
                                                    {{ $acc->summaryLabel() }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <x-input-error class="mt-1" :messages="$errors->get('from_account_id')" />
                                    </div>
                                    <div>
                                        <span class="bank-1c-field-label">Зачислить на счёт *</span>
                                        <select name="to_account_id" required>
                                            <option value="">— выберите —</option>
                                            @foreach ($accounts as $acc)
                                                <option value="{{ $acc->id }}" @selected((string) old('to_account_id', $isEdit ? (string) $movement->to_account_id : '') === (string) $acc->id)>
                                                    {{ $acc->summaryLabel() }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <x-input-error class="mt-1" :messages="$errors->get('to_account_id')" />
                                    </div>
                                    <div>
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
                                    <div class="bank-1c-span-2">
                                        <span class="bank-1c-field-label">Комментарий</span>
                                        <textarea name="comment" rows="4">{{ old('comment', $isEdit ? (string) $movement->comment : '') }}</textarea>
                                        <x-input-error class="mt-1" :messages="$errors->get('comment')" />
                                    </div>
                                </div>

                                <div class="bank-1c-foot">
                                    <a href="{{ route('admin.organizations.index') }}" class="bank-foot-link-plain">Данные организации — счета</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @elseif ($accounts->isNotEmpty())
            <div
                class="rounded-[1.75rem] bg-gradient-to-br from-cyan-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-cyan-200/50"
            >
                <div class="rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90 px-3 py-4 sm:px-5 sm:py-6">
                    <div class="bank-1c-scope w-full min-w-0">
                        <div class="bank-1c-doc w-full">
                            <div class="bank-1c-banner-warn">
                                Для перевода нужно минимум два счёта в организации. Добавьте ещё один счёт в «Данные организации».
                            </div>
                            <div class="bank-1c-foot">
                                <a
                                    href="{{ route('admin.organizations.index') }}"
                                    class="text-sm font-semibold text-cyan-800 underline decoration-cyan-400/40 underline-offset-2 hover:text-cyan-950"
                                >Открыть данные организации</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-admin-layout>
