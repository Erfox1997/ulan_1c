@props([
    'submitLabel' => 'Записать',
    'showToolbarBackLink' => true,
])
@php
    use App\Models\Counterparty;
    /** @var \App\Models\Counterparty $counterparty */
    /** @var array<int, array<string, mixed>> $bankAccounts */
    /** @var int $defaultBankIndex */
    $isEdit = $counterparty->exists;
    $kindLabels = Counterparty::kindLabels();
    $legalLabels = Counterparty::legalFormLabels();

    $kindFallback = $isEdit ? (string) $counterparty->kind : Counterparty::KIND_SUPPLIER;
    if (! array_key_exists($kindFallback, $kindLabels)) {
        $kindFallback = Counterparty::KIND_BUYER;
    }

    $legalFallback = $isEdit ? (string) $counterparty->legal_form : Counterparty::LEGAL_OSOO;
    if (! array_key_exists($legalFallback, $legalLabels)) {
        $legalFallback = Counterparty::LEGAL_OSOO;
    }

    $useOldInput = $errors->any() && session()->has('_old_input');

    if ($useOldInput) {
        $kindValue = old('kind', $kindFallback);
        if (! is_string($kindValue) || $kindValue === '' || ! array_key_exists($kindValue, $kindLabels)) {
            $kindValue = $kindFallback;
        }

        $legalFormValue = old('legal_form', $legalFallback);
        if (! is_string($legalFormValue) || $legalFormValue === '' || ! array_key_exists($legalFormValue, $legalLabels)) {
            $legalFormValue = $legalFallback;
        }

        $nameFallback = $isEdit ? (string) ($counterparty->name ?? '') : '';
        $nameValue = old('name', $nameFallback);
        if (! is_string($nameValue)) {
            $nameValue = $nameFallback;
        }
        if ($nameValue === '' && $isEdit && filled($counterparty->name) && ! $errors->has('name')) {
            $nameValue = (string) $counterparty->name;
        }

        $innValue = (string) old('inn', $counterparty->inn ?? '');
        $phoneValue = (string) old('phone', $counterparty->phone ?? '');
        $addressValue = (string) old('address', $counterparty->address ?? '');

        $openingDebtDefault = $isEdit
            ? (string) ($kindValue === Counterparty::KIND_SUPPLIER
                ? ($counterparty->getAttribute('opening_debt_as_supplier') ?? '')
                : ($counterparty->getAttribute('opening_debt_as_buyer') ?? ''))
            : '';
        $openingDebtSingle = (string) old('opening_debt', $openingDebtDefault);
    } else {
        $kindValue = $kindFallback;
        $legalFormValue = $legalFallback;

        $nameValue = $isEdit ? (string) ($counterparty->name ?? '') : '';
        $innValue = (string) ($counterparty->inn ?? '');
        $phoneValue = (string) ($counterparty->phone ?? '');
        $addressValue = (string) ($counterparty->address ?? '');

        $openingDebtDefault = $isEdit
            ? (string) ($kindValue === Counterparty::KIND_SUPPLIER
                ? ($counterparty->getAttribute('opening_debt_as_supplier') ?? '')
                : ($counterparty->getAttribute('opening_debt_as_buyer') ?? ''))
            : '';
        $openingDebtSingle = $openingDebtDefault;
    }
@endphp
@include('admin.counterparties.partials.cp-theme')

