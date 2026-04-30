@php
    $isEdit = isset($movement) && $movement !== null;
    $bankCashExpenseCategoryFieldInit = [
        'searchUrl' => route('admin.bank.expense-other.categories-search'),
        'initialValue' => old('expense_category', $isEdit ? (string) ($movement->expense_category ?? '') : ''),
    ];
@endphp
<x-admin-layout pageTitle="{{ $isEdit ? 'Редактирование: прочий расход' : 'Новая операция: прочий расход' }}" main-class="bg-slate-100/80 px-3 py-5 sm:px-6 lg:px-8">
    @include('admin.bank.partials.1c-form-document-styles')
    @include('admin.bank.partials.1c-form-modern-inputs')

    <style>
        .page-expense-other .bank-1c-doc {
            border-radius: 0.75rem;
            border-color: rgb(148 163 184 / 0.45);
            box-shadow: 0 10px 40px -12px rgb(79 70 229 / 0.12);
        }
        .page-expense-other .bank-1c-titlebar {
            border-top: 3px solid #4f46e5;
            border-bottom-color: rgb(148 163 184 / 0.35);
            background: linear-gradient(180deg, #eef2ff 0%, #f5f3ff 55%, #f8fafc 100%);
        }
        .page-expense-other .bank-1c-titlebar h2 {
            font-size: 14px;
            letter-spacing: -0.01em;
            color: #0f172a;
        }
        .page-expense-other .bank-1c-toolbar {
            background: linear-gradient(180deg, #e0e7ff 0%, #ede9fe 100%);
            border-bottom-color: rgb(165 180 252 / 0.55);
        }
        .page-expense-other .bank-1c-header {
            background: linear-gradient(180deg, #fafafe 0%, #f8fafc 100%);
            border-bottom-color: rgb(203 213 225 / 0.6);
        }
        .page-expense-other .bank-1c-foot {
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-top-color: rgb(203 213 225 / 0.65);
            padding: 12px 16px;
        }
        .page-expense-other .bank-1c-foot a.bank-foot-link-plain {
            font-size: 14px;
            font-weight: 600;
            color: #3730a3;
            text-decoration: underline;
            text-decoration-color: rgb(129 140 248 / 0.45);
            text-underline-offset: 2px;
        }
        .page-expense-other .bank-1c-foot a.bank-foot-link-plain:hover {
            color: #1e1b4b;
            text-decoration-color: #4f46e5;
        }
        .page-expense-other .expense-other-cat-wrap .expense-other-cat-dd {
            border-radius: 0.5rem;
            border: 1px solid rgb(203 213 225);
            background: #fff;
            box-shadow:
                0 4px 6px -1px rgb(0 0 0 / 0.07),
                0 10px 24px -8px rgb(79 70 229 / 0.16);
            overflow: hidden;
            max-height: min(17rem, 65vh);
            overflow-y: auto;
        }
        .page-expense-other .expense-other-cat-wrap .expense-other-cat-dd button.cat-suggest-row {
            display: block;
            width: 100%;
            text-align: left;
            padding: 0.55rem 0.75rem;
            border: 0;
            border-bottom: 1px solid rgb(241 245 249);
            font-size: 13px;
            color: rgb(15 23 42);
            background: #fff;
            cursor: pointer;
        }
        .page-expense-other .expense-other-cat-wrap .expense-other-cat-dd button.cat-suggest-row:last-of-type {
            border-bottom: 0;
        }
        .page-expense-other .expense-other-cat-wrap .expense-other-cat-dd button.cat-suggest-row:hover,
        .page-expense-other .expense-other-cat-wrap .expense-other-cat-dd button.cat-suggest-row:focus {
            background: rgb(238 242 255);
            outline: none;
        }
        .page-expense-other .expense-other-cat-wrap .expense-other-cat-dd .bank-cp-dd-status {
            margin: 0;
            padding: 0.5rem 0.75rem;
            font-size: 12px;
            color: rgb(71 85 105);
            background: rgb(248 250 252);
            border-bottom: 1px solid rgb(241 245 249);
        }
        .page-expense-other .expense-other-cat-wrap .bank-cp-dd-empty {
            padding: 0.75rem;
            background: rgb(248 250 252);
            border-top: 1px solid rgb(241 245 249);
        }
        .page-expense-other .expense-other-cat-wrap .bank-cp-dd-empty p {
            margin: 0 0 0.5rem;
            font-size: 12px;
            line-height: 1.4;
            color: rgb(71 85 105);
        }
        .page-expense-other .expense-other-cat-wrap .bank-cp-dd-add {
            display: inline-flex;
            width: 100%;
            align-items: center;
            justify-content: center;
            padding: 0.4rem 0.65rem;
            font-size: 12px;
            font-weight: 600;
            color: rgb(55 48 163);
            background: #fff;
            border: 1px solid rgb(196 181 253);
            border-radius: 0.375rem;
            cursor: pointer;
            transition:
                background 0.15s ease,
                border-color 0.15s ease;
        }
        .page-expense-other .expense-other-cat-wrap .bank-cp-dd-add:hover {
            background: rgb(238 242 255);
            border-color: rgb(139 92 246);
        }
    </style>

    <div class="mx-auto w-full max-w-4xl space-y-4">
        <a
            href="{{ route('admin.bank.expense-other') }}"
            class="inline-flex items-center text-sm font-semibold text-indigo-800 underline decoration-indigo-400/50 underline-offset-2 transition hover:text-indigo-950 hover:decoration-indigo-600"
        >← К списку операций</a>

        @include('admin.partials.status-flash')
        @include('admin.bank.partials.no-accounts')

        @if ($accounts->isNotEmpty())
            <script>
                window.__bankCashExpenseCategoryFieldInit = @json($bankCashExpenseCategoryFieldInit);
            </script>
            <div
                class="rounded-[1.75rem] bg-gradient-to-br from-indigo-100/70 via-white to-violet-50/60 p-[3px] shadow-[0_22px_50px_-18px_rgba(79,70,229,0.35)] ring-1 ring-indigo-200/55"
            >
                <div class="rounded-[1.6rem] bg-gradient-to-b from-white via-white to-slate-50/90 px-4 py-5 sm:px-6 sm:py-7">
                    <div class="mb-5 flex flex-col gap-3 border-b border-slate-200/80 pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-indigo-800/90">Банк и касса</p>
                            <p class="mt-1 max-w-xl text-sm leading-relaxed text-slate-600">
                                @if ($isEdit)
                                    Изменение записи №{{ $movement->id }}: дата, счёт, сумма, категория и комментарий.
                                @else
                                    Списание без контрагента: укажите счёт, сумму и при необходимости категорию расхода.
                                @endif
                            </p>
                        </div>
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-700 text-white shadow-lg shadow-indigo-900/25 ring-2 ring-white/50"
                            aria-hidden="true"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                            </svg>
                        </div>
                    </div>

                    <div class="page-expense-other bank-1c-page-modern bank-1c-scope w-full min-w-0">
                        <div class="bank-1c-doc w-full">
                            <div class="bank-1c-titlebar">
                                <h2>Расход денежных средств — прочие @if ($isEdit)<span class="font-normal text-slate-600">(№{{ $movement->id }})</span>@endif</h2>
                            </div>

                            <form
                                method="POST"
                                action="{{ $isEdit ? route('admin.bank.expense-other.update', $movement->id) : route('admin.bank.expense-other.store') }}"
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
                                        <span class="bank-1c-field-label">Счёт / касса списания *</span>
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
                                        <div
                                            class="relative expense-other-cat-wrap"
                                            x-data="bankCashExpenseCategoryField()"
                                            x-ref="catSuggestRoot"
                                            @keydown.escape="onCatEscape()"
                                        >
                                            <span class="bank-1c-field-label">Категория / назначение</span>
                                            <input
                                                type="text"
                                                name="expense_category"
                                                maxlength="255"
                                                autocomplete="off"
                                                placeholder="От 2 букв — подсказки из уже сохранённых категорий по филиалу"
                                                class="block w-full"
                                                x-model="query"
                                                @input="onCatInput($event)"
                                                @focus="onCatFocus($event)"
                                                @blur="onCatBlur()"
                                            />
                                            <div
                                                x-cloak
                                                x-show="showCatDropdown()"
                                                class="expense-other-cat-dd fixed z-[210]"
                                                role="listbox"
                                                aria-label="Категории расходов"
                                                @mousedown.prevent
                                                x-bind:style="'top:' + catPos.top + 'px;left:' + catPos.left + 'px;width:' + catPos.width + 'px'"
                                            >
                                                <div x-show="catLoading" class="bank-cp-dd-status rounded-t-lg">Поиск…</div>
                                                <template x-for="(label, idx) in catItems" :key="'eoc-' + idx + '-' + label">
                                                    <button
                                                        type="button"
                                                        class="cat-suggest-row"
                                                        @mousedown.prevent
                                                        @click="pickCategory(label)"
                                                        role="option"
                                                        x-text="label"
                                                    ></button>
                                                </template>
                                                <div
                                                    x-show="!catLoading && catNoHits && catItems.length === 0"
                                                    class="bank-cp-dd-empty rounded-b-lg"
                                                    x-cloak
                                                >
                                                    <p>В списке операций этой категории ещё не было.</p>
                                                    <button
                                                        type="button"
                                                        class="bank-cp-dd-add"
                                                        @mousedown.prevent
                                                        @click="confirmTypedCategory($event)"
                                                    >
                                                        Добавить категорию «<span x-text="query"></span>»
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
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
