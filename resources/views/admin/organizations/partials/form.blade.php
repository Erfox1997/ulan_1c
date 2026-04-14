@props(['submitLabel' => 'Сохранить', 'formTitle' => 'Организация'])
@php
    /** @var \App\Models\Organization $organization */
    /** @var array<int, array<string, mixed>> $bankAccounts */
    /** @var int $defaultBankIndex */
@endphp
@include('admin.counterparties.partials.cp-theme')
@include('admin.organizations.partials.org-form-styles')

<form
    method="POST"
    class="org-form-scope cp-root w-full min-w-0"
    action="{{ $organization->exists ? route('admin.organizations.update', $organization) : route('admin.organizations.store') }}"
>
    @csrf
    @if ($organization->exists)
        @method('PUT')
    @endif

    <div class="cp-panel org-panel">
        <div class="cp-toolbar">
            <a href="{{ route('admin.organizations.index') }}" class="cp-btn org-btn-ghost">← К списку</a>
        </div>

        <div class="org-titlebar px-4 py-3.5 sm:px-5 sm:py-4">
            <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-600 text-white shadow-lg shadow-emerald-500/30 ring-2 ring-white/60" aria-hidden="true">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="mb-0.5 text-[10px] font-semibold uppercase tracking-wider text-teal-700/90">Справочник</p>
                    <h2 class="cp-title text-[15px] leading-tight text-slate-800">{{ $formTitle }}</h2>
                </div>
            </div>
        </div>

        <div class="org-subhead">Основные данные</div>
        <div class="cp-grid cp-grid-2 org-grid-block px-3 py-4 sm:px-5 sm:py-5">
            <div class="sm:col-span-2">
                <label for="name" class="cp-label">Полное наименование *</label>
                <input id="name" name="name" type="text" class="cp-field" value="{{ old('name', $organization->name) }}" required autocomplete="organization" />
                <x-input-error class="mt-1 text-xs" :messages="$errors->get('name')" />
            </div>
            <div>
                <label for="short_name" class="cp-label">Краткое наименование</label>
                <input id="short_name" name="short_name" type="text" class="cp-field" value="{{ old('short_name', $organization->short_name) }}" />
                <x-input-error class="mt-1 text-xs" :messages="$errors->get('short_name')" />
            </div>
            <div>
                <label for="legal_form" class="cp-label">Правовая форма</label>
                <input id="legal_form" name="legal_form" type="text" class="cp-field" placeholder="ТОО, ИП, …" value="{{ old('legal_form', $organization->legal_form) }}" />
            </div>
            <div>
                <label for="inn" class="cp-label">ИНН</label>
                <input id="inn" name="inn" type="text" class="cp-field tabular-nums" value="{{ old('inn', $organization->inn) }}" />
            </div>
            <div>
                <label for="sort_order" class="cp-label">Порядок в списке</label>
                <input id="sort_order" name="sort_order" type="number" min="0" class="cp-field tabular-nums" value="{{ old('sort_order', $organization->sort_order ?? 0) }}" />
            </div>
            <div class="sm:col-span-2">
                <label for="legal_address" class="cp-label">Юридический адрес</label>
                <textarea id="legal_address" name="legal_address" rows="2" class="cp-field min-h-[4rem] resize-y">{{ old('legal_address', $organization->legal_address) }}</textarea>
            </div>
            <div class="sm:col-span-2">
                <label for="phone" class="cp-label">Телефон</label>
                <input id="phone" name="phone" type="text" class="cp-field" value="{{ old('phone', $organization->phone) }}" placeholder="+996 …" />
            </div>
            <div class="sm:col-span-2">
                <label for="notes" class="cp-label">Примечания</label>
                <textarea id="notes" name="notes" rows="2" class="cp-field min-h-[4rem] resize-y">{{ old('notes', $organization->notes) }}</textarea>
            </div>
            <div class="sm:col-span-2 flex items-center gap-3 rounded-lg border border-emerald-100/90 bg-emerald-50/40 px-3 py-2.5">
                <input id="is_default" name="is_default" type="checkbox" value="1" class="h-4 w-4 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500/40" @checked(old('is_default', $organization->is_default))>
                <label for="is_default" class="cp-label !mb-0 cursor-pointer font-normal leading-snug text-emerald-950/90">Использовать по умолчанию при выставлении счетов</label>
            </div>
        </div>

        <div class="org-subhead">Счета в банке и наличные</div>

        <div
            class="org-bank-zone px-3 py-4 sm:px-5 sm:py-5"
            x-data="organizationBankRows({{ \Illuminate\Support\Js::from($bankAccounts) }}, {{ (int) $defaultBankIndex }})"
        >
            <div class="space-y-3">
                <template x-for="(acc, index) in accounts" :key="index">
                    <div class="org-bank-card p-3 sm:p-3.5">
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-800 ring-1 ring-emerald-200/80">
                                <span class="text-emerald-600">Строка</span>
                                <span x-text="index + 1"></span>
                            </span>
                            <div class="flex flex-wrap items-center gap-2">
                                <label class="flex cursor-pointer items-center gap-1.5 text-[11px] font-medium text-slate-700">
                                    <input
                                        type="radio"
                                        name="default_bank_index"
                                        class="border-slate-300 text-emerald-600 focus:ring-emerald-500/40"
                                        :value="index"
                                        x-model.number="defaultIdx"
                                    >
                                    <span>По умолчанию</span>
                                </label>
                                <button type="button" class="text-[11px] font-semibold text-rose-600 hover:text-rose-800 hover:underline" @click="removeRow(index)">Удалить</button>
                            </div>
                        </div>
                        <input type="hidden" :name="`bank_accounts[${index}][id]`" :value="acc.id ?? ''">
                        <div class="w-full">
                            <label :for="'org_acc_type_'+index" class="sr-only">Тип счёта</label>
                            <select
                                :id="'org_acc_type_'+index"
                                :name="`bank_accounts[${index}][account_type]`"
                                x-model="acc.account_type"
                                class="cp-field mb-0 w-full"
                                @change="if (acc.account_type === 'cash') { acc.bank_name = ''; acc.bik = ''; }"
                            >
                                <option value="bank">Банковский счёт</option>
                                <option value="cash">Наличные</option>
                            </select>
                        </div>

                        <template x-if="acc.account_type === 'bank'">
                            <div class="mt-2 flex w-full flex-col gap-2.5">
                                <div class="w-full min-w-0">
                                    <label class="cp-label !mb-0.5" :for="'org_ba_num_'+index">Номер счёта (р/с) *</label>
                                    <input
                                        :id="'org_ba_num_'+index"
                                        :name="`bank_accounts[${index}][account_number]`"
                                        type="text"
                                        x-model="acc.account_number"
                                        class="cp-field w-full"
                                    >
                                </div>
                                <div class="w-full min-w-0">
                                    <label class="cp-label !mb-0.5" :for="'org_bank_'+index">Наименование банка *</label>
                                    <input
                                        :id="'org_bank_'+index"
                                        :name="`bank_accounts[${index}][bank_name]`"
                                        type="text"
                                        x-model="acc.bank_name"
                                        class="cp-field w-full"
                                    >
                                </div>
                                <div class="flex w-full min-w-0 flex-row flex-nowrap gap-3 sm:gap-4">
                                    <div class="min-w-0 flex-1 basis-0">
                                        <label class="cp-label !mb-0.5">БИК</label>
                                        <input
                                            :name="`bank_accounts[${index}][bik]`"
                                            type="text"
                                            x-model="acc.bik"
                                            class="cp-field w-full min-w-0 tabular-nums"
                                            autocomplete="off"
                                        >
                                    </div>
                                    <div class="min-w-0 flex-1 basis-0">
                                        <label class="cp-label !mb-0.5">Валюта</label>
                                        <input
                                            :name="`bank_accounts[${index}][currency]`"
                                            type="text"
                                            maxlength="3"
                                            x-model="acc.currency"
                                            class="cp-field w-full min-w-0 text-center uppercase tracking-wide"
                                        >
                                    </div>
                                    <div class="min-w-0 flex-1 basis-0">
                                        <label class="cp-label !mb-0.5">Начальный остаток</label>
                                        <input
                                            :name="`bank_accounts[${index}][opening_balance]`"
                                            type="text"
                                            inputmode="decimal"
                                            x-model="acc.opening_balance"
                                            placeholder="0,00"
                                            class="cp-field w-full min-w-0 tabular-nums"
                                        >
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template x-if="acc.account_type === 'cash'">
                            <div class="mt-2 flex w-full flex-col gap-2.5">
                                <div class="w-full min-w-0">
                                    <label class="cp-label !mb-0.5">Подпись (необязательно)</label>
                                    <p class="mb-1 mt-0 text-[10px] leading-tight text-slate-500">Например: «Основная касса».</p>
                                    <input
                                        :name="`bank_accounts[${index}][account_number]`"
                                        type="text"
                                        x-model="acc.account_number"
                                        class="cp-field w-full"
                                    >
                                </div>
                                <div class="grid w-full grid-cols-2 gap-3">
                                    <div class="min-w-0">
                                        <label class="cp-label !mb-0.5">Валюта</label>
                                        <input
                                            :name="`bank_accounts[${index}][currency]`"
                                            type="text"
                                            maxlength="3"
                                            x-model="acc.currency"
                                            class="cp-field w-full min-w-0 uppercase tracking-wide"
                                        >
                                    </div>
                                    <div class="min-w-0">
                                        <label class="cp-label !mb-0.5">Остаток в кассе</label>
                                        <input
                                            :name="`bank_accounts[${index}][opening_balance]`"
                                            type="text"
                                            inputmode="decimal"
                                            x-model="acc.opening_balance"
                                            placeholder="0,00"
                                            class="cp-field w-full min-w-0 tabular-nums"
                                        >
                                    </div>
                                </div>
                                <input type="hidden" :name="`bank_accounts[${index}][bank_name]`" value="">
                                <input type="hidden" :name="`bank_accounts[${index}][bik]`" value="">
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" class="cp-btn org-btn-ghost font-semibold text-sky-800" @click="addBankRow()">+ Банковский счёт</button>
                <button type="button" class="cp-btn org-btn-ghost font-semibold text-teal-800" @click="addCashRow()">+ Наличные</button>
            </div>

            <x-input-error class="mt-2" :messages="$errors->get('bank_accounts')" />
        </div>

        <div class="cp-foot org-foot flex flex-wrap items-center justify-end gap-3 px-4 py-3.5 sm:px-5">
            <a href="{{ route('admin.organizations.index') }}" class="cp-btn min-h-[32px] px-5 org-btn-ghost">Отмена</a>
            <button type="submit" class="cp-btn cp-btn-primary min-h-[32px] px-6 font-bold shadow-md shadow-amber-400/25 ring-1 ring-amber-300/50">{{ $submitLabel }}</button>
        </div>
    </div>
</form>
