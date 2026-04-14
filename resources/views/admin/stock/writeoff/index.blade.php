<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-5xl space-y-4">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200/80 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" action="{{ route('admin.stock.writeoff') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">Склад</label>
                    <select name="warehouse_id" class="rounded-lg border border-slate-200 bg-white py-2 pl-2.5 pr-8 text-sm" onchange="this.form.submit()">
                        @foreach ($warehouses as $w)
                            <option value="{{ $w->id }}" @selected((int) $w->id === (int) $selectedWarehouseId)>{{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
            <a href="{{ route('admin.stock.writeoff.create', ['warehouse_id' => $selectedWarehouseId]) }}" class="inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Новое списание</a>
        </div>

        @if ($warehouses->isEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm"><a href="{{ route('admin.warehouses.create') }}" class="font-semibold text-indigo-800 underline">Создайте склад</a></div>
        @else
            <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-4 py-3">
                    <h2 class="text-sm font-bold text-slate-900">Журнал списаний</h2>
                    <p class="text-xs text-slate-500">Недостача, порча — списание со склада. Нажмите на строку, чтобы открыть и изменить документ.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-2">Дата</th>
                                <th class="px-4 py-2">Склад</th>
                                <th class="px-4 py-2">Позиций</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($documents as $d)
                                @php $editUrl = route('admin.stock.writeoff.edit', $d); @endphp
                                <tr class="transition-colors hover:bg-indigo-50/60">
                                    <td class="px-0 py-0">
                                        <a href="{{ $editUrl }}" class="block px-4 py-2.5 font-medium text-indigo-700 hover:underline">{{ $d->document_date->format('d.m.Y') }}</a>
                                    </td>
                                    <td class="px-0 py-0">
                                        <a href="{{ $editUrl }}" class="block px-4 py-2.5 text-slate-800 hover:text-indigo-800">{{ $d->warehouse?->name ?? '—' }}</a>
                                    </td>
                                    <td class="px-0 py-0">
                                        <a href="{{ $editUrl }}" class="block px-4 py-2.5 tabular-nums text-slate-800 hover:text-indigo-800">{{ $d->lines->count() }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-10 text-center text-sm text-slate-500">Документов пока нет.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-admin-layout>
