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
    $esfTabDefault = $esfTabDefault ?? 'pending';
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

        @if ($sales->isEmpty())
            <div class="rounded-xl border border-slate-200/90 bg-white px-4 py-4 shadow-sm ring-1 ring-slate-900/5 sm:px-6">
                @include('admin.esf._page-title')
            </div>
            <div class="rounded-xl border border-slate-200/90 bg-white px-5 py-12 text-center shadow-sm ring-1 ring-slate-900/5">
                <p class="text-sm text-slate-600">Нет реализаций с отметкой «Выписать ЭСФ».</p>
                <p class="mt-4">
                    <a href="{{ route('admin.legal-entity-sales.index') }}" class="text-sm font-semibold text-emerald-700 underline hover:text-emerald-800">Журнал реализации юрлицам</a>
                </p>
            </div>
        @elseif ($organizations->isEmpty())
            <div class="rounded-xl border border-slate-200/90 bg-white px-4 py-4 shadow-sm ring-1 ring-slate-900/5 sm:px-6">
                @include('admin.esf._page-title')
            </div>
            <div class="rounded-xl border border-slate-200/90 bg-white px-5 py-12 text-center text-sm text-slate-600 shadow-sm ring-1 ring-slate-900/5">
                Нет организаций — сначала заполните справочник.
            </div>
        @else
            <div class="rounded-xl border border-slate-200/90 bg-white px-4 py-4 shadow-sm ring-1 ring-slate-900/5 sm:px-6">
                @include('admin.esf._page-title')
                <div x-data="{ tab: @js($esfTabDefault) }" class="mt-4 space-y-4">
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
                                Нет документов в этом списке — все отмечены как записанные или откройте «Журнал реализации юрлицам».
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
                                ])
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-admin-layout>
