@php
    use App\Models\CashMovement;
    use App\Support\InvoiceNakladnayaFormatter;

    $kindTitle = static function (string $kind): string {
        return match ($kind) {
            CashMovement::KIND_INCOME_CLIENT => 'Приход: оплата от клиента',
            CashMovement::KIND_EXPENSE_SUPPLIER => 'Расход: оплата поставщику',
            CashMovement::KIND_EXPENSE_OTHER => 'Расход: прочие',
            CashMovement::KIND_TRANSFER => 'Перевод между счетами',
            default => $kind,
        };
    };
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-6xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <a
                href="{{ route('admin.reports.shift-report') }}"
                class="text-sm font-semibold text-emerald-700 hover:text-emerald-900 hover:underline"
            >
                ← К списку смен
            </a>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
            <div
                class="border-b border-emerald-900/10 px-4 py-3 text-white sm:px-5"
                style="background: linear-gradient(120deg, #047857 0%, #0d9488 50%, #0f766e 100%);"
            >
                <h1 class="text-sm font-bold tracking-tight">Сменный отчёт · № {{ $shift->id }}</h1>
                <p class="mt-1 text-[11px] font-medium text-emerald-100/90">
                    Кассир: {{ $shift->user?->name ?? '—' }} · операционный день: {{ $shift->business_date?->format('d.m.Y') }}
                </p>
            </div>

            <div class="grid gap-2 border-b border-slate-100 px-4 py-3 text-sm text-slate-700 sm:grid-cols-2 sm:px-5">
                <div>
                    <span class="text-slate-500">Открыта:</span>
                    <span class="font-medium">{{ $shift->opened_at?->format('d.m.Y H:i:s') }}</span>
                </div>
                <div>
                    <span class="text-slate-500">Закрыта:</span>
                    <span class="font-medium">{{ $shift->closed_at ? $shift->closed_at->format('d.m.Y H:i:s') : 'смена ещё открыта' }}</span>
                </div>
                @if ($shift->open_note)
                    <div class="sm:col-span-2">
                        <span class="text-slate-500">Комментарий при открытии:</span>
                        {{ $shift->open_note }}
                    </div>
                @endif
                @if ($shift->close_note)
                    <div class="sm:col-span-2">
                        <span class="text-slate-500">Комментарий при закрытии:</span>
                        {{ $shift->close_note }}
                    </div>
                @endif
            </div>

            <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
                <h2 class="text-xs font-bold uppercase tracking-wide text-slate-600">Сводка по видам операций</h2>
                <p class="mt-0.5 text-[11px] text-slate-500">Тот же учёт, что и при закрытии смены: розница и операции по времени создания записи в интервале смены.</p>
                <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-lg bg-slate-50 px-3 py-2">
                        <dt class="text-[10px] font-bold uppercase text-slate-500">Розница (чеков)</dt>
                        <dd class="font-semibold text-slate-900">{{ $kindBreakdown['retail_checks'] }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-3 py-2">
                        <dt class="text-[10px] font-bold uppercase text-slate-500">Оплаты по чекам</dt>
                        <dd class="font-semibold tabular-nums text-slate-900">{{ InvoiceNakladnayaFormatter::formatMoney($kindBreakdown['retail_payments']) }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-3 py-2">
                        <dt class="text-[10px] font-bold uppercase text-slate-500">Возвраты покупателям</dt>
                        <dd class="font-semibold tabular-nums text-slate-900">{{ InvoiceNakladnayaFormatter::formatMoney($kindBreakdown['refunds']) }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-3 py-2">
                        <dt class="text-[10px] font-bold uppercase text-slate-500">Приход (клиент, вручную)</dt>
                        <dd class="font-semibold tabular-nums text-slate-900">{{ InvoiceNakladnayaFormatter::formatMoney($kindBreakdown['income_client']) }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-3 py-2">
                        <dt class="text-[10px] font-bold uppercase text-slate-500">Расход поставщику</dt>
                        <dd class="font-semibold tabular-nums text-slate-900">{{ InvoiceNakladnayaFormatter::formatMoney($kindBreakdown['expense_supplier']) }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-3 py-2">
                        <dt class="text-[10px] font-bold uppercase text-slate-500">Прочие расходы</dt>
                        <dd class="font-semibold tabular-nums text-slate-900">{{ InvoiceNakladnayaFormatter::formatMoney($kindBreakdown['expense_other']) }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-3 py-2 sm:col-span-2 lg:col-span-1">
                        <dt class="text-[10px] font-bold uppercase text-slate-500">Переводы (объём)</dt>
                        <dd class="font-semibold tabular-nums text-slate-900">{{ InvoiceNakladnayaFormatter::formatMoney($kindBreakdown['transfer_volume']) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="px-4 py-3 sm:px-5">
                <h2 class="text-xs font-bold uppercase tracking-wide text-slate-600">По счетам (начало + движение → ожидается)</h2>
                <div class="mt-2 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Счёт</th>
                                <th class="px-3 py-2 text-right">На начало</th>
                                <th class="px-3 py-2 text-right">Движение</th>
                                <th class="px-3 py-2 text-right">Ожидается</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($closingTable['rows'] as $row)
                                <tr class="hover:bg-emerald-50/20">
                                    <td class="px-3 py-2 text-slate-900">{{ $row['label'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-slate-700">
                                        @if ($row['opening'] !== null)
                                            {{ InvoiceNakladnayaFormatter::formatMoney($row['opening']) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums text-slate-900">{{ InvoiceNakladnayaFormatter::formatMoney($row['movement']) }}</td>
                                    <td class="px-3 py-2 text-right font-medium tabular-nums text-slate-900">
                                        @if ($row['expected'] !== null)
                                            {{ InvoiceNakladnayaFormatter::formatMoney($row['expected']) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-slate-200 bg-slate-50/90 text-sm font-semibold">
                            <tr>
                                <td class="px-3 py-2 text-slate-700">Итого</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ InvoiceNakladnayaFormatter::formatMoney($closingTable['totals']['opening']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ InvoiceNakladnayaFormatter::formatMoney($closingTable['totals']['movement']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ InvoiceNakladnayaFormatter::formatMoney($closingTable['totals']['expected']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            @if ($shift->closed_at !== null && $closingFactRows !== [])
                <div class="border-t border-slate-100 px-4 py-3 sm:px-5">
                    <h2 class="text-xs font-bold uppercase tracking-wide text-slate-600">Сдано при закрытии</h2>
                    <div class="mt-2 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-2">Счёт</th>
                                    <th class="px-3 py-2 text-right">Сумма</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($closingFactRows as $row)
                                    <tr>
                                        <td class="px-3 py-2 text-slate-900">{{ $row['label'] }}</td>
                                        <td class="px-3 py-2 text-right font-medium tabular-nums">{{ InvoiceNakladnayaFormatter::formatMoney($row['amount']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="border-t border-slate-100 px-4 py-3 sm:px-5">
                <h2 class="text-xs font-bold uppercase tracking-wide text-slate-600">Розничные чеки за смену</h2>
                <div class="mt-2 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2">№</th>
                                <th class="px-3 py-2">Дата док.</th>
                                <th class="px-3 py-2">Создан</th>
                                <th class="px-3 py-2 text-right">Сумма чека</th>
                                <th class="px-3 py-2">Оплаты</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($retailSales as $sale)
                                @php
                                    $payParts = [];
                                    foreach ($sale->payments as $p) {
                                        $lbl = $p->organizationBankAccount?->movementReportLabel() ?? '—';
                                        $payParts[] = $lbl . ': ' . InvoiceNakladnayaFormatter::formatMoney((float) $p->amount);
                                    }
                                @endphp
                                <tr class="hover:bg-emerald-50/15">
                                    <td class="px-3 py-2 font-medium text-slate-900">{{ $sale->id }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $sale->document_date?->format('d.m.Y') }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $sale->created_at?->format('d.m.Y H:i') }}</td>
                                    <td class="px-3 py-2 text-right font-medium tabular-nums">{{ InvoiceNakladnayaFormatter::formatMoney((float) $sale->total_amount) }}</td>
                                    <td class="max-w-xs px-3 py-2 text-xs text-slate-600">{{ $payParts !== [] ? implode(' · ', $payParts) : '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-8 text-center text-slate-500">Нет чеков за смену.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="border-t border-slate-100 px-4 py-3 sm:px-5">
                <h2 class="text-xs font-bold uppercase tracking-wide text-slate-600">Возвраты (розница)</h2>
                <div class="mt-2 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Время</th>
                                <th class="px-3 py-2">Чек</th>
                                <th class="px-3 py-2">Счёт</th>
                                <th class="px-3 py-2 text-right">Сумма</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($refunds as $refund)
                                <tr>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $refund->created_at?->format('d.m.Y H:i') }}</td>
                                    <td class="px-3 py-2 text-slate-900">№ {{ $refund->retail_sale_id }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ $refund->organizationBankAccount?->movementReportLabel() ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right font-medium tabular-nums">{{ InvoiceNakladnayaFormatter::formatMoney((float) $refund->amount) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-8 text-center text-slate-500">Нет возвратов за смену.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="border-t border-slate-100 px-4 py-3 sm:px-5">
                <h2 class="text-xs font-bold uppercase tracking-wide text-slate-600">Банк и касса (операции кассира)</h2>
                <div class="mt-2 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50/95 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Время</th>
                                <th class="px-3 py-2">Тип</th>
                                <th class="px-3 py-2">Счета / контрагент</th>
                                <th class="px-3 py-2 text-right">Сумма</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($cashMovements as $m)
                                @php
                                    $accLabel = '—';
                                    if ($m->kind === CashMovement::KIND_TRANSFER) {
                                        $accLabel = ($m->fromAccount?->movementReportLabel() ?? '—') . ' → ' . ($m->toAccount?->movementReportLabel() ?? '—');
                                    } elseif ($m->ourAccount) {
                                        $accLabel = $m->ourAccount->movementReportLabel();
                                    }
                                    $extra = $m->counterparty ? $m->counterparty->name : trim((string) $m->comment);
                                @endphp
                                <tr>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $m->created_at?->format('d.m.Y H:i') }}</td>
                                    <td class="px-3 py-2 text-slate-900">{{ $kindTitle($m->kind) }}</td>
                                    <td class="max-w-md px-3 py-2 text-xs text-slate-600">
                                        {{ $accLabel }}
                                        @if ($extra !== '')
                                            <span class="block text-slate-500">{{ $extra }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right font-medium tabular-nums">{{ InvoiceNakladnayaFormatter::formatMoney((float) $m->amount) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-8 text-center text-slate-500">Нет ручных операций за смену.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
