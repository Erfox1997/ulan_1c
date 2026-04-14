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
                            <th class="whitespace-nowrap px-4 py-2.5">Для кого</th>
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
                                <td class="px-4 py-3">
                                    <span class="font-medium text-slate-900">{{ $order->recipientKindLabel() }}</span>
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
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            <a
                                                href="{{ route('admin.service-sales.requests.edit', $order) }}"
                                                class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 shadow-sm transition hover:bg-slate-50"
                                            >Изменить</a>
                                            <a
                                                href="{{ route('admin.service-sales.requests.show', $order) }}"
                                                class="inline-flex items-center rounded-lg bg-gradient-to-r from-emerald-600 to-teal-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition hover:from-emerald-500 hover:to-teal-500"
                                            >Подробнее / оформить</a>
                                            <form
                                                method="POST"
                                                action="{{ route('admin.service-sales.requests.destroy', $order) }}"
                                                class="inline"
                                                onsubmit="return confirm('Удалить заявку №{{ $order->id }}?');"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center rounded-lg border border-red-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50"
                                                >
                                                    Удалить
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-14 text-center">
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
</x-admin-layout>