<form
    method="POST"
    action="{{ $isEdit ? route('admin.counterparties.update', $counterparty) : route('admin.counterparties.store') }}"
    class="cp-root w-full min-w-0"
    x-data="{
        legalForm: @json($legalFormValue),
        cpShortName: '',
        kind: @json($kindValue),
        openingDebtLabel() {
            if (this.kind === '{{ Counterparty::KIND_SUPPLIER }}') return 'Ваш долг поставщику, сом';
            if (this.kind === '{{ Counterparty::KIND_BUYER }}') return 'Долг вам (покупатель), сом';
            return 'Начальный долг, сом';
        },
        fullNameLive() {
            const n = String(this.cpShortName ?? '').trim();
            if (!n) return '';
            if (this.legalForm === '{{ Counterparty::LEGAL_IP }}') return 'ИП ' + n;
            if (this.legalForm === '{{ Counterparty::LEGAL_OSOO }}') return 'ОсОО «' + n + '»';
            return n;
        },
    }"
    x-init="$nextTick(() => {
        if ($refs.kindSelect) kind = $refs.kindSelect.value;
        if ($refs.legalFormSelect) legalForm = $refs.legalFormSelect.value;
    })"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="cp-panel">
        @if ($showToolbarBackLink)
            <div class="cp-toolbar">
                <a href="{{ route('admin.counterparties.index') }}" class="cp-btn">← К списку</a>
            </div>
        @endif

        <div class="cp-titlebar">
            <h2 class="cp-title">
                @if ($isEdit)
                    Контрагент
                @else
                    Данные карточки
                @endif
            </h2>
        </div>

        <div class="cp-grid cp-grid-2 cp-section-divider">
            <div>
                <label for="kind" class="cp-label">Тип *</label>
                <select
                    id="kind"
                    name="kind"
                    required
                    class="cp-field"
                    x-ref="kindSelect"
                    @change="kind = $event.target.value"
                >
                    @foreach ($kindLabels as $value => $label)
                        <option value="{{ $value }}" @selected($kindValue === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error class="mt-1" :messages="$errors->get('kind')" />
            </div>
            <div>
                <label for="counterparty_name" class="cp-label">Наименование *</label>
                <input
                    id="counterparty_name"
                    name="name"
                    type="text"
                    value="{{ $nameValue }}"
                    required
                    class="cp-field"
                    autocomplete="organization"
                    x-init="cpShortName = $el.value"
                    @input="cpShortName = $event.target.value"
                />
                <x-input-error class="mt-1" :messages="$errors->get('name')" />
            </div>
            <div>
                <label for="legal_form" class="cp-label">Правовая форма *</label>
                <select
                    id="legal_form"
                    name="legal_form"
                    required
                    class="cp-field"
                    x-ref="legalFormSelect"
                    @change="legalForm = $event.target.value"
                >
                    @foreach ($legalLabels as $value => $label)
                        <option value="{{ $value }}" @selected($legalFormValue === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error class="mt-1" :messages="$errors->get('legal_form')" />
            </div>
            <div>
                <label for="full_name_preview" class="cp-label">Полное наименование</label>
                <input
                    id="full_name_preview"
                    type="text"
                    readonly
                    tabindex="-1"
                    x-bind:value="fullNameLive()"
                    class="cp-field cp-field-readonly"
                />
            </div>
        </div>

        <div class="cp-subhead">Реквизиты</div>
        <div class="cp-grid cp-grid-2 cp-section-divider">
            <div>
                <label for="inn" class="cp-label">ИНН</label>
                <input id="inn" name="inn" type="text" value="{{ $innValue }}" class="cp-field" />
                <x-input-error class="mt-1" :messages="$errors->get('inn')" />
            </div>
            <div>
                <label for="phone" class="cp-label">Телефон</label>
                <input id="phone" name="phone" type="text" value="{{ $phoneValue }}" placeholder="+996 …" class="cp-field" />
                <x-input-error class="mt-1" :messages="$errors->get('phone')" />
            </div>
            <div class="sm:col-span-2">
                <label for="address" class="cp-label">Адрес</label>
                <textarea id="address" name="address" rows="2" class="cp-field min-h-[4rem] resize-y">{{ $addressValue }}</textarea>
                <x-input-error class="mt-1" :messages="$errors->get('address')" />
            </div>
        </div>

        <div class="cp-subhead">Начальные долги</div>
        <div class="cp-grid cp-section-divider">
            <div class="max-w-md">
                <label for="opening_debt" class="cp-label">
                    <span x-text="openingDebtLabel()">Начальный долг, сом</span>
                </label>
                <input
                    id="opening_debt"
                    name="opening_debt"
                    type="text"
                    inputmode="decimal"
                    autocomplete="off"
                    placeholder="0"
                    value="{{ $openingDebtSingle }}"
                    class="cp-field tabular-nums"
                />
                <x-input-error class="mt-1" :messages="$errors->get('opening_debt')" />
            </div>
        </div>

        <div class="cp-subhead">Счета в банке и наличные</div>

        <div
            class="cp-bank-section cp-section-divider px-3 py-3 sm:px-4"
            x-data="organizationBankRows({{ \Illuminate\Support\Js::from($bankAccounts) }}, {{ (int) $defaultBankIndex }})"
        >
            <div class="space-y-3">
                <template x-for="(acc, index) in accounts" :key="index">
                    <div class="cp-bank-card">
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <span class="text-[11px] font-semibold text-neutral-600">Счёт <span x-text="index + 1"></span></span>
                            <div class="flex flex-wrap items-center gap-3">
                                <label class="flex cursor-pointer items-center gap-1.5 text-[11px] text-neutral-800">
                                    <input
                                        type="radio"
                                        name="default_bank_index"
                                        class="border-slate-400"
                                        :value="index"
                                        x-model.number="defaultIdx"
                                    >
                                    <span>По умолчанию</span>
                                </label>
                                <button type="button" class="text-[11px] font-semibold text-red-700 hover:underline" @click="removeRow(index)">Удалить</button>
                            </div>
                        </div>
                        <input type="hidden" :name="`bank_accounts[${index}][id]`" :value="acc.id ?? ''">
                        <select
                            :name="`bank_accounts[${index}][account_type]`"
                            x-model="acc.account_type"
                            class="cp-field mb-3 max-w-xs"
                            @change="if (acc.account_type === 'cash') { acc.bank_name = ''; acc.bik = ''; }"
                        >
                            <option value="bank">Банковский счёт</option>
                            <option value="cash">Наличные</option>
                        </select>

                        <template x-if="acc.account_type === 'bank'">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="cp-label" :for="'cp_ba_num_'+index">Номер счёта (р/с) *</label>
                                    <input
                                        :id="'cp_ba_num_'+index"
                                        :name="`bank_accounts[${index}][account_number]`"
                                        type="text"
                                        x-model="acc.account_number"
                                        class="cp-field"
                                    >
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="cp-label" :for="'cp_bank_'+index">Наименование банка *</label>
                                    <input
                                        :id="'cp_bank_'+index"
                                        :name="`bank_accounts[${index}][bank_name]`"
                                        type="text"
                                        x-model="acc.bank_name"
                                        class="cp-field"
                                    >
                                </div>
                                <div>
                                    <label class="cp-label">БИК</label>
                                    <input :name="`bank_accounts[${index}][bik]`" type="text" x-model="acc.bik" class="cp-field">
                                </div>
                                <div>
                                    <label class="cp-label">Валюта</label>
                                    <input :name="`bank_accounts[${index}][currency]`" type="text" maxlength="3" x-model="acc.currency" class="cp-field uppercase">
                                </div>
                            </div>
                        </template>

                        <template x-if="acc.account_type === 'cash'">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="cp-label">Подпись (необязательно)</label>
                                    <input
                                        :name="`bank_accounts[${index}][account_number]`"
                                        type="text"
                                        x-model="acc.account_number"
                                        class="cp-field"
                                    >
                                </div>
                                <div>
                                    <label class="cp-label">Валюта</label>
                                    <input :name="`bank_accounts[${index}][currency]`" type="text" maxlength="3" x-model="acc.currency" class="cp-field uppercase">
                                </div>
                                <input type="hidden" :name="`bank_accounts[${index}][bank_name]`" value="">
                                <input type="hidden" :name="`bank_accounts[${index}][bik]`" value="">
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" class="cp-btn" @click="addBankRow()">+ Банковский счёт</button>
                <button type="button" class="cp-btn" @click="addCashRow()">+ Наличные</button>
            </div>

            <x-input-error class="mt-2" :messages="$errors->get('bank_accounts')" />
        </div>

        <div class="cp-foot">
            <a href="{{ route('admin.counterparties.index') }}" class="cp-btn min-h-[28px] px-4">Отмена</a>
            <button type="submit" class="cp-btn cp-btn-primary min-h-[28px] px-5">{{ $submitLabel }}</button>
        </div>
    </div>
</form>
