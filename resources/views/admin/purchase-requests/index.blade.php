@php
    $fmtDt = static fn ($d) => $d ? $d->format('d.m.Y H:i') : '—';
    $oldIds = collect(old('ids', []))->map(fn ($v) => (int) $v)->filter()->all();
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="px-3 py-6 sm:px-6 lg:px-8">
    @include('admin.partials.cp-brush')
    <div class="cp-root mx-auto w-full max-w-[min(100%,112rem)] space-y-6">
        @include('admin.partials.status-flash')

        @if ($errors->has('ids'))
            <div
                class="rounded-xl border border-rose-200/90 bg-rose-50 px-4 py-3 text-sm text-rose-950 shadow-sm"
                role="alert"
            >
                {{ $errors->first('ids') }}
            </div>
        @endif

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-lg font-semibold text-slate-900">{{ $pageTitle }}</h1>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">
                    Отметьте галочками одну или несколько заявок, затем скачайте Excel или PDF с позициями (наименование, к закупке, ОЭМ).
                </p>
            </div>
            <a
                href="{{ route('admin.reports.goods-stock') }}"
                class="inline-flex shrink-0 items-center justify-center rounded-xl border border-emerald-200/90 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-900 shadow-sm ring-1 ring-emerald-900/5 hover:bg-emerald-100/90"
            >
                К остаткам товаров
            </a>
        </div>

        <div
            class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50"
        >
            <div class="overflow-hidden rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90">
                <form id="purchase-requests-export-form" method="POST" action="{{ route('admin.purchase-requests.export.excel') }}">
                    @csrf
                    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200/90 px-4 py-3 sm:px-5">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Экспорт выбранных</span>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                        >
                            Скачать Excel
                        </button>
                        <button
                            type="submit"
                            formaction="{{ route('admin.purchase-requests.export.pdf') }}"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                        >
                            Скачать PDF
                        </button>
                        <button
                            type="submit"
                            formaction="{{ route('admin.purchase-requests.export.pdf') }}"
                            name="inline"
                            value="1"
                            formtarget="_blank"
                            class="inline-flex items-center justify-center rounded-lg border border-emerald-200/90 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-900 shadow-sm hover:bg-emerald-100/90"
                            title="Открыть PDF в новой вкладке — удобно отправить или поделиться с телефона"
                        >
                            Открыть PDF (для отправки)
                        </button>
                    </div>

                    <div class="overflow-x-auto border-b border-slate-200/90">
                        <table class="min-w-full border-collapse text-left text-sm">
                            <thead class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="w-10 whitespace-nowrap px-2 py-3 text-center">
                                        <input
                                            type="checkbox"
                                            class="h-3.5 w-3.5 rounded border-slate-400 text-emerald-600 focus:ring-emerald-500/30"
                                            title="Выделить все на этой странице"
                                            @if ($paginator->isNotEmpty())
                                                onchange="var el=this; document.querySelectorAll('.pr-export-cb').forEach(function(c){ c.checked = el.checked; });"
                                            @endif
                                        />
                                    </th>
                                    <th class="whitespace-nowrap px-4 py-3">№</th>
                                    <th class="whitespace-nowrap px-4 py-3">Дата</th>
                                    <th class="whitespace-nowrap px-4 py-3">Кто создал</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-right">Позиций</th>
                                    <th class="min-w-[12rem] px-4 py-3">Комментарий</th>
                                    <th class="whitespace-nowrap px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($paginator as $row)
                                    <tr class="align-top hover:bg-emerald-50/35">
                                        <td class="px-2 py-3 text-center align-middle">
                                            <input
                                                type="checkbox"
                                                name="ids[]"
                                                value="{{ $row->id }}"
                                                class="pr-export-cb h-3.5 w-3.5 rounded border-slate-400 text-emerald-600 focus:ring-emerald-500/30"
                                                @checked(in_array((int) $row->id, $oldIds, true))
                                            />
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-600">{{ $row->id }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 tabular-nums text-slate-800">{{ $fmtDt($row->created_at) }}</td>
                                        <td class="px-4 py-3 text-slate-800">{{ $row->user?->name ?? '—' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums font-semibold text-slate-900">
                                            {{ $row->lines_count }}
                                        </td>
                                        <td class="max-w-md px-4 py-3 text-slate-600">
                                            {{ $row->note ? \Illuminate\Support\Str::limit($row->note, 120) : '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right">
                                            <a
                                                href="{{ route('admin.purchase-requests.show', $row) }}"
                                                class="text-sm font-semibold text-emerald-700 hover:text-emerald-900"
                                            >Подробнее</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-12 text-center text-sm text-slate-500">Заявок пока нет.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>
                @if ($paginator->lastPage() > 1)
                    <div class="bg-slate-50/90 px-4 py-3 text-sm text-slate-700">
                        {{ $paginator->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-admin-layout>
