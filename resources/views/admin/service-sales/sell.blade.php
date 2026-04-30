<x-admin-layout pageTitle="Заявка на продажу" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-lg space-y-3">
        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($warehouses->isEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                Сначала добавьте склад в настройках.
                <a href="{{ route('admin.warehouses.create') }}" class="ml-2 font-semibold text-emerald-800 underline">Создать склад</a>
            </div>
        @else
            <script>
                window.__serviceOrderHeaderInit = {
                    counterpartySearchUrl: @json($counterpartySearchUrl),
                    counterpartyQuickUrl: @json($counterpartyQuickUrl),
                    customerVehiclesIndexUrl: @json($customerVehiclesIndexUrl),
                    customerVehiclesStoreUrl: @json($customerVehiclesStoreUrl),
                    vehicleHistoryUrlBase: @json($vehicleHistoryUrlBase),
                    csrf: @json(csrf_token()),
                    masters: @json($masters->map(fn ($e) => ['id' => $e->id, 'full_name' => $e->full_name])->values()),
                    initialCounterparty: null,
                    initialVehicleId: null,
                    warehouseId: {{ (int) $selectedWarehouseId }},
                };
            </script>
            <div x-data="serviceOrderHeaderForm()" class="space-y-3">
                <form
                    method="POST"
                    action="{{ route('admin.service-sales.sell.store') }}"
                    class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]"
                >
                    @csrf
                    <div
                        class="border-b border-emerald-900/15 px-3 py-2.5 text-white sm:px-4"
                        style="background: linear-gradient(125deg, #047857 0%, #0d9488 55%, #115e59 100%);"
                    >
                        <label for="svc_sell_wh" class="block text-[10px] font-bold uppercase tracking-wide text-teal-100/95">Склад *</label>
                        <select
                            id="svc_sell_wh"
                            name="warehouse_id"
                            required
                            class="mt-1 w-full rounded-lg border-0 bg-white/95 px-2.5 py-2 text-sm font-semibold text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-white/80"
                        >
                            @foreach ($warehouses as $w)
                                <option value="{{ $w->id }}" @selected((int) $w->id === (int) $selectedWarehouseId)>{{ $w->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @include('admin.service-sales.partials.sell-order-fields', [
                        'masters' => $masters,
                        'order' => null,
                        'defaultDocumentDate' => $defaultDocumentDate,
                    ])
                    <div class="border-t border-slate-100 bg-slate-50/80 px-3 py-3 sm:px-4">
                        <button
                            type="submit"
                            class="w-full rounded-lg bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-3 text-sm font-bold text-white shadow-md transition hover:from-emerald-500 hover:to-teal-500"
                        >
                            Далее: запчасти и услуги
                        </button>
                    </div>
                </form>
            </div>
        @endif

        @if ($recentPending->isNotEmpty() && $mayAccessRoute('admin.service-sales.requests'))
            <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-3 py-2">
                    <h2 class="text-xs font-bold uppercase tracking-wide text-slate-600">В очереди на оформление</h2>
                    <a href="{{ route('admin.service-sales.requests') }}" class="text-[11px] font-semibold text-emerald-700 hover:underline">Все заявки</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="border-b border-slate-100 bg-white text-left font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="whitespace-nowrap px-2.5 py-1.5">№</th>
                                <th class="whitespace-nowrap px-2 py-1.5">Кому</th>
                                <th class="whitespace-nowrap px-2 py-1.5">Дата</th>
                                <th class="whitespace-nowrap px-2 py-1.5">Статус</th>
                                <th class="whitespace-nowrap px-2 py-1.5 text-right">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach ($recentPending as $row)
                                <tr class="text-slate-800">
                                    <td class="whitespace-nowrap px-2.5 py-1.5 font-mono text-slate-600">{{ $row->id }}</td>
                                    <td class="max-w-[8rem] truncate px-2 py-1.5">{{ $row->recipientKindLabel() }}</td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-slate-600">{{ $row->document_date?->format('d.m.Y') ?? '—' }}</td>
                                    <td class="px-2 py-1.5">
                                        @if ($row->status === \App\Models\ServiceOrder::STATUS_FULFILLED)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-900 ring-1 ring-emerald-200/80">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                                Оформлена
                                            </span>
                                        @elseif ($row->status === \App\Models\ServiceOrder::STATUS_CANCELLED)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700 ring-1 ring-slate-200/80">
                                                Отменена
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-950 ring-1 ring-amber-200/80">
                                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                                                Ждёт
                                            </span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-2 py-1.5 text-right">
                                        @if ($mayAccessRoute('admin.service-sales.requests.edit'))
                                            <a
                                                href="{{ route('admin.service-sales.requests.edit', $row) }}"
                                                class="font-semibold text-slate-700 underline decoration-slate-400 underline-offset-2 hover:text-slate-900"
                                            >Изменить</a>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-admin-layout>
