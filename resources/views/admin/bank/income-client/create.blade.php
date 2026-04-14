@php
    $isEdit = isset($movement) && $movement !== null;
@endphp
<x-admin-layout pageTitle="{{ $isEdit ? 'Редактирование: приход от покупателя' : 'Новая операция: приход от покупателя' }}" main-class="bg-slate-100/80 px-3 py-5 sm:px-6 lg:px-8">
    @include('admin.partials.cp-brush')
    @include('admin.bank.partials.1c-form-document-styles')
    @include('admin.bank.partials.1c-form-modern-inputs')

    <style>
        .page-income-client .bank-1c-doc {
            border-radius: 0.75rem;
            border-color: rgb(148 163 184 / 0.45);
            box-shadow: 0 10px 40px -12px rgb(14 165 233 / 0.14);
        }
        .page-income-client .bank-1c-titlebar {
            border-top: 3px solid #0ea5e9;
            border-bottom-color: rgb(148 163 184 / 0.35);
            background: linear-gradient(180deg, #f0f9ff 0%, #ecfeff 55%, #f8fafc 100%);
        }
        .page-income-client .bank-1c-titlebar h2 {
            font-size: 14px;
            letter-spacing: -0.01em;
            color: #0f172a;
        }
        .page-income-client .bank-1c-toolbar {
            background: linear-gradient(180deg, #e0f2fe 0%, #ecfeff 100%);
            border-bottom-color: rgb(125 211 252 / 0.55);
        }
        .page-income-client .bank-1c-header {
            background: linear-gradient(180deg, #fafefe 0%, #f8fafc 100%);
            border-bottom-color: rgb(203 213 225 / 0.6);
        }
        .page-income-client .bank-1c-foot {
            background: linear-gradient(180deg, #f8fafc 0%, #f0f9ff 100%);
            border-top-color: rgb(203 213 225 / 0.65);
            padding: 12px 16px;
        }
        .page-income-client .bank-1c-foot a.bank-foot-link-plain {
            font-size: 14px;
            font-weight: 600;
            color: #0369a1;
            text-decoration: underline;
            text-decoration-color: rgb(56 189 248 / 0.45);
            text-underline-offset: 2px;
        }
        .page-income-client .bank-1c-foot a.bank-foot-link-plain:hover {
            color: #0c4a6e;
            text-decoration-color: #0284c7;
        }
    </style>

    <div class="mx-auto w-full max-w-4xl space-y-4">
        <a
            href="{{ route('admin.bank.income-client') }}"
            class="inline-flex items-center text-sm font-semibold text-sky-800 underline decoration-sky-400/50 underline-offset-2 transition hover:text-sky-950 hover:decoration-sky-600"
        >← К списку операций</a>

        @include('admin.partials.status-flash')
        @include('admin.bank.partials.no-accounts')

        @if ($accounts->isNotEmpty())
            <script>
                window.__bankCounterpartyFieldInit = @json($cpField);
            </script>

            <div
                class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/80 via-white to-emerald-50/55 p-[3px] shadow-[0_22px_50px_-18px_rgba(14,165,233,0.32)] ring-1 ring-sky-200/60"
            >
                <div class="rounded-[1.6rem] bg-gradient-to-b from-white via-white to-slate-50/90 px-4 py-5 sm:px-6 sm:py-7">
                    <div class="mb-5 flex flex-col gap-3 border-b border-slate-200/80 pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-sky-800/90">Банк и касса</p>
                            <p class="mt-1 max-w-xl text-sm leading-relaxed text-slate-600">
                                @if ($isEdit)
                                    Изменение записи №{{ $movement->id }}: дата, счёт, сумма, клиент и комментарий.
                                @else
                                    Поступление оплаты от покупателя: выберите счёт зачисления, сумму и контрагента из справочника.
                                @endif
                            </p>
                        </div>
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-600 to-emerald-600 text-white shadow-lg shadow-sky-900/20 ring-2 ring-white/50"
                            aria-hidden="true"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                        </div>
                    </div>

                    <div class="page-income-client bank-1c-page-modern bank-1c-scope w-full min-w-0">
                        <div class="bank-1c-doc w-full">
                            <div class="bank-1c-titlebar">
                                <h2>Приход денежных средств — оплата от покупателя @if ($isEdit)<span class="font-normal text-slate-600">(№{{ $movement->id }})</span>@endif</h2>
                            </div>

                            <form
                                method="POST"
                                action="{{ $isEdit ? route('admin.bank.income-client.update', $movement->id) : route('admin.bank.income-client.store') }}"
                                class="contents"
                                x-data="bankCounterpartyField()"
                                @keydown.escape.window="onCpEscape()"
                                @submit="validateBeforeSubmit($event)"
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
                                        <span class="bank-1c-field-label">Счёт / касса получателя *</span>
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
                                    <div class="relative bank-1c-cp-wrap bank-1c-span-full" x-ref="bankCpRoot">
                                        <span class="bank-1c-field-label">Клиент (контрагент) *</span>
                                        <input
                                            type="text"
                                            x-ref="bankCpInput"
                                            autocomplete="off"
                                            placeholder="Начните вводить наименование (не менее 2 букв)"
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
                                            <div x-show="cpLoading" class="cp-foot text-[#666]">Поиск…</div>
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
                                            <div x-show="!cpLoading && cpNoHits && cpItems.length === 0 && !cpQuickOpen" class="cp-foot space-y-2">
                                                <p class="m-0">Нет подходящих контрагентов (покупатель / прочий).</p>
                                                <button type="button" class="bank-1c-tb-btn w-full justify-center" @mousedown.prevent @click="openCpQuickAdd($event)" x-text="quickBtnAdd"></button>
                                            </div>
                                            <div x-show="cpQuickOpen" class="cp-quick space-y-2">
                                                <p class="m-0 text-[11px] font-semibold" x-text="quickTitle"></p>
                                                <label class="bank-1c-field-label mb-0" for="bank_cp_quick_lf_in">Правовая форма</label>
                                                <select id="bank_cp_quick_lf_in" x-model="cpQuickLegalForm" @mousedown.stop>
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
                                    <div class="bank-1c-span-full">
                                        <span class="bank-1c-field-label">Комментарий</span>
                                        <textarea name="comment" rows="4">{{ old('comment', $isEdit ? (string) $movement->comment : '') }}</textarea>
                                        <x-input-error class="mt-1" :messages="$errors->get('comment')" />
                                    </div>
                                </div>

                                <div class="bank-1c-foot flex flex-wrap items-center justify-between gap-3">
                                    <a href="{{ route('admin.counterparties.index') }}" class="bank-foot-link-plain">Открыть справочник контрагентов</a>
                                    <a
                                        href="{{ route('admin.counterparties.create') }}"
                                        class="inline-flex items-center rounded-lg border border-sky-200/90 bg-white px-3 py-1.5 text-sm font-semibold text-slate-800 shadow-sm ring-1 ring-slate-900/[0.04] transition hover:border-sky-300 hover:bg-sky-50/60"
                                    >Создать контрагента (полная карточка)</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-admin-layout>
