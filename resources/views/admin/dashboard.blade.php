@php
    $fmtCash = static function ($v): string {
        if ($v === null) {
            return '—';
        }

        return number_format((float) $v, 2, ',', ' ');
    };
@endphp
<x-admin-layout pageTitle="Главное" main-class="px-3 py-6 sm:px-6 lg:px-8">
    @include('admin.partials.cp-brush')
    @include('admin.bank.partials.1c-form-document-styles')
    @include('admin.partials.dashboard-cash-styles')

    <div class="mx-auto w-full max-w-4xl space-y-4">
        @include('admin.partials.status-flash')
        @error('shift')
            <div
                class="flex items-start gap-3 rounded-2xl border border-amber-200/90 bg-gradient-to-r from-amber-50 to-white px-4 py-3 text-sm text-amber-950 shadow-sm ring-1 ring-amber-100/60"
                role="alert"
            >
                <span class="mt-0.5 font-bold text-amber-700">!</span>
                <span>{{ $message }}</span>
            </div>
        @enderror
        <div class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50">
            <div class="rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90 px-3 py-4 sm:px-5 sm:py-6">
                <div class="dashboard-cash-shell bank-1c-scope w-full min-w-0">
                    <div class="bank-1c-doc w-full">
            @if ($openShift)
                <div class="bank-1c-titlebar">
                    <h2>Кассовая смена — закрытие</h2>
                </div>

                <div class="bank-1c-info-panel">
                    <p class="bank-1c-info-title">Смена открыта</p>
                    <ul>
                        <li>Дата: {{ $openShift->business_date->format('d.m.Y') }}</li>
                        <li>Открыта: {{ $openShift->opened_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</li>
                        <li>Кассир: {{ $openShift->user?->name ?? '—' }}</li>
                        @if ($openShift->open_note)
                            <li>Комментарий: {{ $openShift->open_note }}</li>
                        @endif
                    </ul>
                </div>

                @if ($closingExpectedByAccount !== null && ! empty($closingExpectedByAccount['rows']))
                    <div class="bank-1c-embed-section">
                        <p class="bank-1c-section-title m-0 mb-2">При закрытии — по счетам (ориентир)</p>
                        <div class="bank-1c-table-panel overflow-x-auto">
                            <table class="bank-1c-data-table">
                                <thead>
                                    <tr>
                                        <th>Счёт</th>
                                        @if ($closingExpectedByAccount['has_per_account_opening'])
                                            <th class="bank-1c-num">На начало</th>
                                            <th class="bank-1c-num">Движение за смену</th>
                                            <th class="bank-1c-num">Должно быть</th>
                                        @else
                                            <th class="bank-1c-num">Движение за смену</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($closingExpectedByAccount['rows'] as $cer)
                                        <tr>
                                            <td class="font-semibold">{{ $cer['label'] }}</td>
                                            @if ($closingExpectedByAccount['has_per_account_opening'])
                                                <td class="bank-1c-num">{{ $fmtCash($cer['opening']) }}</td>
                                                <td class="bank-1c-num">{{ $fmtCash($cer['movement']) }}</td>
                                                <td class="bank-1c-num font-semibold">{{ $fmtCash($cer['expected']) }} сом</td>
                                            @else
                                                <td class="bank-1c-num">{{ $fmtCash($cer['movement']) }}</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    @if ($closingExpectedByAccount['has_per_account_opening'])
                                        <tr>
                                            <td>Итого</td>
                                            <td class="bank-1c-num">{{ $fmtCash($closingExpectedByAccount['totals']['opening']) }}</td>
                                            <td class="bank-1c-num">{{ $fmtCash($closingExpectedByAccount['totals']['movement']) }}</td>
                                            <td class="bank-1c-num">{{ $fmtCash($closingExpectedByAccount['totals']['expected']) }} сом</td>
                                        </tr>
                                    @else
                                        <tr>
                                            <td>Итого движение</td>
                                            <td class="bank-1c-num">{{ $fmtCash($closingExpectedByAccount['totals']['movement']) }} сом</td>
                                        </tr>
                                        <tr>
                                            <td class="bank-1c-tfoot-note" colspan="2">
                                                Ожидается всего (начало смены + операции этого кассира по деньгам):
                                                <span class="tabular-nums">{{ $fmtCash($closingExpectedByAccount['totals']['expected']) }} сом</span>
                                            </td>
                                        </tr>
                                    @endif
                                </tfoot>
                            </table>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.cash-shifts.close', $openShift) }}" class="contents">
                    @csrf

                    @if ($accountsForCloseShift !== [])
                        <div class="bank-1c-toolbar">
                            <button type="submit" class="bank-1c-tb-btn bank-1c-tb-btn-primary">Закрыть смену</button>
                        </div>
                    @endif

                    <div class="bank-1c-shift-form">
                        @if ($accountsForCloseShift === [])
                            <div class="bank-1c-banner-warn !mb-0">
                                Нет счетов в «Данные организации» — закрытие по счетам недоступно. Восстановите счета филиала.
                            </div>
                        @else
                            <ul class="m-0 list-none space-y-3 p-0">
                                @foreach ($accountsForCloseShift as $acc)
                                    <li class="bank-1c-field-row">
                                        <label class="bank-1c-field-label" for="closing_acc_{{ $acc['id'] }}">{{ $acc['label'] }}</label>
                                        <input
                                            id="closing_acc_{{ $acc['id'] }}"
                                            name="closing_by_account[{{ $acc['id'] }}]"
                                            type="text"
                                            value="{{ old('closing_by_account.'.$acc['id']) }}"
                                            inputmode="decimal"
                                            placeholder="0,00"
                                            required
                                            autocomplete="off"
                                        />
                                        <x-input-error class="mt-1" :messages="$errors->get('closing_by_account.'.$acc['id'])" />
                                    </li>
                                @endforeach
                            </ul>
                            <x-input-error class="mt-2" :messages="$errors->get('closing_by_account')" />
                        @endif
                        <div class="bank-1c-field-row @if ($accountsForCloseShift === []) hidden @endif">
                            <label class="bank-1c-field-label" for="close_note">Комментарий при закрытии (необязательно)</label>
                            <textarea
                                id="close_note"
                                name="close_note"
                                rows="2"
                                placeholder="При необходимости"
                            >{{ old('close_note') }}</textarea>
                            <x-input-error class="mt-1" :messages="$errors->get('close_note')" />
                        </div>
                    </div>
                </form>

                <div class="bank-1c-foot">
                    <a href="{{ route('admin.organizations.index') }}">Данные организации — счета</a>
                </div>
            @else
                <div class="bank-1c-titlebar">
                    <h2>Кассовая смена — открытие</h2>
                </div>

                <form method="POST" action="{{ route('admin.cash-shifts.store') }}" class="contents">
                    @csrf

                    @if ($accountsForOpenShift !== [])
                        <div class="bank-1c-toolbar">
                            <button type="submit" class="bank-1c-tb-btn bank-1c-tb-btn-primary">Открыть смену</button>
                        </div>
                    @endif

                    <div class="bank-1c-shift-form">
                        @if ($accountsForOpenShift === [])
                            <div class="bank-1c-banner-warn !mb-0">
                                Нет счетов в «Данные организации» для этого филиала. Добавьте счета (банк / касса), затем откройте смену.
                            </div>
                        @else
                            <ul class="m-0 list-none space-y-3 p-0">
                                @foreach ($accountsForOpenShift as $acc)
                                    <li class="bank-1c-field-row">
                                        <label class="bank-1c-field-label" for="opening_acc_{{ $acc['id'] }}">{{ $acc['label'] }}</label>
                                        <input
                                            id="opening_acc_{{ $acc['id'] }}"
                                            name="opening_by_account[{{ $acc['id'] }}]"
                                            type="text"
                                            value="{{ old('opening_by_account.'.$acc['id']) }}"
                                            inputmode="decimal"
                                            placeholder="0,00"
                                            required
                                            autocomplete="off"
                                        />
                                        <x-input-error class="mt-1" :messages="$errors->get('opening_by_account.'.$acc['id'])" />
                                    </li>
                                @endforeach
                            </ul>
                            <x-input-error class="mt-2" :messages="$errors->get('opening_by_account')" />
                        @endif
                        <div class="bank-1c-field-row @if ($accountsForOpenShift === []) hidden @endif">
                            <label class="bank-1c-field-label" for="open_note">Комментарий (необязательно)</label>
                            <textarea
                                id="open_note"
                                name="open_note"
                                rows="2"
                                placeholder="При необходимости"
                            >{{ old('open_note') }}</textarea>
                            <x-input-error class="mt-1" :messages="$errors->get('open_note')" />
                        </div>
                    </div>
                </form>

                <div class="bank-1c-foot">
                    <a href="{{ route('admin.organizations.index') }}">Данные организации — счета</a>
                </div>
            @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
