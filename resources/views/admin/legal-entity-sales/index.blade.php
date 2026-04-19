<x-admin-layout pageTitle="Продажа юрлицам — покупатели" main-class="px-3 py-5 sm:px-4 lg:px-5">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-10">
        @if (session('status'))
            <div class="rounded-lg border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 shadow-sm">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->has('delete'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                {{ $errors->first('delete') }}
            </div>
        @endif

        @if ($warehouses->isEmpty())
            <div class="rounded-xl border border-amber-200/80 bg-amber-50 px-5 py-4 text-sm text-amber-950 shadow-sm">
                <p class="font-medium">Сначала заведите хотя бы один склад.</p>
                <p class="mt-2 text-amber-900/90">
                    <a href="{{ route('admin.warehouses.create') }}" class="font-semibold text-emerald-800 underline hover:text-emerald-700">Добавить склад</a>
                </p>
            </div>
        @else
            <div
                class="flex flex-col"
                x-data="{
                    selectedSaleId: null,
                    lesBase: @js(rtrim(url('/admin/p/trade.sale-legal'), '/')),
                    printOrgId: @json($defaultPrintOrganizationId),
                }"
            >
                <div class="rounded-xl border border-slate-200/90 bg-white px-4 py-4 shadow-sm ring-1 ring-slate-900/5 sm:px-6">
                    <form
                        id="les-index-filter-form"
                        data-journal-filter-form
                        method="GET"
                        action="{{ route('admin.legal-entity-sales.index') }}"
                        class="space-y-4"
                    >
                        <input type="hidden" name="good_id" value="{{ ($filterGoodId ?? 0) > 0 ? (int) $filterGoodId : '' }}">
                        <div class="flex w-full min-w-0 flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-2">
                                <h2 class="text-lg font-semibold tracking-tight text-slate-900">Реализация покупателям (юрлица)</h2>
                                @if ($selectedWarehouseId !== 0)
                                    <a
                                        href="{{ route('admin.legal-entity-sales.create', ['warehouse_id' => $selectedWarehouseId]) }}"
                                        class="inline-flex shrink-0 items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 shadow-sm transition hover:border-slate-400 hover:bg-slate-50"
                                    >
                                        <span class="text-sm leading-none text-slate-500">+</span>
                                        Создать
                                    </a>
                                @else
                                    <span class="text-xs text-slate-500">Выберите склад, чтобы создать документ.</span>
                                @endif
                            </div>

                            <div class="flex w-full min-w-0 shrink-0 lg:w-auto lg:max-w-md lg:justify-end">
                                <div class="flex w-full min-w-0 items-center gap-3 rounded-lg border border-slate-200 bg-slate-50/90 px-3 py-1.5 sm:inline-flex sm:w-auto sm:py-2">
                                    <label for="les_warehouse" class="shrink-0 text-xs font-medium text-slate-600">Склад</label>
                                    <select
                                        id="les_warehouse"
                                        name="warehouse_id"
                                        class="min-w-0 flex-1 cursor-pointer rounded-md border border-slate-200 bg-white py-1.5 pl-2.5 pr-8 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/25 sm:min-w-[14rem] sm:flex-initial"
                                        onchange="this.form.submit()"
                                    >
                                        @foreach ($warehouses as $w)
                                            <option value="{{ $w->id }}" @selected((int) $w->id === (int) $selectedWarehouseId)>{{ $w->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        @include('admin.partials.journal-good-filter', [
                            'formSelector' => '#les-index-filter-form',
                            'goodsSearchUrl' => route('admin.goods.search'),
                            'warehouseId' => $selectedWarehouseId,
                            'filterGoodId' => (int) ($filterGoodId ?? 0),
                            'filterGoodSummary' => $filterGoodSummary ?? '',
                            'returnsUrl' => route('admin.customer-returns.index'),
                            'boxed' => false,
                        ])
                    </form>
                </div>

                <div class="mt-10 overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/5">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50/80 px-5 py-3.5 sm:px-6">
                        <h3 class="text-sm font-semibold tracking-tight text-slate-900">Журнал реализаций</h3>
                        @if (! $recentSales->isEmpty())
                            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                                @if ($organizations->isNotEmpty())
                                    <div class="flex w-full min-w-0 flex-wrap items-center gap-2 sm:w-auto">
                                        <label for="les_print_org" class="shrink-0 text-[11px] font-medium text-slate-600">Организация в шапке</label>
                                        <select
                                            id="les_print_org"
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
                                    :disabled="!selectedSaleId"
                                    @click="selectedSaleId && window.open(lesBase + '/' + selectedSaleId + '/print' + (printOrgId != null && printOrgId !== '' ? '?organization_id=' + printOrgId : ''), '_blank')"
                                >
                                    Печать накладной
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!selectedSaleId"
                                    @click="selectedSaleId && (window.location.href = lesBase + '/' + selectedSaleId + '/pdf' + (printOrgId != null && printOrgId !== '' ? '?organization_id=' + printOrgId : ''))"
                                >
                                    Скачать PDF
                                </button>
                            </div>
                        @endif
                    </div>
                    @if ($recentSales->isEmpty())
                        <div class="px-5 py-12 text-center sm:px-6">
                            <p class="text-sm text-slate-600">Пока нет документов.</p>
                            @if ($selectedWarehouseId !== 0)
                                <p class="mt-4">
                                    <a
                                        href="{{ route('admin.legal-entity-sales.create', ['warehouse_id' => $selectedWarehouseId]) }}"
                                        class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                                    >Создать реализацию</a>
                                </p>
                            @endif
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
                                        <th scope="col" class="border border-slate-300 px-2 py-2.5 text-center text-[11px] font-semibold uppercase tracking-wide text-slate-700" title="Счёт на оплату отправлен">Счёт</th>
                                        <th scope="col" class="border border-slate-300 px-3 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-700">Строк</th>
                                        <th scope="col" class="border border-slate-300 px-2 py-2.5 text-center text-[11px] font-semibold uppercase tracking-wide text-slate-700"> </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white">
                                    @foreach ($recentSales as $r)
                                        <tr
                                            class="cursor-pointer transition-colors hover:bg-emerald-50/80"
                                            role="link"
                                            tabindex="0"
                                            data-edit-url="{{ route('admin.legal-entity-sales.edit', $r) }}"
                                            onclick="if (!event.target.closest('td[data-les-sel]') && !event.target.closest('td[data-doc-action]')) { window.location.href=this.dataset.editUrl }"
                                            onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();window.location.href=this.dataset.editUrl}"
                                        >
                                            <td data-les-sel class="border border-slate-300 px-2 py-2 text-center" @click.stop>
                                                <input
                                                    type="radio"
                                                    name="les_journal_pick"
                                                    value="{{ $r->id }}"
                                                    class="h-4 w-4 cursor-pointer border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                                    x-model="selectedSaleId"
                                                />
                                            </td>
                                            <td class="border border-slate-300 whitespace-nowrap px-3 py-2.5 text-slate-900">{{ $r->document_date->format('d.m.Y') }}</td>
                                            <td class="border border-slate-300 px-3 py-2.5 text-slate-900">{{ $r->warehouse->name }}</td>
                                            <td class="border border-slate-300 px-3 py-2.5 text-slate-800">{{ $r->buyer_name !== '' ? $r->buyer_name : '—' }}</td>
                                            <td class="border border-slate-300 px-2 py-2.5 text-center text-xs text-slate-700">
                                                @if ($r->payment_invoice_sent)
                                                    <span class="rounded bg-sky-100 px-1.5 py-0.5 font-medium text-sky-900">отпр.</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="border border-slate-300 whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-slate-900">{{ $r->lines->count() }}</td>
                                            <td data-doc-action class="border border-slate-300 px-2 py-2 text-center" @click.stop>
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.legal-entity-sales.destroy', $r) }}"
                                                    class="inline"
                                                    onsubmit="return confirm('Удалить реализацию от {{ $r->document_date->format('d.m.Y') }}? Остатки будут восстановлены.');"
                                                >
                                                    @csrf
                                                    @method('DELETE')
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
