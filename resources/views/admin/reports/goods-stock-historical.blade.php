@php
    $fmtQty = static fn ($v) => number_format((float) $v, 2, ',', ' ');
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="px-3 py-6 sm:px-6 lg:px-8">
    @include('admin.partials.cp-brush')
    <div class="cp-root mx-auto w-full max-w-[min(100%,112rem)] space-y-6">
        @if ($warehouses->isEmpty())
            <div
                class="rounded-2xl border border-amber-200/90 bg-gradient-to-r from-amber-50 via-white to-orange-50/40 px-5 py-4 text-sm text-amber-950 shadow-sm ring-1 ring-amber-100/80"
            >
                <p class="font-semibold text-amber-950">Нет складов.</p>
                <p class="mt-2 text-amber-900/90">
                    <a
                        href="{{ route('admin.warehouses.create') }}"
                        class="font-semibold text-emerald-700 underline decoration-emerald-300 underline-offset-2 hover:text-emerald-800"
                    >Создать склад</a>
                </p>
            </div>
        @else
            @include('admin.partials.status-flash')

            <div
                class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50"
            >
                <form
                    method="GET"
                    action="{{ route('admin.reports.goods-stock-historical') }}"
                    class="rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90 px-4 py-4 sm:px-6 sm:py-5"
                >
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 sm:items-end">
                        <div>
                            <x-input-label for="r_gsh_wh" value="Склад" />
                            <select
                                id="r_gsh_wh"
                                name="warehouse_id"
                                class="mt-2 block w-full max-w-md rounded-xl border border-slate-200/90 bg-white py-2.5 pl-3 pr-10 text-sm text-slate-900 shadow-sm ring-1 ring-slate-900/5 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/25"
                                onchange="this.form.querySelector('input[name=page]').value='1'; this.form.submit();"
                            >
                                <option value="0" @selected($selectedWarehouseId === 0)>Все склады (сводно)</option>
                                @foreach ($warehouses as $w)
                                    <option value="{{ $w->id }}" @selected((int) $w->id === (int) $selectedWarehouseId)>{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="r_gsh_date" value="Остаток на конец дня" />
                            <input
                                id="r_gsh_date"
                                type="date"
                                name="as_of"
                                value="{{ $asOf->format('Y-m-d') }}"
                                class="mt-2 block w-full max-w-md rounded-xl border border-slate-200/90 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm ring-1 ring-slate-900/5 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/25"
                            />
                        </div>
                        <div>
                            <x-input-label for="r_gsh_q" value="Поиск" />
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <input
                                    id="r_gsh_q"
                                    type="search"
                                    name="q"
                                    value="{{ $searchQuery }}"
                                    placeholder="Артикул, наименование, штрихкод…"
                                    autocomplete="off"
                                    class="min-w-[12rem] flex-1 rounded-xl border border-slate-200/90 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm ring-1 ring-slate-900/5 placeholder:text-slate-400 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/25"
                                />
                                <button
                                    type="submit"
                                    class="inline-flex shrink-0 items-center justify-center rounded-xl border border-emerald-200/90 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-900 shadow-sm ring-1 ring-emerald-900/5 hover:bg-emerald-100/90"
                                >
                                    Показать
                                </button>
                                @if ($searchQuery !== '')
                                    <a
                                        href="{{ route('admin.reports.goods-stock-historical', array_filter(['warehouse_id' => $selectedWarehouseId, 'as_of' => $asOf->format('Y-m-d'), 'page' => 1])) }}"
                                        class="text-sm font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-slate-900"
                                    >Сбросить</a>
                                @endif
                            </div>
                        </div>
                    </div>
                    <p class="mt-4 text-[12px] leading-relaxed text-slate-600">
                        «На дату» — расчётный остаток на конец выбранного дня: текущий остаток минус все движения
                        с датой <span class="font-semibold text-slate-800">после</span> этой даты (как в учёте по проведённым документам).
                        «Сейчас» — текущий остаток в системе.
                    </p>
                    <input type="hidden" name="page" value="1" />
                </form>
            </div>

            <div
                class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50"
            >
                <div class="overflow-hidden rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90">
                    <div class="ob-1c-scope overflow-hidden rounded-[1.5rem] bg-white/95">
                        <style>
                            .ob-1c-scope {
                                font-family: Tahoma, 'Segoe UI', Arial, sans-serif;
                                font-size: 12px;
                                color: #0f172a;
                            }
                            .ob-1c-scope .ob-1c-table {
                                width: 100%;
                                border-collapse: collapse;
                                table-layout: auto;
                                background: #fff;
                            }
                            .ob-1c-scope .ob-1c-table th,
                            .ob-1c-scope .ob-1c-table td {
                                border: 1px solid rgb(226 232 240);
                                padding: 0;
                                vertical-align: middle;
                            }
                            .ob-1c-scope .ob-1c-table th {
                                background: linear-gradient(180deg, #ecfdf5 0%, #e0f2fe 100%);
                                font-weight: 700;
                                text-align: left;
                                padding: 6px 8px;
                                white-space: nowrap;
                                color: #0f766e;
                                font-size: 11px;
                                letter-spacing: 0.02em;
                            }
                            .ob-1c-scope .ob-1c-table th.ob-num,
                            .ob-1c-scope .ob-1c-table td.ob-num {
                                text-align: center;
                                width: 2.25rem;
                                color: #475569;
                            }
                            .ob-1c-scope .ob-1c-table td.ob-cell {
                                padding: 4px 8px;
                                font-size: 12px;
                            }
                            .ob-1c-scope .ob-1c-table td.ob-numr {
                                text-align: right;
                            }
                        </style>
                        <div
                            class="flex flex-wrap items-start justify-between gap-3 border-b border-emerald-200/55 bg-gradient-to-r from-emerald-50/95 via-white to-sky-50/50 px-4 py-3 sm:px-5"
                        >
                            <div>
                                <p class="mb-0.5 text-[10px] font-semibold uppercase tracking-wider text-teal-700/90">Отчёт</p>
                                <h2 class="text-[15px] font-bold leading-tight text-slate-800">{{ $pageTitle }}</h2>
                                <p class="mt-1 text-[11px] text-slate-600">
                                    На {{ $asOf->format('d.m.Y') }} и сейчас
                                </p>
                            </div>
                        </div>

                        @if ($rowsPaginator->lastPage() > 1)
                            <div class="border-b border-emerald-100/80 bg-slate-50/90 px-3 py-2 text-[11px] text-slate-700">
                                {{ $rowsPaginator->links() }}
                            </div>
                        @endif

                        <div class="overflow-x-auto border-t border-slate-200/90">
                            <table class="ob-1c-table">
                                <thead>
                                    <tr>
                                        <th class="ob-num">N</th>
                                        <th>Склад</th>
                                        <th>Наименование</th>
                                        <th>Ед. изм.</th>
                                        <th class="whitespace-nowrap">На дату</th>
                                        <th class="whitespace-nowrap">Сейчас</th>
                                        <th class="whitespace-nowrap">Изменение после даты</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($rowsPaginator as $i => $r)
                                        @php
                                            $qThen = (float) ($r['quantity_as_of'] ?? 0);
                                            $qNow = (float) ($r['quantity'] ?? 0);
                                            $mov = (float) ($r['movement_after_date'] ?? 0);
                                        @endphp
                                        <tr class="hover:bg-emerald-50/40">
                                            <td class="ob-cell ob-num">{{ $rowsPaginator->firstItem() + $i }}</td>
                                            <td class="ob-cell whitespace-nowrap">{{ $r['warehouse'] ?? '—' }}</td>
                                            <td class="ob-cell">{{ $r['name'] ?? '' }}</td>
                                            <td class="ob-cell">{{ $r['unit'] ?? '' }}</td>
                                            <td class="ob-cell ob-numr">{{ $fmtQty($qThen) }}</td>
                                            <td class="ob-cell ob-numr font-semibold">{{ $fmtQty($qNow) }}</td>
                                            <td class="ob-cell ob-numr {{ $mov > 0 ? 'text-emerald-800' : ($mov < 0 ? 'text-rose-800' : 'text-slate-600') }}">
                                                {{ $mov >= 0 ? '+' : '' }}{{ $fmtQty($mov) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="ob-cell py-6 text-center text-slate-500" colspan="7">Нет строк по фильтру.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-admin-layout>
