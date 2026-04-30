@php
    use App\Models\Counterparty;
    $kindLabels = Counterparty::kindLabels();
    $legalLabels = Counterparty::legalFormLabels();
    $kindBadgeClass = static fn (string $k): string => match ($k) {
        Counterparty::KIND_BUYER => 'cp-badge-buyer',
        Counterparty::KIND_SUPPLIER => 'cp-badge-supplier',
        default => 'cp-badge-other',
    };
@endphp
<x-admin-layout pageTitle="Контрагенты" main-class="px-3 py-6 sm:px-6 lg:px-8">
    @include('admin.partials.cp-brush')
    <div class="cp-root mx-auto w-full max-w-6xl space-y-4">
        @include('admin.partials.status-flash')

        @if (session('import_errors'))
            <div
                class="rounded-2xl border border-amber-200/90 bg-gradient-to-r from-amber-50 to-white px-4 py-3 text-sm text-amber-950 shadow-sm ring-1 ring-amber-100/60"
            >
                <p class="font-semibold">Замечания при импорте:</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach (session('import_errors') as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-sky-200/70 bg-white shadow-[0_8px_30px_-8px_rgba(14,165,233,0.15)] ring-1 ring-sky-100/60">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-emerald-100/80 bg-gradient-to-r from-emerald-50/95 via-white to-sky-50/60 px-4 py-3.5 sm:px-5">
                <div class="flex min-w-0 items-center gap-3">
                    <span
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-teal-600 text-white shadow-md shadow-emerald-500/25"
                        aria-hidden="true"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"
                            />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="mb-0.5 text-[10px] font-semibold uppercase tracking-wider text-teal-700/90">Закупки и продажи</p>
                        <h2 class="truncate text-[14px] font-bold leading-tight text-slate-800">Контрагенты</h2>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form
                        method="POST"
                        action="{{ route('admin.counterparties.import') }}"
                        enctype="multipart/form-data"
                        class="flex flex-wrap items-center gap-2"
                    >
                        @csrf
                        <input
                            type="file"
                            name="file"
                            id="counterparty_import_file"
                            class="hidden"
                            accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
                            onchange="if (this.files.length) this.form.requestSubmit()"
                        />
                        <button
                            type="button"
                            class="cp-btn border-emerald-200/90 bg-gradient-to-b from-white to-emerald-50/90 text-[11px] font-semibold text-slate-800 shadow-sm hover:to-emerald-50"
                            onclick="document.getElementById('counterparty_import_file').click()"
                        >
                            Excel…
                        </button>
                    </form>
                    <a
                        href="{{ route('admin.counterparties.sample-import') }}"
                        class="cp-btn border-sky-200 bg-gradient-to-b from-sky-50 to-white text-[11px] font-semibold text-sky-900 hover:from-sky-100"
                    >
                        Образец
                    </a>
                    <a href="{{ route('admin.counterparties.create') }}" class="cp-btn cp-btn-primary shadow-md shadow-amber-400/20 ring-1 ring-amber-300/40">
                        <span class="text-[14px] leading-none">+</span>
                        Добавить
                    </a>
                </div>
            </div>
            <x-input-error class="px-4 pt-3 sm:px-5" :messages="$errors->get('file')" />

            <form
                method="GET"
                action="{{ route('admin.counterparties.index') }}"
                class="relative z-[1] border-b border-slate-200/80 bg-slate-50/70 px-4 py-3 sm:px-5"
                role="search"
            >
                <label for="cp_index_q" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                    Поиск
                </label>
                <div class="flex flex-wrap items-stretch gap-2 sm:items-center">
                    <div class="relative min-w-[min(100%,28rem)] flex-1">
                        <input
                            type="search"
                            name="q"
                            id="cp_index_q"
                            value="{{ $searchQuery ?? '' }}"
                            autocomplete="off"
                            placeholder="Наименование, полное наименование, ИНН, телефон, адрес…"
                            class="w-full rounded-xl border border-slate-200/90 bg-white py-2.5 pl-3 pr-10 text-sm text-slate-900 shadow-sm ring-1 ring-slate-900/[0.04] placeholder:text-slate-400 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        />
                        <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 select-none" aria-hidden="true">⌕</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="submit"
                            class="inline-flex shrink-0 items-center justify-center rounded-xl border border-emerald-300/90 bg-gradient-to-b from-emerald-50 to-white px-4 py-2.5 text-sm font-semibold text-emerald-950 shadow-sm hover:from-emerald-100/90"
                        >
                            Найти
                        </button>
                        @if (trim($searchQuery ?? '') !== '')
                            <a
                                href="{{ route('admin.counterparties.index') }}"
                                class="shrink-0 text-sm font-semibold text-slate-600 underline-offset-2 hover:text-slate-900 hover:underline"
                            >Сбросить</a>
                        @endif
                    </div>
                </div>
            </form>

            <div class="cp-table-wrap bg-gradient-to-b from-slate-50/30 via-white to-emerald-50/20">
                <table class="cp-table cp-directory-table">
                    <thead>
                        <tr>
                            <th>Тип</th>
                            <th>Наименование</th>
                            <th class="hidden md:table-cell">Правовая форма</th>
                            <th class="hidden lg:table-cell">Полное наименование</th>
                            <th class="hidden sm:table-cell">Телефон</th>
                            <th class="cp-num">Счета</th>
                            <th class="text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($counterparties as $c)
                            <tr>
                                <td class="whitespace-nowrap align-middle">
                                    <span class="cp-badge {{ $kindBadgeClass($c->kind) }}">
                                        {{ Str::limit($kindLabels[$c->kind] ?? $c->kind, 24) }}
                                    </span>
                                </td>
                                <td class="align-middle font-semibold text-neutral-900">{{ $c->name }}</td>
                                <td class="hidden align-middle md:table-cell">{{ $legalLabels[$c->legal_form] ?? $c->legal_form }}</td>
                                <td class="hidden max-w-xs align-middle lg:table-cell">
                                    <span class="line-clamp-2" title="{{ $c->full_name }}">{{ $c->full_name }}</span>
                                </td>
                                <td class="hidden whitespace-nowrap align-middle tabular-nums sm:table-cell">{{ $c->phone ?? '—' }}</td>
                                <td class="cp-num align-middle tabular-nums">{{ $c->bank_accounts_count }}</td>
                                <td class="whitespace-nowrap text-right align-middle">
                                    <a href="{{ route('admin.counterparties.edit', $c) }}" class="cp-link">Изменить</a>
                                    <span class="mx-1.5 text-slate-300">|</span>
                                    <form
                                        action="{{ route('admin.counterparties.destroy', $c) }}"
                                        method="POST"
                                        class="inline"
                                        onsubmit="return confirm('Удалить контрагента «{{ $c->name }}»?');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="cp-link text-red-700 hover:text-red-900">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-14 text-center text-[13px] text-slate-600">
                                    @if (trim($searchQuery ?? '') !== '')
                                        По запросу ничего не найдено.
                                        <a
                                            href="{{ route('admin.counterparties.index') }}"
                                            class="cp-link font-semibold text-sky-700 hover:text-sky-900"
                                        >Показать всех</a>
                                    @else
                                        Контрагентов нет —
                                        <a
                                            href="{{ route('admin.counterparties.create') }}"
                                            class="cp-link font-semibold text-sky-700 hover:text-sky-900"
                                        >создать</a>
                                        или
                                        <button
                                            type="button"
                                            class="cp-link font-semibold text-sky-700 hover:text-sky-900"
                                            onclick="document.getElementById('counterparty_import_file').click()"
                                        >
                                            загрузить Excel
                                        </button>
                                        .
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-admin-layout>
