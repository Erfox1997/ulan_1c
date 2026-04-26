@php
    /** @var \App\Models\PurchaseRequest $purchaseRequest */
    $fmtQty = static fn ($v) => number_format((float) $v, 4, ',', ' ');
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="px-3 py-6 sm:px-6 lg:px-8">
    @include('admin.partials.cp-brush')
    <div class="cp-root mx-auto w-full max-w-[min(100%,56rem)] space-y-6">
        @include('admin.partials.status-flash')

        @if ($errors->any())
            <div
                class="rounded-xl border border-rose-200/90 bg-rose-50 px-4 py-3 text-sm text-rose-950 shadow-sm"
                role="alert"
            >
                <p class="font-semibold">Проверьте введённые данные.</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Редактирование</p>
                <h1 class="mt-1 text-lg font-semibold text-slate-900">Заявка № {{ $purchaseRequest->id }}</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Можно изменить комментарий, количество к закупке и убрать лишние позиции (снимки остатка при создании не меняются).
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a
                    href="{{ route('admin.purchase-requests.show', $purchaseRequest) }}"
                    class="inline-flex shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm ring-1 ring-slate-900/5 hover:bg-slate-50"
                >К просмотру</a>
                <a
                    href="{{ route('admin.purchase-requests.index') }}"
                    class="inline-flex shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm ring-1 ring-slate-900/5 hover:bg-slate-50"
                >К списку</a>
            </div>
        </div>

        <form
            method="POST"
            action="{{ route('admin.purchase-requests.update', $purchaseRequest) }}"
            class="space-y-6"
        >
            @csrf
            @method('PUT')

            <div
                class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50"
            >
                <div class="overflow-hidden rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90 p-4 sm:p-5">
                    <h2 class="text-sm font-semibold text-slate-900">Позиции</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Отметьте «убрать», чтобы удалить позицию из заявки.</p>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full border-collapse text-left text-sm">
                            <thead class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-2.5">Наименование</th>
                                    <th class="px-3 py-2.5">Склад</th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-right">Остаток (снимок)</th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-right">К закупке</th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-center">Убрать</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($purchaseRequest->lines as $i => $line)
                                    <tr class="align-top">
                                        <td class="px-3 py-2.5">
                                            <input type="hidden" name="lines[{{ $i }}][id]" value="{{ $line->id }}" />
                                            <span class="font-medium text-slate-900">{{ $line->good?->name ?? '—' }}</span>
                                            @if ($line->oem_snapshot)
                                                <span class="mt-0.5 block text-[10px] text-slate-500">ОЭМ: {{ $line->oem_snapshot }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 text-slate-800">{{ $line->warehouse?->name ?? '—' }}</td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-slate-800">
                                            {{ $fmtQty($line->quantity_snapshot) }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-right">
                                            <input
                                                type="text"
                                                inputmode="decimal"
                                                name="lines[{{ $i }}][quantity]"
                                                value="{{ old('lines.'.$i.'.quantity', $line->quantity_requested) }}"
                                                autocomplete="off"
                                                class="w-28 rounded-lg border border-slate-200 px-2 py-1 text-right text-sm tabular-nums text-slate-900 focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-500/30"
                                                @focus="$event.target.select()"
                                                @click="$event.target.select()"
                                            />
                                        </td>
                                        <td class="px-3 py-2.5 text-center">
                                            <input
                                                type="checkbox"
                                                name="lines[{{ $i }}][remove]"
                                                value="1"
                                                class="h-3.5 w-3.5 rounded border-slate-400 text-rose-600 focus:ring-rose-500/30"
                                                @checked((string) old('lines.'.$i.'.remove', '') === '1')
                                            />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <x-input-label for="pr_edit_note" value="Комментарий к заявке" />
                        <textarea
                            id="pr_edit_note"
                            name="note"
                            rows="3"
                            class="mt-1 block w-full rounded-xl border border-slate-200/90 px-3 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-slate-900/5 placeholder:text-slate-400 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/25"
                            placeholder="Срочность, поставщик…"
                        >{{ old('note', $purchaseRequest->note) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-2">
                <a
                    href="{{ route('admin.purchase-requests.index') }}"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                >Отмена</a>
                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-xl border border-emerald-200/90 bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
                >Сохранить</button>
            </div>
        </form>

        <div class="border-t border-slate-200/90 pt-4">
            <form
                method="POST"
                action="{{ route('admin.purchase-requests.destroy', $purchaseRequest) }}"
                onsubmit="return confirm('Удалить заявку № {{ $purchaseRequest->id }} целиком? Это действие нельзя отменить.');"
            >
                @csrf
                @method('DELETE')
                <button
                    type="submit"
                    class="text-sm font-semibold text-rose-700 hover:text-rose-900"
                >Удалить заявку целиком</button>
            </form>
        </div>
    </div>
</x-admin-layout>
