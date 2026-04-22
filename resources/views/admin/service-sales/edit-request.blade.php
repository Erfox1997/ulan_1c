<x-admin-layout pageTitle="Заявка №{{ $serviceOrder->id }} — редактирование" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-lg space-y-3">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            @if ($mayAccessRoute('admin.service-sales.requests'))
                <a href="{{ route('admin.service-sales.requests') }}" class="font-semibold text-emerald-700 hover:underline">← К заявкам</a>
            @else
                <a href="{{ route('admin.service-sales.sell') }}" class="font-semibold text-emerald-700 hover:underline">← Заявка на продажу</a>
            @endif
            @if ($mayAccessRoute('admin.service-sales.requests.lines') || $mayAccessRoute('admin.service-sales.sell.lines'))
                <span class="text-slate-300" aria-hidden="true">·</span>
                <a
                    href="{{ route($mayAccessRoute('admin.service-sales.requests.lines') ? 'admin.service-sales.requests.lines' : 'admin.service-sales.sell.lines', $serviceOrder) }}"
                    class="font-semibold text-emerald-700 hover:underline"
                >Позиции</a>
            @endif
            @if ($mayAccessRoute('admin.service-sales.requests.show'))
                <span class="text-slate-300" aria-hidden="true">·</span>
                <a href="{{ route('admin.service-sales.requests.show', $serviceOrder) }}" class="font-semibold text-slate-600 hover:text-emerald-700 hover:underline">Оформление</a>
            @endif
        </div>

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
            @php
                $initialCp = $initialCounterparty;
            @endphp
            <script>
                window.__serviceOrderHeaderInit = {
                    counterpartySearchUrl: @json($counterpartySearchUrl),
                    counterpartyQuickUrl: @json($counterpartyQuickUrl),
                    customerVehiclesIndexUrl: @json($customerVehiclesIndexUrl),
                    customerVehiclesStoreUrl: @json($customerVehiclesStoreUrl),
                    csrf: @json(csrf_token()),
                    masters: @json($masters->map(fn ($e) => ['id' => $e->id, 'full_name' => $e->full_name])->values()),
                    initialCounterparty: @json($initialCp),
                    initialVehicleId: @json($serviceOrder->customer_vehicle_id),
                    warehouseId: {{ (int) $selectedWarehouseId }},
                };
            </script>
            <div x-data="serviceOrderHeaderForm()" class="space-y-3">
                <form
                    method="POST"
                    action="{{ route('admin.service-sales.requests.update', $serviceOrder) }}"
                    class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]"
                >
                    @csrf
                    @method('PUT')
                    <div
                        class="border-b border-emerald-900/15 px-3 py-2.5 text-white sm:px-4"
                        style="background: linear-gradient(125deg, #047857 0%, #0d9488 55%, #115e59 100%);"
                    >
                        <label for="svc_edit_wh" class="block text-[10px] font-bold uppercase tracking-wide text-teal-100/95">Склад *</label>
                        <select
                            id="svc_edit_wh"
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
                        'order' => $serviceOrder,
                        'defaultDocumentDate' => $defaultDocumentDate,
                    ])
                    <div class="border-t border-slate-100 bg-slate-50/80 px-3 py-3 sm:px-4">
                        <button
                            type="submit"
                            class="w-full rounded-lg bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-3 text-sm font-bold text-white shadow-md transition hover:from-emerald-500 hover:to-teal-500"
                        >
                            Сохранить шапку и перейти к позициям
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-admin-layout>
