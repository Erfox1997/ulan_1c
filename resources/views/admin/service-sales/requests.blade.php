@php
    $fmtMoney = static function ($v): string {
        if ($v === null || $v === '') {
            return '—';
        }

        return number_format((float) $v, 2, ',', ' ');
    };
    $filterLabels = [
        'awaiting' => 'Ждут оформления',
        'fulfilled' => 'Оформлены',
        'all' => 'Все',
    ];
@endphp
<x-admin-layout pageTitle="Заявки на продажу" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg border border-emerald-200/80 bg-emerald-50 px-3 py-2.5 text-sm font-medium text-emerald-900 shadow-sm">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->has('fulfill'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-900">
                {{ $errors->first('fulfill') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-end">
            <div class="inline-flex rounded-xl border border-slate-200/90 bg-white p-1 shadow-sm ring-1 ring-slate-900/[0.04]">
                @foreach (['awaiting', 'fulfilled', 'all'] as $key)
                    <a
                        href="{{ route('admin.service-sales.requests', ['status' => $key]) }}"
                        class="rounded-lg px-3 py-2 text-xs font-bold transition sm:px-4 sm:text-sm {{ $statusFilter === $key
                            ? 'bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-md shadow-emerald-900/15'
                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
                    >{{ $filterLabels[$key] }}</a>
                @endforeach
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
            <div
                class="border-b border-emerald-900/10 px-4 py-3 text-white sm:px-5"
                style="background: linear-gradient(120deg, #047857 0%, #0d9488 45%, #0f766e 100%);"
            >
                <h2 class="text-sm font-bold tracking-tight">Список заявок</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                            <th class="whitespace-nowrap px-4 py-2.5">№</th>
                            <th class="min-w-[7rem] px-4 py-2.5">Клиент</th>
                            <th class="whitespace-nowrap px-4 py-2.5">Марка авто</th>
                            <th class="whitespace-nowrap px-4 py-2.5">Дата</th>
                            <th class="min-w-[6rem] px-4 py-2.5">Склад</th>
                            <th class="whitespace-nowrap px-4 py-2.5 text-right">Сумма</th>
                            <th class="whitespace-nowrap px-4 py-2.5">Статус</th>
                            <th class="whitespace-nowrap px-4 py-2.5 text-right">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($orders as $order)
                            <tr class="transition-colors hover:bg-emerald-50/40">
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold text-slate-600">#{{ $order->id }}</td>
                                <td class="max-w-[12rem] px-4 py-3">
                                    <span class="font-medium text-slate-900" title="{{ $order->recipientKindLabel() }}">{{ $order->clientDisplayLabel() }}</span>
                                </td>
                                <td class="max-w-[9rem] truncate px-4 py-3 text-slate-700" title="{{ $order->customerVehicle?->vehicle_brand ?? '' }}">
                                    {{ $order->customerVehicle?->vehicle_brand ? $order->customerVehicle->vehicle_brand : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-slate-700">{{ $order->document_date?->format('d.m.Y') ?? '—' }}</td>
                                <td class="max-w-[10rem] truncate px-4 py-3 text-slate-600" title="{{ $order->warehouse?->name ?? '' }}">{{ $order->warehouse?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <span class="font-semibold tabular-nums text-slate-900">{{ $fmtMoney($order->total_amount) }}</span>
                                    <span class="text-xs font-medium text-slate-400"> сом</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($order->status === \App\Models\ServiceOrder::STATUS_FULFILLED)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-900 ring-1 ring-emerald-200/80">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                            Оформлена
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-950 ring-1 ring-amber-200/80">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                                            Ждёт
                                        </span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    @if ($order->isAwaitingFulfillment())
                                        <form
                                            id="service-order-destroy-{{ $order->id }}"
                                            method="POST"
                                            action="{{ route('admin.service-sales.requests.destroy', $order) }}"
                                            class="hidden"
                                        >
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    @endif
                                    <select
                                        class="service-order-actions-select ml-auto block w-full max-w-[11rem] cursor-pointer rounded-lg border border-slate-200 bg-white py-1.5 pl-2 pr-7 text-left text-xs font-semibold text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                        data-order-id="{{ $order->id }}"
                                        aria-label="Действия по заявке №{{ $order->id }}"
                                    >
                                        <option value="" selected>Действия…</option>
                                        <option value="print" data-url="{{ route('admin.service-sales.requests.print', $order) }}">Печать</option>
                                        @if ($order->isAwaitingFulfillment())
                                            <option value="edit" data-url="{{ route('admin.service-sales.requests.edit', $order) }}">Изменить данные</option>
                                            @if ($mayAccessRoute('admin.service-sales.requests.lines') || $mayAccessRoute('admin.service-sales.sell.lines'))
                                                <option
                                                    value="lines"
                                                    data-url="{{ route($mayAccessRoute('admin.service-sales.requests.lines') ? 'admin.service-sales.requests.lines' : 'admin.service-sales.sell.lines', $order) }}"
                                                >Изменить позиции</option>
                                            @endif
                                            <option value="fulfill" data-url="{{ route('admin.service-sales.requests.show', $order) }}">Оформить</option>
                                            <option value="delete">Удалить</option>
                                        @endif
                                    </select>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-14 text-center">
                                    <p class="text-sm font-medium text-slate-600">Нет заявок в этом разделе</p>
                                    <p class="mt-1 text-xs text-slate-400">Смените фильтр выше или создайте заявку на странице продажи услуг</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        (function () {
            document.querySelectorAll('.service-order-actions-select').forEach(function (sel) {
                sel.addEventListener('change', function () {
                    var v = sel.value;
                    var opt = sel.options[sel.selectedIndex];
                    sel.selectedIndex = 0;
                    if (!v) {
                        return;
                    }
                    if (v === 'delete') {
                        var id = sel.getAttribute('data-order-id');
                        var form = document.getElementById('service-order-destroy-' + id);
                        if (form && confirm('Удалить заявку №' + id + '?')) {
                            form.submit();
                        }
                        return;
                    }
                    var url = opt.getAttribute('data-url');
                    if (!url) {
                        return;
                    }
                    if (v === 'print') {
                        window.open(url, '_blank', 'noopener,noreferrer');
                    } else {
                        window.location.href = url;
                    }
                });
            });
        })();
    </script>
</x-admin-layout>
