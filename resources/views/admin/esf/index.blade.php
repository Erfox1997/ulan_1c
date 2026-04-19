@php
    $fmt = static fn (?string $v) => $v === null || $v === '' ? '—' : number_format((float) $v, 2, ',', ' ');
    $lineSum = static function ($sale) {
        $t = '0';
        foreach ($sale->lines as $line) {
            $t = bcadd($t, (string) ($line->line_sum ?? '0'), 2);
        }

        return $t;
    };
    $defaultOrgId = $organizations->first()?->id;
    $salesPending = $salesPending ?? collect();
    $salesRecorded = $salesRecorded ?? collect();
    $salesAvailable = $salesAvailable ?? collect();
    $hasAnyLegalSales = $hasAnyLegalSales ?? false;
    $esfTabDefault = $esfTabDefault ?? 'pending';
    $availableFilterError = $availableFilterError ?? null;
    $availableListHint = $availableListHint ?? '';
    $esfFilter = $esfFilter ?? ['date_from' => '', 'date_to' => ''];
    $availableFilterDateFrom = $availableFilterDateFrom ?? null;
    $availableFilterDateTo = $availableFilterDateTo ?? null;
@endphp
<x-admin-layout pageTitle="ЭСФ — выгрузка XML" main-class="px-3 py-5 sm:px-5 lg:px-8 max-w-7xl mx-auto w-full">
    <div class="space-y-6">
        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
                {{ session('error') }}
            </div>
        @endif
        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($organizations->isEmpty())
            <div class="rounded-xl border border-slate-200/90 bg-white px-4 py-4 shadow-sm ring-1 ring-slate-900/5 sm:px-6">
                @include('admin.esf._page-title')
            </div>
            <div class="rounded-xl border border-slate-200/90 bg-white px-5 py-12 text-center text-sm text-slate-600 shadow-sm ring-1 ring-slate-900/5">
                Нет организаций — сначала заполните справочник.
            </div>
        @elseif (! $hasAnyLegalSales)
            <div class="rounded-xl border border-slate-200/90 bg-white px-4 py-4 shadow-sm ring-1 ring-slate-900/5 sm:px-6">
                @include('admin.esf._page-title')
            </div>
            <div class="rounded-xl border border-slate-200/90 bg-white px-5 py-12 text-center shadow-sm ring-1 ring-slate-900/5">
                <p class="text-sm text-slate-600">Нет реализаций юридическим лицам в этой филиальной базе.</p>
                <p class="mt-4">
                    <a href="{{ route('admin.legal-entity-sales.index') }}" class="text-sm font-semibold text-emerald-700 underline hover:text-emerald-800">Журнал реализации юрлицам</a>
                </p>
            </div>
        @else
            <div class="rounded-xl border border-slate-200/90 bg-white px-4 py-4 shadow-sm ring-1 ring-slate-900/5 sm:px-6">
                @include('admin.esf._page-title')

                @if ($availableFilterError)
                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950" role="alert">
                        {{ $availableFilterError }}
                    </div>
                @endif

                <div class="mt-6 overflow-hidden rounded-xl border border-slate-200/90 bg-slate-50/40 shadow-sm ring-1 ring-slate-900/5">
                    <div class="border-b border-slate-200 bg-slate-50/80 px-4 py-3 sm:px-6">
                        <h3 class="text-sm font-semibold text-slate-900">Реализации без очереди ЭСФ</h3>
                        <p class="mt-1 text-xs text-slate-600">{{ $availableListHint }}</p>
                        <form method="GET" action="{{ route('admin.esf.index') }}" class="mt-4 flex flex-wrap items-end gap-3">
                            <div>
                                <label for="esf_f_date_from" class="mb-1 block text-[11px] font-semibold text-slate-600">Дата с</label>
                                <input
                                    id="esf_f_date_from"
                                    type="date"
                                    name="date_from"
                                    value="{{ old('date_from', $availableFilterDateFrom) }}"
                                    class="rounded-lg border border-slate-200 bg-white px-2 py-2 text-sm text-slate-900 shadow-sm"
                                />
                            </div>
                            <div>
                                <label for="esf_f_date_to" class="mb-1 block text-[11px] font-semibold text-slate-600">Дата по</label>
                                <input
                                    id="esf_f_date_to"
                                    type="date"
                                    name="date_to"
                                    value="{{ old('date_to', $availableFilterDateTo) }}"
                                    class="rounded-lg border border-slate-200 bg-white px-2 py-2 text-sm text-slate-900 shadow-sm"
                                />
                            </div>
                            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-700">
                                Показать
                            </button>
                            <a href="{{ route('admin.esf.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Сбросить период</a>
                        </form>
                    </div>
                    @if ($salesAvailable->isEmpty())
                        <div class="px-4 py-8 text-center text-sm text-slate-600 sm:px-6">
                            Все реализации уже отмечены для ЭСФ или список пуст. Откройте вкладки ниже.
                        </div>
                    @else
                        <div class="overflow-x-auto p-3 sm:p-4">
                            @include('admin.esf._table-available', [
                                'sales' => $salesAvailable,
                                'fmt' => $fmt,
                                'lineSum' => $lineSum,
                                'esfFilter' => $esfFilter,
                            ])
                        </div>
                    @endif
                </div>

                <div x-data="{ tab: @js($esfTabDefault) }" class="mt-8 space-y-4">
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <button
                            type="button"
                            class="flex-1 rounded-lg border px-4 py-3 text-center text-sm font-semibold transition-colors"
                            :class="tab === 'pending' ? 'border-emerald-600 bg-emerald-50 text-emerald-900 ring-1 ring-emerald-600/30' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                            @click="tab = 'pending'"
                        >
                            Нужно записать ({{ $salesPending->count() }})
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-lg border px-4 py-3 text-center text-sm font-semibold transition-colors"
                            :class="tab === 'recorded' ? 'border-emerald-600 bg-emerald-50 text-emerald-900 ring-1 ring-emerald-600/30' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                            @click="tab = 'recorded'"
                        >
                            Записано в ЭСФ ({{ $salesRecorded->count() }})
                        </button>
                    </div>

                    <div x-show="tab === 'pending'" x-cloak class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/5">
                        <div class="border-b border-slate-200 bg-slate-50/80 px-4 py-3 sm:px-6">
                            <h3 class="text-sm font-semibold text-slate-900">К записи в ГНС</h3>
                        </div>
                        @if ($salesPending->isEmpty())
                            <div class="px-4 py-10 text-center text-sm text-slate-600 sm:px-6">
                                Нет документов в очереди — отметьте «Нужна ЭСФ» в таблице выше или все уже записаны в налоговой.
                            </div>
                        @else
                            <div class="overflow-x-auto p-3 sm:p-4">
                                @include('admin.esf._table-pending', [
                                    'sales' => $salesPending,
                                    'organizations' => $organizations,
                                    'orgsPayload' => $orgsPayload,
                                    'defaultOrgId' => $defaultOrgId,
                                    'fmt' => $fmt,
                                    'lineSum' => $lineSum,
                                    'esfFilter' => $esfFilter,
                                ])
                            </div>
                        @endif
                    </div>

                    <div x-show="tab === 'recorded'" x-cloak class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/5">
                        <div class="border-b border-slate-200 bg-slate-50/80 px-4 py-3 sm:px-6">
                            <h3 class="text-sm font-semibold text-slate-900">Уже записано в налоговой</h3>
                        </div>
                        @if ($salesRecorded->isEmpty())
                            <div class="px-4 py-10 text-center text-sm text-slate-600 sm:px-6">
                                Пока нет записанных ЭСФ.
                            </div>
                        @else
                            <div class="overflow-x-auto p-3 sm:p-4">
                                @include('admin.esf._table-recorded', [
                                    'sales' => $salesRecorded,
                                    'fmt' => $fmt,
                                    'lineSum' => $lineSum,
                                    'esfFilter' => $esfFilter,
                                ])
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-admin-layout>
