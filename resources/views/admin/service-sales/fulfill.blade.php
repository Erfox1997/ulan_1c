@php
    $cp = $serviceOrder->counterparty;
    $lf = $cp?->legal_form;
    $showRetail = $lf === null || $lf === \App\Models\Counterparty::LEGAL_INDIVIDUAL;
    $showLegal = in_array($lf, [\App\Models\Counterparty::LEGAL_IP, \App\Models\Counterparty::LEGAL_OSOO, \App\Models\Counterparty::LEGAL_OTHER], true);
@endphp
<x-admin-layout pageTitle="Оформление №{{ $serviceOrder->id }}" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-3xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->has('fulfill'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">
                {{ $errors->first('fulfill') }}
            </div>
        @endif

        @error('lines')
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">
                {{ $message }}
            </div>
        @enderror

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.service-sales.requests') }}" class="text-xs font-semibold text-emerald-800 hover:underline">← К заявкам</a>
            @if ($serviceOrder->isAwaitingFulfillment() && ($mayAccessRoute('admin.service-sales.requests.lines') || $mayAccessRoute('admin.service-sales.sell.lines')))
                <span class="text-slate-300" aria-hidden="true">·</span>
                <a
                    href="{{ route($mayAccessRoute('admin.service-sales.requests.lines') ? 'admin.service-sales.requests.lines' : 'admin.service-sales.sell.lines', $serviceOrder) }}"
                    class="text-xs font-semibold text-teal-800 hover:underline"
                >Позиции</a>
            @endif
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
            <div
                class="border-b border-emerald-900/10 px-4 py-3 text-white sm:px-5"
                style="background: linear-gradient(120deg, #047857 0%, #0d9488 50%, #0f766e 100%);"
            >
                <h1 class="text-sm font-bold tracking-tight">Заявка №{{ $serviceOrder->id }}</h1>
            </div>

            <div class="p-4 sm:p-5">
                @if ($showRetail)
                    <div class="space-y-4">
                        @if ($paymentAccountsPayload === [])
                            <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                                Нет счётов. <a href="{{ route('admin.organizations.index') }}" class="font-semibold underline">Организации</a>
                            </p>
                        @else
                            <form method="POST" action="{{ route('admin.service-sales.requests.fulfill-retail', $serviceOrder) }}" class="space-y-4">
                                @csrf
                                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                                    @include('admin.service-sales.partials.fulfill-lines', ['fulfillLinesPayload' => $fulfillLinesPayload])
                                </div>
                                <div>
                                    <label for="fulfill_retail_acc" class="mb-1 block text-xs font-semibold text-slate-700">Счёт / касса *</label>
                                    <select
                                        id="fulfill_retail_acc"
                                        name="organization_bank_account_id"
                                        required
                                        class="w-full rounded-lg border border-slate-200 bg-slate-50/80 py-2.5 pl-3 pr-8 text-sm font-medium text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                    >
                                        @foreach ($paymentAccountsPayload as $acc)
                                            <option value="{{ $acc['id'] }}" @selected((int) ($defaultAccountId ?? 0) === (int) $acc['id'])>
                                                {{ $acc['label'] }} — {{ $acc['organization'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="fulfill_retail_date" class="mb-1 block text-xs font-semibold text-slate-700">Дата документа *</label>
                                    <input
                                        id="fulfill_retail_date"
                                        type="date"
                                        name="document_date"
                                        value="{{ old('document_date', $defaultDocumentDate) }}"
                                        required
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                    />
                                </div>
                                <button type="submit" class="w-full rounded-lg bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-3 text-sm font-bold text-white shadow-md transition hover:from-emerald-500 hover:to-teal-500">
                                    Провести продажу
                                </button>
                            </form>
                        @endif
                    </div>
                @endif

                @if ($showRetail && $showLegal)
                    <hr class="my-6 border-slate-200" />
                @endif

                @if ($showLegal)
                    <script>
                        window.__serviceFulfillLegal = {
                            searchUrl: @json(route('admin.counterparties.search')),
                            quickUrl: @json(route('admin.counterparties.quick-store')),
                            csrf: @json(csrf_token()),
                            prefill: @json($legalCounterpartyPrefill),
                        };
                    </script>
                    <div class="space-y-4" x-data="serviceFulfillLegalCp()">
                        <form
                            method="POST"
                            action="{{ route('admin.service-sales.requests.fulfill-legal', $serviceOrder) }}"
                            class="space-y-4"
                            @submit="if (!counterpartyId) { $event.preventDefault(); }"
                        >
                            @csrf
                            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                                @include('admin.service-sales.partials.fulfill-lines', ['fulfillLinesPayload' => $fulfillLinesPayload])
                            </div>
                            <input type="hidden" name="counterparty_id" x-bind:value="counterpartyId" />
                            <div class="relative z-[100]">
                                <label for="fulfill_cp_search" class="mb-1 block text-xs font-semibold text-slate-700">Контрагент *</label>
                                <input
                                    id="fulfill_cp_search"
                                    type="search"
                                    x-model="query"
                                    @input.debounce.300ms="onInput()"
                                    @focus="if (query.trim().length >= 2) { onInput() }"
                                    @keydown.escape="open = false"
                                    autocomplete="off"
                                    placeholder="От 2 букв…"
                                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                />
                                <div
                                    x-cloak
                                    x-show="open && (loading || items.length > 0)"
                                    @click.outside="open = false"
                                    class="absolute left-0 right-0 z-[200] mt-1 max-h-52 overflow-y-auto rounded-lg border border-slate-200 bg-white py-0.5 shadow-2xl ring-1 ring-slate-900/10"
                                >
                                    <div x-show="loading" class="px-3 py-2 text-xs text-slate-500">Поиск…</div>
                                    <template x-for="item in items" :key="item.id">
                                        <button
                                            type="button"
                                            class="flex w-full flex-col items-start px-3 py-2.5 text-left text-sm transition hover:bg-emerald-50"
                                            @click="pick(item)"
                                        >
                                            <span class="font-medium text-slate-900" x-text="item.full_name || item.name"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            @error('counterparty_id')
                                <p class="text-xs text-red-600">{{ $message }}</p>
                            @enderror
                            <p>
                                <button type="button" class="text-xs font-semibold text-emerald-700 hover:underline" @click="modalOpen = true; quickError = ''">
                                    Новый покупатель
                                </button>
                            </p>
                            <div>
                                <label for="fulfill_legal_date" class="mb-1 block text-xs font-semibold text-slate-700">Дата документа *</label>
                                <input
                                    id="fulfill_legal_date"
                                    type="date"
                                    name="document_date"
                                    value="{{ old('document_date', $defaultDocumentDate) }}"
                                    required
                                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                />
                            </div>
                            <button
                                type="submit"
                                class="w-full rounded-lg bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-3 text-sm font-bold text-white shadow-md transition hover:from-emerald-500 hover:to-teal-500 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="!counterpartyId"
                            >
                                Провести продажу
                            </button>
                        </form>

                        <div
                            x-cloak
                            x-show="modalOpen"
                            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 p-4"
                            @keydown.escape.window="modalOpen = false"
                        >
                            <div
                                class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-4 shadow-xl"
                                @click.outside="modalOpen = false"
                            >
                                <h3 class="text-sm font-bold text-slate-900">Новый покупатель</h3>
                                <div class="mt-3 space-y-2">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700">Наименование *</label>
                                        <input
                                            type="text"
                                            x-model="quickName"
                                            class="mt-1 w-full rounded-lg border border-slate-200 px-2.5 py-2 text-sm text-slate-900"
                                            placeholder="Как в договоре"
                                        />
                                    </div>
                                    <div>
                                        <span class="block text-xs font-semibold text-slate-700">Форма</span>
                                        <div class="mt-1.5 flex flex-wrap gap-3">
                                            <label class="inline-flex items-center gap-2 text-sm text-slate-800">
                                                <input type="radio" x-model="quickForm" value="ip" class="text-emerald-600" />
                                                ИП
                                            </label>
                                            <label class="inline-flex items-center gap-2 text-sm text-slate-800">
                                                <input type="radio" x-model="quickForm" value="osoo" class="text-emerald-600" />
                                                ОсОО
                                            </label>
                                        </div>
                                    </div>
                                    <p x-show="quickError" class="text-sm text-red-600" x-text="quickError"></p>
                                </div>
                                <div class="mt-4 flex justify-end gap-2">
                                    <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="modalOpen = false">
                                        Отмена
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-500 disabled:opacity-50"
                                        :disabled="quickSaving"
                                        @click="quickSave()"
                                    >
                                        Сохранить
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-admin-layout>
