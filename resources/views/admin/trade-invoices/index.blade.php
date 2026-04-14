<x-admin-layout pageTitle="Счёт на оплату покупателю" main-class="px-3 py-5 sm:px-4 lg:px-5">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-10">
        @if (session('status'))
            <div class="rounded-lg border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 shadow-sm">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->has('sale_ids'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->get('sale_ids') as $msg)
                        <li>{{ $msg }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if ($errors->has('delete'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                {{ $errors->first('delete') }}
            </div>
        @endif

        @if ($warehouses->isEmpty())
            <div class="rounded-xl border border-amber-200/80 bg-amber-50 px-5 py-4 text-sm text-amber-950 shadow-sm">
                <p class="font-medium">Сначала заведите хотя бы один склад и проведите реализацию юрлицу.</p>
                <p class="mt-2 text-amber-900/90">
                    <a href="{{ route('admin.warehouses.create') }}" class="font-semibold text-emerald-800 underline hover:text-emerald-700">Склады</a>
                    ·
                    <a href="{{ route('admin.legal-entity-sales.index') }}" class="font-semibold text-emerald-800 underline hover:text-emerald-700">Журнал реализаций</a>
                </p>
            </div>
        @else
            @php
                $tradeInvoiceJournalInit = [
                    'invoiceBase' => rtrim(url('/admin/p/trade.invoice'), '/'),
                    'mergedPrint' => url('/admin/p/trade.invoice/merged/print'),
                    'mergedPdf' => url('/admin/p/trade.invoice/merged/pdf'),
                    'printOrgId' => $defaultPrintOrganizationId,
                ];
            @endphp
            <script>
                window.__tradeInvoiceCpInit = {
                    searchUrl: @json(route('admin.counterparties.search', ['for' => 'sale'])),
                    counterpartyId: {{ (int) $selectedCounterpartyId }},
                    counterpartyLabel: @json($selectedCounterpartyLabel),
                };
                window.__tradeInvoiceJournalInit = @json($tradeInvoiceJournalInit);
            </script>
            @php
                $tiListQuery = array_filter([
                    'warehouse_id' => $selectedWarehouseId > 0 ? $selectedWarehouseId : null,
                    'counterparty_id' => $selectedCounterpartyId > 0 ? $selectedCounterpartyId : null,
                ], static fn ($v) => $v !== null && $v > 0);
            @endphp
            <div class="flex w-full max-w-6xl flex-wrap items-stretch gap-2 sm:inline-flex sm:items-center">
                <a
                    href="{{ route('admin.trade-invoices.index', $tiListQuery) }}"
                    class="inline-flex min-h-[42px] flex-1 items-center justify-center rounded-xl border px-4 py-2.5 text-center text-sm font-semibold shadow-sm transition sm:flex-initial sm:min-w-[10.5rem]
                        {{ $invoiceSentTab === 'pending'
                            ? 'border-emerald-600 bg-emerald-600 text-white ring-1 ring-emerald-600/90'
                            : 'border-slate-300 bg-white text-slate-800 hover:border-slate-400 hover:bg-slate-50' }}"
                >Отправить надо</a>
                <a
                    href="{{ route('admin.trade-invoices.index', array_merge($tiListQuery, ['sent' => 1])) }}"
                    class="inline-flex min-h-[42px] flex-1 items-center justify-center rounded-xl border px-4 py-2.5 text-center text-sm font-semibold shadow-sm transition sm:flex-initial sm:min-w-[10.5rem]
                        {{ $invoiceSentTab === 'sent'
                            ? 'border-emerald-600 bg-emerald-600 text-white ring-1 ring-emerald-600/90'
                            : 'border-slate-300 bg-white text-slate-800 hover:border-slate-400 hover:bg-slate-50' }}"
                >Отправленные</a>
            </div>
            <form
                method="GET"
                action="{{ route('admin.trade-invoices.index') }}"
                class="rounded-xl border border-slate-200/90 bg-white px-4 py-4 shadow-sm ring-1 ring-slate-900/5 sm:px-6"
                x-data="tradeInvoiceCpFilter()"
                @keydown.escape.window="items = []; noHits = false"
            >
                @if ($invoiceSentTab === 'sent')
                    <input type="hidden" name="sent" value="1" />
                @endif
                <input type="hidden" name="counterparty_id" :value="counterpartyId" />
                <div class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end">
                    <div class="min-w-0 lg:max-w-xs">
                        <label for="ti_wh" class="block text-xs font-medium text-slate-600">Склад</label>
                        <select
                            id="ti_wh"
                            name="warehouse_id"
                            class="mt-1 block w-full rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-10 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                            onchange="this.form.submit()"
                        >
                            @foreach ($warehouses as $w)
                                <option value="{{ $w->id }}" @selected((int) $w->id === (int) $selectedWarehouseId)>{{ $w->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0 flex-1 lg:max-w-lg">
                        <label for="ti_cp" class="block text-xs font-medium text-slate-600">Контрагент</label>
                        <div class="relative mt-1">
                            <div class="flex flex-wrap items-stretch gap-2">
                                <input
                                    id="ti_cp"
                                    type="text"
                                    x-model="query"
                                    autocomplete="off"
                                    placeholder="Введите от 2 букв и выберите из списка"
                                    class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-3 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                    @focus="onCpFocus($event)"
                                    @input="onCpInput($event)"
                                    @blur="onCpBlur()"
                                />
                                <button
                                    type="button"
                                    class="shrink-0 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-100"
                                    @mousedown.prevent
                                    @click="clearCpFilter()"
                                >
                                    Все
                                </button>
                            </div>
                            <div
                                x-cloak
                                x-show="showCpDropdown()"
                                class="fixed z-[100] max-h-72 overflow-y-auto rounded-lg border border-slate-300 bg-white py-1 text-left shadow-lg"
                                role="listbox"
                                :style="'top:' + cpPos.top + 'px;left:' + cpPos.left + 'px;width:' + cpPos.width + 'px'"
                                @mousedown.prevent
                            >
                                <div x-show="cpLoading" class="px-3 py-2 text-xs text-slate-500">Поиск…</div>
                                <template x-for="item in cpItems" :key="item.id">
                                    <button
                                        type="button"
                                        class="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left text-xs hover:bg-slate-100"
                                        @click="pickCounterparty(item)"
                                    >
                                        <span class="text-slate-900" x-text="item.full_name || item.name"></span>
                                        <span class="text-[10px] text-slate-500" x-show="item.kind === 'buyer'">Покупатель</span>
                                    </button>
                                </template>
                                <div
                                    x-show="!cpLoading && cpNoHits && cpItems.length === 0"
                                    class="px-3 py-2 text-xs text-slate-500"
                                >
                                    Нет совпадений
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <div class="flex flex-col" x-data="tradeInvoiceJournal">
                <form
                    x-ref="bulkForm"
                    method="POST"
                    action="{{ route('admin.trade-invoices.bulk-update-sent') }}"
                    class="hidden"
                    aria-hidden="true"
                >
                    @csrf
                    <input type="hidden" name="payment_invoice_sent" value="1">
                    <input type="hidden" name="return_warehouse_id" value="{{ $selectedWarehouseId }}">
                    <input type="hidden" name="return_counterparty_id" value="{{ $selectedCounterpartyId }}">
                    <input type="hidden" name="return_sent" value="{{ $invoiceSentTab === 'sent' ? '1' : '0' }}">
                </form>
                <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/5">
                    @if ($sales->isNotEmpty())
                    <div class="flex flex-wrap items-center justify-end gap-3 border-b border-slate-200 bg-slate-50/80 px-5 py-3.5 sm:px-6">
                            <div class="flex w-full min-w-0 flex-col gap-2 sm:w-auto sm:max-w-full sm:items-end">
                                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-900 shadow-sm transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-50"
                                        title="Отметить выбранные продажи: счёт на оплату отправлен клиенту"
                                        :disabled="selectedIds.length === 0"
                                        @click="bulkSubmit(true)"
                                    >
                                        Счёт отправлен
                                    </button>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                                @if ($organizations->isNotEmpty())
                                    <div class="flex w-full min-w-0 flex-wrap items-center gap-2 sm:w-auto">
                                        <label for="ti_print_org" class="shrink-0 text-[11px] font-medium text-slate-600">Организация в шапке</label>
                                        <select
                                            id="ti_print_org"
                                            x-model="printOrgId"
                                            class="min-w-0 max-w-[12rem] cursor-pointer rounded-md border border-slate-200 bg-white py-1 pl-2 pr-7 text-xs text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/25 sm:max-w-[16rem]"
                                        >
                                            @foreach ($organizations as $o)
                                                <option value="{{ $o->id }}">{{ $o->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                    title="Одна реализация — обычный счёт; несколько — объединённый (один покупатель)"
                                    :disabled="selectedIds.length === 0"
                                    @click="openPrint()"
                                >
                                    Печать счёта
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                    title="Одна реализация — один PDF; несколько — объединённый счёт (один покупатель)"
                                    :disabled="selectedIds.length === 0"
                                    @click="openPdf()"
                                >
                                    Скачать PDF
                                </button>
                                </div>
                            </div>
                    </div>
                    @endif

                    @if ($sales->isEmpty())
                        <div class="px-5 py-12 text-center sm:px-6">
                            <p class="text-sm text-slate-600">Нет продаж по выбранным условиям.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto p-4 sm:p-5 sm:pt-3">
                            <table class="w-full border-collapse border border-slate-300 text-sm">
                                <thead>
                                    <tr class="bg-slate-100">
                                        <th scope="col" class="border border-slate-300 px-2 py-2.5 text-center text-[11px] font-semibold uppercase tracking-wide text-slate-700">Выбор</th>
                                        <th scope="col" class="border border-slate-300 px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-700">Дата</th>
                                        <th scope="col" class="border border-slate-300 px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-700">Склад</th>
                                        <th scope="col" class="border border-slate-300 px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-700">Покупатель</th>
                                        <th scope="col" class="border border-slate-300 px-3 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-700">Строк</th>
                                        <th scope="col" class="border border-slate-300 px-3 py-2.5 text-center text-[11px] font-semibold uppercase tracking-wide text-slate-700">Счёт отправлен</th>
                                        <th scope="col" class="border border-slate-300 px-2 py-2.5 text-center text-[11px] font-semibold uppercase tracking-wide text-slate-700"> </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white">
                                    @foreach ($sales as $sale)
                                        <tr class="transition-colors hover:bg-slate-50/90">
                                            <td class="border border-slate-300 px-2 py-2 text-center" @click.stop>
                                                <input
                                                    type="checkbox"
                                                    value="{{ $sale->id }}"
                                                    class="h-4 w-4 cursor-pointer rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                                    x-model="selectedIds"
                                                />
                                            </td>
                                            <td class="border border-slate-300 whitespace-nowrap px-3 py-2.5 text-slate-900">{{ $sale->document_date->format('d.m.Y') }}</td>
                                            <td class="border border-slate-300 px-3 py-2.5 text-slate-900">{{ $sale->warehouse->name }}</td>
                                            <td class="border border-slate-300 px-3 py-2.5 text-slate-800">{{ $sale->buyer_name !== '' ? $sale->buyer_name : '—' }}</td>
                                            <td class="border border-slate-300 whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-slate-900">{{ $sale->lines->count() }}</td>
                                            <td class="border border-slate-300 px-2 py-2 text-center" @click.stop>
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.trade-invoices.update-sent', $sale) }}"
                                                    class="inline-flex justify-center"
                                                >
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="return_warehouse_id" value="{{ $selectedWarehouseId }}">
                                                    <input type="hidden" name="return_counterparty_id" value="{{ $selectedCounterpartyId }}">
                                                    <input type="hidden" name="return_sent" value="{{ $invoiceSentTab === 'sent' ? '1' : '0' }}">
                                                    <input type="hidden" name="payment_invoice_sent" value="0">
                                                    <label class="inline-flex cursor-pointer items-center gap-1.5 text-[11px] text-slate-700" title="Отметка: счёт на оплату по этой продаже отправлен клиенту">
                                                        <input
                                                            type="checkbox"
                                                            name="payment_invoice_sent"
                                                            value="1"
                                                            class="h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                                            @checked($sale->payment_invoice_sent)
                                                            onchange="this.form.submit()"
                                                        />
                                                        <span class="hidden sm:inline">Отправлен</span>
                                                    </label>
                                                </form>
                                            </td>
                                            <td class="border border-slate-300 px-2 py-2 text-center" @click.stop>
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.legal-entity-sales.destroy', $sale) }}"
                                                    class="inline"
                                                    onsubmit="return confirm('Удалить реализацию от {{ $sale->document_date->format('d.m.Y') }}? Остатки будут восстановлены.');"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="return_to" value="trade-invoices">
                                                    <input type="hidden" name="return_warehouse_id" value="{{ $selectedWarehouseId }}">
                                                    <input type="hidden" name="return_counterparty_id" value="{{ $selectedCounterpartyId }}">
                                                    <input type="hidden" name="return_sent" value="{{ $invoiceSentTab === 'sent' ? '1' : '0' }}">
                                                    <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Удалить</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-admin-layout>
