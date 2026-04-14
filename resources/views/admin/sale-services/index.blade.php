@php
    $fmt = static function ($v): string {
        if ($v === null) {
            return '—';
        }

        return number_format((float) $v, 2, ',', ' ');
    };
@endphp
<x-admin-layout pageTitle="Продажа услуг">
    @include('admin.partials.cp-brush')
    <div class="cp-root mx-auto w-full max-w-6xl space-y-4">
        @if (session('status'))
            <div
                class="flex items-start gap-3 rounded-2xl border border-emerald-200/90 bg-gradient-to-r from-emerald-50 to-teal-50/90 px-4 py-3 text-[13px] text-emerald-950 shadow-sm"
                role="status"
            >
                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-700" aria-hidden="true">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($errors->has('delete'))
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 shadow-sm">
                {{ $errors->first('delete') }}
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-sky-200/70 bg-white shadow-[0_8px_30px_-8px_rgba(14,165,233,0.15)] ring-1 ring-sky-100/60">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-emerald-100/80 bg-gradient-to-r from-emerald-50/95 via-white to-sky-50/60 px-4 py-3.5 sm:px-5">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-teal-600 text-white shadow-md shadow-emerald-500/25" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655-5.653a2.548 2.548 0 010-3.586L11.4 2.845a2.548 2.548 0 013.586 0l5.653 4.655a2.548 2.548 0 010 3.586l-1.635 1.635M11.42 15.17L9.15 12.91" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="mb-0.5 text-[10px] font-semibold uppercase tracking-wider text-teal-700/90">Справочник</p>
                        <h2 class="truncate text-[14px] font-bold leading-tight text-slate-800">Продажа услуг</h2>
                    </div>
                </div>
                <a href="{{ route('admin.sale-services.create') }}" class="cp-btn cp-btn-primary shadow-md shadow-amber-400/20 ring-1 ring-amber-300/40">
                    <span class="text-[14px] leading-none">+</span>
                    Добавить услугу
                </a>
            </div>
            <div class="cp-table-wrap bg-gradient-to-b from-slate-50/40 to-white">
                <table class="cp-table">
                    <thead>
                        <tr>
                            <th>Наименование</th>
                            <th>Ед.</th>
                            <th class="cp-num">Цена, сом</th>
                            <th class="text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($services as $s)
                            <tr>
                                <td class="align-top font-semibold text-neutral-900">{{ $s->name }}</td>
                                <td class="align-top whitespace-nowrap text-neutral-700">{{ $s->unit ?? '—' }}</td>
                                <td class="cp-num align-top tabular-nums">{{ $fmt($s->sale_price) }}</td>
                                <td class="whitespace-nowrap text-right align-top">
                                    <a href="{{ route('admin.sale-services.edit', $s) }}" class="cp-link">Изменить цену</a>
                                    <span class="mx-1.5 text-slate-300">|</span>
                                    <form
                                        action="{{ route('admin.sale-services.destroy', $s) }}"
                                        method="POST"
                                        class="inline"
                                        onsubmit="return confirm('Удалить услугу «{{ $s->name }}»?');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="cp-link text-red-700 hover:text-red-900">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-14 text-center text-[13px] text-slate-600">
                                    Нет услуг —
                                    <a href="{{ route('admin.sale-services.create') }}" class="cp-link font-semibold text-sky-700 hover:text-sky-900">добавить</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-admin-layout>
