@php
    $fmt = static fn ($v) => number_format((float) $v, 2, ',', ' ');
@endphp
<x-admin-layout pageTitle="Оплата — розница" main-class="bg-slate-100/80 px-3 py-5 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-3xl space-y-5">
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ $posUrl }}" class="text-sm font-semibold text-emerald-800 hover:underline">← Назад к корзине</a>
                <h1 class="mt-2 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Оплата</h1>
            </div>
        </div>

        <script>
            window.__retailCheckoutInit = {
                draftTotal: @json($draftTotal),
                defaultAccountId: {{ (int) ($defaultAccountId ?? 0) }},
                documentDate: @json($documentDate),
                accounts: @json($paymentAccountsPayload),
                debtorHintsUrl: @json($debtorHintsUrl),
                counterpartySearchUrl: @json($counterpartySearchUrl),
                oldDebtorName: @json(old('debtor_name', '')),
                oldDebtorPhone: @json(old('debtor_phone', '')),
                oldDebtorComment: @json(old('debtor_comment', '')),
            };
        </script>

        <form
            method="POST"
            action="{{ $storeUrl }}"
            class="overflow-hidden rounded-2xl border border-emerald-900/10 bg-white shadow-xl shadow-emerald-900/15 ring-1 ring-emerald-900/[0.05]"
            x-data="retailCheckoutForm()"
        >
            @csrf
            <div class="border-b border-emerald-900/20 px-4 py-5 text-white sm:px-6" style="background: linear-gradient(135deg, #047857 0%, #0d9488 45%, #0f766e 100%);">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-teal-100/95">Итого к оплате</p>
                        <p class="mt-1 text-2xl font-extrabold tabular-nums">{{ $fmt($draftTotal) }} сом</p>
                    </div>
                    <div class="min-w-[11rem]">
                        <label for="chk_date" class="block text-[10px] font-bold uppercase tracking-[0.12em] text-teal-100/95">Дата документа *</label>
                        <input
                            id="chk_date"
                            type="date"
                            name="document_date"
                            x-model="documentDate"
                            required
                            class="mt-2 w-full rounded-xl border-0 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 shadow-md"
                        />
                    </div>
                </div>
            </div>

            <div class="space-y-4 px-4 py-5 sm:px-6">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-bold text-slate-800">Оплаты по счетам / кассе</h2>
                    <button type="button" class="text-sm font-semibold text-emerald-700 hover:underline" @click="addPaymentRow()">+ ещё счёт</button>
                </div>

                <template x-for="(row, idx) in payments" :key="idx">
                    <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50/80 p-4 sm:flex-row sm:items-end">
                        <div class="min-w-0 flex-1">
                            <label class="mb-1 block text-xs font-medium text-slate-600">Счёт / касса</label>
                            <select
                                :name="`payments[${idx}][organization_bank_account_id]`"
                                x-model="row.organization_bank_account_id"
                                class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-sm font-semibold text-slate-900"
                            >
                                @foreach ($paymentAccountsPayload as $acc)
                                    <option value="{{ $acc['id'] }}">{{ $acc['label'] }} — {{ $acc['organization'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-full sm:w-40">
                            <label class="mb-1 block text-xs font-medium text-slate-600">Сумма, сом</label>
                            <input
                                type="text"
                                :name="`payments[${idx}][amount]`"
                                x-model="row.amount"
                                inputmode="decimal"
                                autocomplete="off"
                                @focus="$event.target.select()"
                                @mouseup="$event.preventDefault()"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-right text-sm font-bold tabular-nums"
                                placeholder="0,00"
                            />
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded-lg px-3 py-2 text-sm font-medium text-slate-500 hover:bg-red-50 hover:text-red-600"
                            x-show="payments.length > 1"
                            @click="removePaymentRow(idx)"
                        >Удалить</button>
                    </div>
                </template>

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                    <span class="font-medium text-slate-600">Внесено</span>
                    <span class="text-lg font-extrabold tabular-nums text-slate-900" x-text="sumPaidFormatted"></span>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50/90 px-4 py-3 text-sm" x-show="debtAmount > 0.004" x-cloak>
                    <span class="font-bold text-rose-900">Долг</span>
                    <span class="text-lg font-extrabold tabular-nums text-rose-950" x-text="debtFormatted"></span>
                </div>
            </div>

            <div class="space-y-3 border-t border-slate-200 bg-slate-50 px-4 py-5 sm:px-6" x-show="debtAmount > 0.004" x-cloak>
                <p class="text-sm font-bold text-slate-800">Данные по долгу</p>
                <p class="text-xs text-slate-500">Начните вводить имя — подтянутся ранее вводившиеся должники и покупатели из справочника.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="relative z-20">
                        <label for="debtor_name" class="mb-1 flex items-center gap-2 text-xs font-medium text-slate-600">
                            Имя *
                            <span x-show="debtorHintsLoading" class="text-slate-400">поиск…</span>
                        </label>
                        <input
                            id="debtor_name"
                            type="search"
                            name="debtor_name"
                            x-model="debtorName"
                            @input.debounce.300ms="fetchDebtorHints()"
                            @focus="onDebtorNameFocus()"
                            autocomplete="off"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="ФИО"
                        />
                        <div
                            x-cloak
                            x-show="debtorHintsOpen && debtorHints.length > 0"
                            @click.outside="debtorHintsOpen = false"
                            class="absolute left-0 right-0 top-full z-50 mt-1 max-h-56 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
                        >
                            <template x-for="(h, hi) in debtorHints" :key="hi">
                                <button
                                    type="button"
                                    class="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left text-sm transition hover:bg-emerald-50/90"
                                    @mousedown.prevent="pickDebtorHint(h)"
                                >
                                    <span class="font-medium text-slate-900" x-text="h.debtor_name"></span>
                                    <span
                                        class="text-xs text-slate-500"
                                        x-show="h.debtor_phone != null && String(h.debtor_phone).trim() !== ''"
                                        x-text="'Тел.: ' + h.debtor_phone"
                                    ></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <div>
                        <label for="debtor_phone" class="mb-1 block text-xs font-medium text-slate-600">Телефон *</label>
                        <input
                            id="debtor_phone"
                            type="text"
                            name="debtor_phone"
                            x-model="debtorPhone"
                            autocomplete="off"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                            placeholder="+996…"
                        />
                    </div>
                </div>
                <div>
                    <label for="debtor_comment" class="mb-1 block text-xs font-medium text-slate-600">Комментарий</label>
                    <textarea
                        id="debtor_comment"
                        name="debtor_comment"
                        rows="2"
                        x-model="debtorComment"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                        placeholder="Когда вернёт, договорённости…"
                    ></textarea>
                </div>
            </div>

            {{-- DOM: сначала «Провести» — по Enter открывается чек; на sm — row-reverse, справа основная кнопка --}}
            <div class="flex flex-col gap-3 border-t border-emerald-200/60 bg-gradient-to-b from-white to-emerald-50/30 px-4 py-5 sm:flex-row-reverse sm:flex-wrap sm:items-center sm:justify-end sm:gap-3 sm:px-6">
                <button
                    type="submit"
                    name="checkout_action"
                    value="with_receipt"
                    class="inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-8 py-3.5 text-[15px] font-bold text-white shadow-lg transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 sm:w-auto sm:min-w-[14rem]"
                >
                    Провести продажу
                </button>
                <button
                    type="submit"
                    name="checkout_action"
                    value="without_receipt"
                    class="inline-flex w-full items-center justify-center rounded-xl border-2 border-slate-200 bg-white px-6 py-3.5 text-[15px] font-bold text-slate-800 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50/80 hover:text-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-400/40 sm:w-auto sm:min-w-[12rem]"
                >
                    Сохранить без чека
                </button>
            </div>
        </form>
    </div>
</x-admin-layout>
