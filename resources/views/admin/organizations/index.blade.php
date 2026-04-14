<x-admin-layout pageTitle="Данные организации" main-class="px-3 py-6 sm:px-6 lg:px-8">
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

        <div class="overflow-hidden rounded-2xl border border-sky-200/70 bg-white shadow-[0_8px_30px_-8px_rgba(14,165,233,0.15)] ring-1 ring-sky-100/60">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-emerald-100/80 bg-gradient-to-r from-emerald-50/95 via-white to-sky-50/60 px-4 py-3.5 sm:px-5">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-teal-600 text-white shadow-md shadow-emerald-500/25" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="mb-0.5 text-[10px] font-semibold uppercase tracking-wider text-teal-700/90">Справочник</p>
                        <h2 class="truncate text-[14px] font-bold leading-tight text-slate-800">Данные организации</h2>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.organizations.create') }}" class="cp-btn cp-btn-primary shadow-md shadow-amber-400/20 ring-1 ring-amber-300/40">
                        <span class="text-[14px] leading-none">+</span>
                        Добавить организацию
                    </a>
                    <div
                        class="relative"
                        x-data="{ open: false }"
                        @keydown.escape.window="open = false"
                    >
                        <button
                            type="button"
                            class="cp-btn min-h-[24px] min-w-[28px] border-sky-200 bg-gradient-to-b from-sky-50 to-white px-2 font-bold text-sky-800 hover:from-sky-100"
                            @click="open = !open"
                            :aria-expanded="open"
                            aria-label="Справка по разделу"
                        >
                            ?
                        </button>
                        <div
                            x-cloak
                            x-show="open"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:leave="transition ease-in duration-100"
                            @click.outside="open = false"
                            class="absolute right-0 top-full z-30 mt-1 w-[min(100vw-2rem,22rem)] rounded-xl border border-sky-200/90 bg-gradient-to-b from-amber-50 to-white px-3 py-2.5 text-left text-[11px] leading-relaxed text-slate-800 shadow-lg shadow-sky-200/40"
                            role="tooltip"
                        >
                            <p>Реквизиты для счетов на оплату и документов. Можно завести несколько организаций (например, разные ИП/ОсОО). У каждого счёта и кассы можно указать начальный остаток в валюте строки.</p>
                            @if ($organizations->isEmpty())
                                <p class="mt-2 border-t border-sky-100 pt-2 text-slate-600">
                                    Организаций пока нет. Добавьте первую — с реквизитами для счёта на оплату.
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="cp-table-wrap bg-gradient-to-b from-slate-50/30 via-white to-emerald-50/20">
                <table class="cp-table cp-directory-table">
                    <thead>
                        <tr>
                            <th>Наименование</th>
                            <th class="w-[1%] whitespace-nowrap text-center">По умолчанию</th>
                            <th class="whitespace-nowrap">ИНН</th>
                            <th>Счета и касса</th>
                            <th class="text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($organizations as $org)
                            <tr>
                                <td class="align-middle">
                                    <span class="font-semibold text-neutral-900">{{ $org->name }}</span>
                                </td>
                                <td class="align-middle text-center">
                                    @if ($org->is_default)
                                        <span class="cp-directory-pill">По умолчанию</span>
                                    @else
                                        <span class="cp-directory-pill-muted">—</span>
                                    @endif
                                </td>
                                <td class="align-middle whitespace-nowrap tabular-nums text-neutral-800">{{ $org->inn ?? '—' }}</td>
                                <td class="align-middle text-neutral-700">
                                    @php
                                        $nBank = $org->bankAccounts->where('account_type', 'bank')->count();
                                        $nCash = $org->bankAccounts->where('account_type', 'cash')->count();
                                    @endphp
                                    <span class="tabular-nums">банк: {{ $nBank }}</span>
                                    @if ($nCash > 0)
                                        <span class="text-sky-300"> · </span>
                                        <span class="tabular-nums">наличные: {{ $nCash }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-right align-middle">
                                    <a href="{{ route('admin.organizations.edit', $org) }}" class="cp-link">Изменить</a>
                                    <span class="mx-1.5 text-slate-300">|</span>
                                    <form
                                        action="{{ route('admin.organizations.destroy', $org) }}"
                                        method="POST"
                                        class="inline"
                                        onsubmit="return confirm('Удалить организацию и все привязанные счета?');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="cp-link text-red-700 hover:text-red-900">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-14 text-center text-[13px] text-slate-600">
                                    Организаций нет —
                                    <a href="{{ route('admin.organizations.create') }}" class="cp-link font-semibold text-sky-700 hover:text-sky-900">создать первую</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-admin-layout>
