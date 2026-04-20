<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-5xl space-y-4">
        @if (session('status'))
            <div class="rounded-xl border border-indigo-200/80 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ session('error') }}</div>
        @endif

        @php
            $draftCount = $documents->where('is_draft', true)->count();
        @endphp
        <form id="stock-audit-merge-form" method="POST" action="{{ route('admin.stock.audit.merge-drafts') }}" class="hidden">
            @csrf
            <input type="hidden" name="warehouse_id" value="{{ (int) $selectedWarehouseId }}" />
        </form>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" action="{{ route('admin.stock.audit') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">Склад</label>
                    <select
                        name="warehouse_id"
                        class="rounded-lg border border-slate-200 bg-white py-2 pl-2.5 pr-8 text-sm shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                        onchange="this.form.submit()"
                    >
                        @foreach ($warehouses as $w)
                            <option value="{{ $w->id }}" @selected((int) $w->id === (int) $selectedWarehouseId)>{{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
            <div class="flex flex-wrap items-center gap-2">
                @if ($draftCount >= 2)
                    <button
                        type="submit"
                        form="stock-audit-merge-form"
                        id="stock-audit-merge-submit"
                        class="inline-flex rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        disabled
                        onclick="return confirm('Объединить выбранные черновики в один? По каждому товару количества будут сложены. Исходные черновики будут удалены.')"
                    >
                        Объединить черновики
                    </button>
                @endif
                <a
                    href="{{ route('admin.stock.audit.create', ['warehouse_id' => $selectedWarehouseId]) }}"
                    class="inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Новая ревизия
                </a>
            </div>
        </div>

        @if ($warehouses->isEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950">
                <a href="{{ route('admin.warehouses.create') }}" class="font-semibold text-indigo-800 underline">Создайте склад</a>
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
                    <h2 class="text-sm font-bold text-slate-900">Журнал ревизий</h2>
                    <p class="text-xs text-slate-500">
                        Несколько человек могут вести свои черновики по одному складу; отметьте два и более черновика и нажмите «Объединить черновики» — позиции суммируются (например 5 + 3 = 8), получится один черновик для проведения ревизии. После проведения скачайте Excel: учёт, факт, разница и сумма по закупочной цене.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase text-slate-500">
                            <tr>
                                @if ($draftCount >= 2)
                                    <th class="w-10 px-2 py-2 text-center" title="Для объединения черновиков">✓</th>
                                @endif
                                <th class="px-4 py-2">Дата</th>
                                <th class="px-4 py-2">Склад</th>
                                <th class="px-4 py-2">Статус</th>
                                <th class="px-4 py-2">Позиций</th>
                                <th class="px-4 py-2 text-right">Excel</th>
                                <th class="w-28 px-4 py-2 text-right">Удалить</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($documents as $d)
                                @php
                                    $editUrl = $d->is_draft ? route('admin.stock.audit.edit', $d) : null;
                                @endphp
                                <tr
                                    class="transition-colors @if ($d->is_draft) cursor-pointer hover:bg-indigo-50/80 @else hover:bg-indigo-50/60 @endif"
                                    @if ($editUrl) onclick="window.location.href='{{ $editUrl }}'" @endif
                                >
                                    @if ($draftCount >= 2)
                                        <td class="px-2 py-2.5 text-center" onclick="event.stopPropagation()">
                                            @if ($d->is_draft)
                                                <input
                                                    type="checkbox"
                                                    form="stock-audit-merge-form"
                                                    name="draft_ids[]"
                                                    value="{{ $d->id }}"
                                                    class="stock-audit-merge-cb h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                />
                                            @endif
                                        </td>
                                    @endif
                                    <td class="whitespace-nowrap px-4 py-2.5 @if ($d->is_draft) font-medium text-indigo-800 @else text-slate-800 @endif">
                                        {{ $d->document_date->format('d.m.Y') }}
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-800">{{ $d->warehouse?->name ?? '—' }}</td>
                                    <td class="px-4 py-2.5">
                                        @if ($d->is_draft)
                                            <span class="inline-flex rounded-md bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-950">Черновик</span>
                                        @else
                                            <span class="text-xs text-slate-500">Проведено</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 tabular-nums text-slate-600">{{ $d->lines->count() }}</td>
                                    <td class="px-4 py-2.5 text-right" @if ($editUrl) onclick="event.stopPropagation()" @endif>
                                        @if ($d->is_draft)
                                            <span class="text-xs text-slate-400">После проведения</span>
                                        @else
                                            <a
                                                href="{{ route('admin.stock.audit.export', $d) }}"
                                                class="text-sm font-semibold text-indigo-700 hover:text-indigo-900 hover:underline"
                                            >
                                                Скачать .xlsx
                                            </a>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-right" onclick="event.stopPropagation()">
                                        <form
                                            method="POST"
                                            action="{{ route('admin.stock.audit.destroy', $d) }}"
                                            class="inline"
                                            onsubmit='return confirm({{ json_encode($d->is_draft ? 'Удалить черновик?' : 'Удалить проведённую ревизию? Остатки на складе будут откатаны по этому документу.') }})'
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="text-xs font-semibold text-rose-700 hover:text-rose-900 hover:underline"
                                            >
                                                Удалить
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $draftCount >= 2 ? 7 : 6 }}" class="px-4 py-10 text-center text-sm text-slate-500">Документов пока нет.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
    @if ($draftCount >= 2)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var btn = document.getElementById('stock-audit-merge-submit');
                if (!btn) return;
                // Чекбоксы с form="..." не входят в subtree <form> — querySelector по form их не видит.
                function sync() {
                    var n = document.querySelectorAll('input.stock-audit-merge-cb:checked').length;
                    btn.disabled = n < 2;
                }
                document.querySelectorAll('input.stock-audit-merge-cb').forEach(function (el) {
                    el.addEventListener('change', sync);
                });
                sync();
            });
        </script>
    @endif
</x-admin-layout>
