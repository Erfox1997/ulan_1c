@php
    $isEdit = isset($movement) && $movement !== null;
    $defaultIncomeKind = old('income_kind', $isEdit && $movement->counterparty_id ? 'loan' : 'plain');
    $bankCashExpenseCategoryFieldInit = [
        'searchUrl' => route('admin.bank.income-other.categories-search'),
        'initialValue' => old('expense_category', $isEdit ? (string) ($movement->expense_category ?? '') : ''),
    ];
@endphp
<x-admin-layout pageTitle="{{ $isEdit ? 'Редактирование: прочий приход / займ' : 'Новая операция: прочий приход / займ' }}" main-class="bg-slate-100/80 px-3 py-5 sm:px-6 lg:px-8">
    @include('admin.partials.cp-brush')
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
        /* Выпадающий список контрагента: компактнее и в тон странице */
        .page-income-other .bank-1c-cp-wrap .bank-1c-dd {
            border-radius: 0.5rem;
            border: 1px solid rgb(203 213 225);
            background: #fff;
            box-shadow:
                0 4px 6px -1px rgb(0 0 0 / 0.07),
                0 10px 24px -8px rgb(5 150 105 / 0.18);
            overflow: hidden;
            max-height: min(18rem, 70vh);
        }
        .page-income-other .bank-1c-cp-wrap .bank-1c-dd .cp-row {
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid rgb(241 245 249);
            font-size: 13px;
        }
        .page-income-other .bank-1c-cp-wrap .bank-1c-dd .cp-row:last-of-type {
            border-bottom: 0;
        }
        .page-income-other .bank-1c-cp-wrap .bank-1c-dd .bank-cp-dd-status {
            margin: 0;
            padding: 0.5rem 0.75rem;
            font-size: 12px;
            line-height: 1.35;
            color: rgb(100 116 139);
            background: rgb(248 250 252);
            border-bottom: 1px solid rgb(241 245 249);
        }
        .page-income-other .bank-1c-cp-wrap .bank-1c-dd .bank-cp-dd-empty {
            padding: 0.75rem;
            background: rgb(248 250 252);
            border-top: 1px solid rgb(241 245 249);
        }
        .page-income-other .bank-1c-cp-wrap .bank-1c-dd .bank-cp-dd-empty p {
            margin: 0 0 0.5rem;
            font-size: 12px;
            line-height: 1.4;
            color: rgb(71 85 105);
        }
        .page-income-other .bank-1c-cp-wrap .bank-1c-dd .bank-cp-dd-add {
            display: inline-flex;
            width: 100%;
            align-items: center;
            justify-content: center;
            padding: 0.4rem 0.65rem;
            font-size: 12px;
            font-weight: 600;
            color: rgb(4 120 87);
            background: #fff;
            border: 1px solid rgb(167 243 208);
            border-radius: 0.375rem;
            cursor: pointer;
            transition:
                background 0.15s ease,
                border-color 0.15s ease;
        }
        .page-income-other .bank-1c-cp-wrap .bank-1c-dd .bank-cp-dd-add:hover {
            background: rgb(236 253 245);
            border-color: rgb(52 211 153);
        }
        .page-income-other .income-other-cat-wrap .income-other-cat-dd {
            border-radius: 0.5rem;
            border: 1px solid rgb(203 213 225);
            background: #fff;
            box-shadow:
                0 4px 6px -1px rgb(0 0 0 / 0.07),
                0 10px 24px -8px rgb(5 150 105 / 0.18);
            overflow: hidden;
            max-height: min(17rem, 65vh);
            overflow-y: auto;
        }
        .page-income-other .income-other-cat-wrap .income-other-cat-dd button.cat-suggest-row {
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
        .page-income-other .income-other-cat-wrap .income-other-cat-dd button.cat-suggest-row:last-of-type {
            border-bottom: 0;
        }
        .page-income-other .income-other-cat-wrap .income-other-cat-dd button.cat-suggest-row:hover,
        .page-income-other .income-other-cat-wrap .income-other-cat-dd button.cat-suggest-row:focus {
            background: rgb(236 253 245);
            outline: none;
        }
        .page-income-other .income-other-cat-wrap .income-other-cat-dd .bank-cp-dd-status {
            margin: 0;
            padding: 0.5rem 0.75rem;
            font-size: 12px;
            color: rgb(71 85 105);
            background: rgb(248 250 252);
            border-bottom: 1px solid rgb(241 245 249);
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
            <script>
                window.__bankCounterpartyFieldInit = @json($cpField);
                window.__bankCashExpenseCategoryFieldInit = @json($bankCashExpenseCategoryFieldInit);
                /** Показ блока кредитора без Alpine (надёжнее внутри form + grid). */
                window.incomeOtherToggleLoanCp = function (form) {
                    if (!form) {
                        return;
                    }
                    var block = form.querySelector('[data-income-other-loan-cp]');
                    if (!block) {
                        return;
                    }
                    var loan = form.querySelector('input[name="income_kind"][value="loan"]');
                    block.style.display = loan && loan.checked ? 'block' : 'none';
                };
            </script>

            <div
                class="rounded-[1.75rem] bg-gradient-to-br from-emerald-100/70 via-white to-cyan-50/60 p-[3px] shadow-[0_22px_50px_-18px_rgba(5,150,105,0.35)] ring-1 ring-emerald-200/55"
            >
                <div class="rounded-[1.6rem] bg-gradient-to-b from-white via-white to-slate-50/90 px-4 py-5 sm:px-6 sm:py-7">
                    <div class="mb-5 flex flex-col gap-3 border-b border-slate-200/80 pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-emerald-800/90">Банк и касса</p>
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
                                <h2>Приход денежных средств — прочие / займ @if ($isEdit)<span class="font-normal text-slate-600">(№{{ $movement->id }})</span>@endif</h2>
                            </div>

                            <form
                                id="income-other-form"
                                method="POST"
                                action="{{ $isEdit ? route('admin.bank.income-other.update', $movement->id) : route('admin.bank.income-other.store') }}"
                                class="contents"
                                onsubmit="return (function (f) { var k = f.querySelector('input[name=income_kind]:checked'); if (k && k.value === 'loan') { var el = f.querySelector('input[name=counterparty_id]'); var cp = parseInt(String((el && el.value) || '0'), 10); if (!cp) { window.alert('Укажите кредитора: выберите контрагента с типом «прочее» в справочнике.'); return false; } } return true; })(this)"
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
                                            value="{{ old('amount', $isEdit ? number_format((float) $movement->amount, 2, ',', ' ') : '') }}"
                                        />
                                        <x-input-error class="mt-1" :messages="$errors->get('amount')" />
                                    </div>
                                    <div class="bank-1c-span-full space-y-2">
                                        <span class="bank-1c-field-label">Тип операции *</span>
                                        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:gap-x-6">
                                            <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-800">
                                                <input
                                                    type="radio"
                                                    name="income_kind"
                                                    value="plain"
                                                    class="mt-1"
                                                    @checked($defaultIncomeKind === 'plain')
                                                    onchange="window.incomeOtherToggleLoanCp(this.form)"
                                                />
                                                <span class="font-semibold">Прочий приход</span>
                                            </label>
                                            <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-800">
                                                <input
                                                    type="radio"
                                                    name="income_kind"
                                                    value="loan"
                                                    class="mt-1"
                                                    @checked($defaultIncomeKind === 'loan')
                                                    onchange="window.incomeOtherToggleLoanCp(this.form)"
                                                />
                                                <span class="font-semibold">Получение займа</span>
                                            </label>
                                        </div>
                                        <x-input-error class="mt-1" :messages="$errors->get('income_kind')" />
                                    </div>
                                    <div
                                        class="bank-1c-span-full"
                                        data-income-other-loan-cp
                                        style="{{ $defaultIncomeKind === 'loan' ? '' : 'display: none' }}"
                                    >
                                            <div
                                                class="relative bank-1c-cp-wrap"
                                                x-ref="bankCpRoot"
                                                x-data="bankCounterpartyField()"
                                            >
                                                <span class="bank-1c-field-label">Кредитор (контрагент «прочее») *</span>
                                                <input
                                                    type="text"
                                                    x-ref="bankCpInput"
                                                    autocomplete="off"
                                                    :value="query"
                                                    @focus="onCpFocus($event)"
                                                    @input="onCpInput($event)"
                                                    @blur="onCpBlur()"
                                                />
                                                <input type="hidden" name="counterparty_id" :value="counterpartyId" />
                                                <x-input-error class="mt-1" :messages="$errors->get('counterparty_id')" />

                                                <div
                                                    x-cloak
                                                    x-show="showCpDropdown()"
                                                    class="bank-1c-dd fixed z-[220]"
                                                    role="listbox"
                                                    @mousedown.prevent
                                                    :style="'top:' + cpPos.top + 'px;left:' + cpPos.left + 'px;width:' + cpPos.width + 'px'"
                                                >
                                                    <div x-show="cpLoading" class="bank-cp-dd-status">Поиск…</div>
                                                    <template x-for="item in cpItems" :key="item.id">
                                                        <button
                                                            type="button"
                                                            class="cp-row"
                                                            @mousedown.prevent
                                                            @click="pickCounterparty(item)"
                                                        >
                                                            <span x-text="item.full_name || item.name"></span>
                                                            <span class="cp-kind" x-text="kindLabel(item.kind)"></span>
                                                        </button>
                                                    </template>
                                                    <div x-show="!cpLoading && cpNoHits && cpItems.length === 0 && !cpQuickOpen" class="bank-cp-dd-empty">
                                                        <p>Нет совпадений в «прочее».</p>
                                                        <button type="button" class="bank-cp-dd-add" @mousedown.prevent @click="openCpQuickAdd($event)" x-text="quickBtnAdd"></button>
                                                    </div>
                                                    <div x-show="cpQuickOpen" class="cp-quick space-y-2 rounded-b-lg">
                                                        <p class="m-0 text-xs font-semibold text-slate-800" x-text="quickTitle"></p>
                                                        <label class="bank-1c-field-label mb-0" for="bank_cp_quick_lf_io">Правовая форма</label>
                                                        <select id="bank_cp_quick_lf_io" x-model="cpQuickLegalForm" @mousedown.stop>
                                                            @foreach (\App\Models\Counterparty::legalFormLabels() as $k => $label)
                                                                <option value="{{ $k }}">{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                        <p x-show="cpQuickError" class="m-0 text-[11px] text-red-700" x-text="cpQuickError"></p>
                                                        <div class="flex flex-wrap gap-2">
                                                            <button
                                                                type="button"
                                                                class="bank-1c-tb-btn"
                                                                :disabled="cpQuickSaving"
                                                                @mousedown.prevent
                                                                @click="submitCpQuickAdd()"
                                                            >
                                                                <span x-show="!cpQuickSaving">Создать и подставить</span>
                                                                <span x-show="cpQuickSaving">Сохранение…</span>
                                                            </button>
                                                            <button type="button" class="bank-1c-tb-btn" @mousedown.prevent @click="cpQuickOpen = false; cpQuickError = ''">
                                                                Отмена
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                    </div>
                                    <div class="bank-1c-span-full">
                                        <div
                                            class="relative income-other-cat-wrap"
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
                                                class="income-other-cat-dd fixed z-[210]"
                                                role="listbox"
                                                aria-label="Категории"
                                                @mousedown.prevent
                                                x-bind:style="'top:' + catPos.top + 'px;left:' + catPos.left + 'px;width:' + catPos.width + 'px'"
                                            >
                                                <div x-show="catLoading" class="bank-cp-dd-status rounded-t-lg">Поиск…</div>
                                                <template x-for="(label, idx) in catItems" :key="'ioc-' + idx + '-' + label">
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
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var f = document.getElementById('income-other-form');
                    if (f && window.incomeOtherToggleLoanCp) {
                        window.incomeOtherToggleLoanCp(f);
                    }
                });
            </script>
        @endif
    </div>
</x-admin-layout>
