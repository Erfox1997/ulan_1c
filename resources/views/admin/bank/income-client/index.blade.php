@php
    $fmtMoney = static fn ($v): string => number_format((float) $v, 2, ',', ' ');
    $cpIdFilter = isset($filterCounterpartyId) && $filterCounterpartyId !== null ? (int) $filterCounterpartyId : null;
    $hasSearch =
        $cpIdFilter !== null
        || (isset($searchQuery) && $searchQuery !== '');
    $filterInputValue =
        $cpIdFilter !== null && isset($filterCounterpartyLabel) && $filterCounterpartyLabel !== ''
            ? $filterCounterpartyLabel
            : ($searchQuery ?? '');
    $bankMovementCpListFilterInit = [
        'searchUrl' => route('admin.counterparties.search', ['for' => 'sale']),
        'listUrl' => route('admin.bank.income-client'),
        'initialValue' => $filterInputValue,
        'appliedCounterpartyId' => $cpIdFilter,
        'allowedKinds' => ['buyer', 'other'],
    ];
@endphp
<x-admin-layout pageTitle="Приход: оплата от покупателя" main-class="bg-slate-100/80 px-3 py-5 sm:px-6 lg:px-8">
    <style>
        .income-client-cp-dd {
            max-height: 16rem;
            overflow-y: auto;
            border: 1px solid rgb(226 232 240);
            border-radius: 0.75rem;
            background: #fff;
            box-shadow:
                0 10px 15px -3px rgb(15 23 42 / 0.12),
                0 4px 6px -4px rgb(15 23 42 / 0.08);
        }
        .income-client-cp-dd button.cp-row-in {
            display: flex;
            width: 100%;
            flex-direction: column;
            align-items: flex-start;
            gap: 1px;
            padding: 0.5rem 0.75rem;
            text-align: left;
            font-size: 0.8125rem;
            line-height: 1.35;
            border: 0;
            border-bottom: 1px solid rgb(241 245 249);
            background: #fff;
            cursor: pointer;
            color: rgb(15 23 42);
        }
        .income-client-cp-dd button.cp-row-in:last-of-type {
            border-bottom: 0;
        }
        .income-client-cp-dd button.cp-row-in:hover,
        .income-client-cp-dd button.cp-row-in:focus {
            background: rgb(240 249 255);
            outline: none;
        }
        .income-client-cp-dd .cp-kind-in {
            font-size: 10px;
            font-weight: 600;
            color: rgb(100 116 139);
        }
        .income-client-cp-dd .cp-foot-in {
            padding: 0.5rem 0.75rem;
            font-size: 11px;
            line-height: 1.4;
            color: rgb(100 116 139);
            background: rgb(248 250 252);
            border-top: 1px solid rgb(241 245 249);
        }
    </style>

    <div class="mx-auto w-full max-w-5xl space-y-4">
        @include('admin.partials.status-flash')

        <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-sky-800/90">Банк и касса</p>
                <p class="mt-0.5 text-sm text-slate-600">
                    Операции прихода от покупателей по филиалу (последние 500 записей).
                    @if ($hasSearch)
                        @if ($cpIdFilter !== null)
                            Фильтр по контрагенту: «{{ $filterCounterpartyLabel }}».
                        @else
                            Поиск по тексту: «{{ $searchQuery }}».
                        @endif
                    @endif
                </p>
            </div>
            <a
                href="{{ route('admin.bank.income-client.create') }}"
                class="inline-flex w-full shrink-0 items-center justify-center rounded-xl border border-emerald-800/20 bg-emerald-600 px-5 py-3 text-center text-sm font-bold !text-white no-underline shadow-[0_10px_28px_-6px_rgba(5,150,105,0.55)] transition hover:bg-emerald-500 active:bg-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 sm:w-auto visited:!text-white"
            >
                + Создать операцию
            </a>
        </div>

        <script>
            window.__bankMovementCpListFilterInit = @json($bankMovementCpListFilterInit);
        </script>

        <form
            method="get"
            action="{{ route('admin.bank.income-client') }}"
            class="rounded-2xl border border-slate-200/90 bg-white p-3 shadow-sm ring-1 ring-slate-900/[0.04] sm:flex sm:flex-row sm:flex-wrap sm:items-start sm:justify-between sm:gap-3"
            role="search"
            x-data="bankMovementCpListFilter()"
            @keydown.escape.window="onCpEscape()"
            @submit="submitTextSearch($event)"
        >
            <div class="relative min-w-0 flex-1" x-ref="bankCpListFilterRoot">
                <label for="income_client_q" class="mb-1 block text-xs font-semibold text-slate-600">Клиент (контрагент)</label>
                <input
                    id="income_client_q"
                    type="search"
                    autocomplete="off"
                    x-model="query"
                    x-on:input="onCpInput($event)"
                    x-on:focus="onCpFocus($event)"
                    x-on:blur="onCpBlur()"
                    class="min-h-[2.75rem] w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-inner shadow-slate-900/[0.03] placeholder:text-slate-400 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/25"
                    placeholder="Введите от 2 букв — список под полем"
                />

                <div
                    x-cloak
                    x-show="showCpDropdown()"
                    class="income-client-cp-dd fixed z-[220]"
                    role="listbox"
                    aria-label="Подходящие контрагенты"
                    @mousedown.prevent
                    x-bind:style="'top:' + cpPos.top + 'px;left:' + cpPos.left + 'px;width:' + cpPos.width + 'px'"
                >
                    <div x-show="cpLoading" class="cp-foot-in text-slate-600">Поиск…</div>
                    <template x-for="item in cpItems" :key="item.id">
                        <button
                            type="button"
                            class="cp-row-in"
                            @mousedown.prevent
                            x-on:click="pickCounterparty(item)"
                            role="option"
                        >
                            <span x-text="item.full_name || item.name"></span>
                            <span class="cp-kind-in" x-text="kindLabel(item.kind)"></span>
                        </button>
                    </template>
                    <div
                        x-show="!cpLoading && cpNoHits && cpItems.length === 0"
                        class="cp-foot-in"
                        x-cloak
                    >
                        Нет совпадений в справочнике.&nbsp;<span class="font-medium text-slate-700">Найти по тексту</span> можно кнопкой ниже —
                        по строке в наименовании, полном имени или ИНН.
                    </div>
                </div>
            </div>

            <div class="mt-2 flex shrink-0 flex-wrap gap-2 sm:mt-7">
                <button
                    type="submit"
                    class="inline-flex min-h-[2.75rem] items-center justify-center rounded-xl border border-sky-700/25 bg-sky-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2"
                >
                    Найти по тексту
                </button>
                @if ($hasSearch)
                    <a
                        href="{{ route('admin.bank.income-client') }}"
                        class="inline-flex min-h-[2.75rem] items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2"
                    >
                        Сбросить
                    </a>
                @endif
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
            <div
                class="border-b border-sky-900/10 px-4 py-3 text-white sm:px-5"
                style="background: linear-gradient(120deg, #0284c7 0%, #0d9488 45%, #059669 100%);"
            >
                <h2 class="text-sm font-bold tracking-tight">История операций</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                            <th class="whitespace-nowrap px-4 py-2.5">Дата</th>
                            <th class="whitespace-nowrap px-4 py-2.5 text-right">Сумма</th>
                            <th class="min-w-[10rem] px-4 py-2.5">Счёт / касса</th>
                            <th class="min-w-[12rem] px-4 py-2.5">Клиент</th>
                            <th class="min-w-[8rem] px-4 py-2.5">Комментарий</th>
                            <th class="whitespace-nowrap px-4 py-2.5">Кто внёс</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($movements as $m)
                            @php
                                $cp = $m->counterparty;
                                $cpLabel = $cp
                                    ? (trim((string) $cp->full_name) !== ''
                                        ? $cp->full_name
                                        : \App\Models\Counterparty::buildFullName($cp->legal_form, (string) $cp->name))
                                    : '—';
                            @endphp
                            <tr
                                class="cursor-pointer transition-colors hover:bg-sky-50/70 focus-visible:outline focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-sky-400"
                                role="link"
                                tabindex="0"
                                title="Открыть для редактирования"
                                onclick="window.location.href={{ json_encode(route('admin.bank.income-client.edit', $m->id)) }}"
                                onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();window.location.href={{ json_encode(route('admin.bank.income-client.edit', $m->id)) }};}"
                            >
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-slate-800">{{ $m->occurred_on?->format('d.m.Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-emerald-800">{{ $fmtMoney($m->amount) }}</td>
                                <td class="max-w-[14rem] px-4 py-3 text-slate-700" title="{{ $m->ourAccount?->summaryLabel() ?? '' }}">
                                    {{ $m->ourAccount?->summaryLabel() ?? '—' }}
                                </td>
                                <td class="max-w-[18rem] px-4 py-3 text-slate-800" title="{{ $cpLabel }}">
                                    <span class="line-clamp-2">{{ $cpLabel }}</span>
                                </td>
                                <td class="max-w-[14rem] px-4 py-3 text-slate-600" title="{{ $m->comment ?? '' }}">
                                    <span class="line-clamp-2">{{ $m->comment ? \Illuminate\Support\Str::limit((string) $m->comment, 80) : '—' }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $m->user?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500">
                                    @if ($hasSearch)
                                        @if ($cpIdFilter !== null)
                                            По выбранному контрагенту операций не найдено.
                                            <a href="{{ route('admin.bank.income-client') }}" class="font-semibold text-sky-800 underline decoration-sky-400 underline-offset-2 hover:text-sky-950">Показать все</a>
                                        @else
                                            По запросу «{{ $searchQuery }}» операций не найдено.
                                            <a href="{{ route('admin.bank.income-client') }}" class="font-semibold text-sky-800 underline decoration-sky-400 underline-offset-2 hover:text-sky-950">Показать все</a>
                                        @endif
                                    @else
                                        Пока нет операций. Нажмите «Создать операцию», чтобы записать приход от покупателя.
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
