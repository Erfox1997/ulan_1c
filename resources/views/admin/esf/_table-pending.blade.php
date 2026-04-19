@php
    $esfFilter = $esfFilter ?? ['date_from' => '', 'date_to' => ''];
@endphp
<table class="w-full min-w-[900px] border-collapse border border-slate-300 text-sm">
    <thead>
        <tr class="bg-slate-100">
            <th class="border border-slate-300 px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-700">Дата</th>
            <th class="border border-slate-300 px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-700">Покупатель</th>
            <th class="border border-slate-300 px-2 py-2 text-right text-[10px] font-semibold uppercase tracking-wide text-slate-700">Сумма</th>
            <th class="border border-slate-300 px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-700">Организация</th>
            <th class="border border-slate-300 px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-700">Оплата</th>
            <th class="border border-slate-300 px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-700">Счёт</th>
            <th class="border border-slate-300 px-2 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-700">Статус</th>
            <th class="border border-slate-300 px-2 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-700">Действия</th>
        </tr>
    </thead>
    <tbody class="bg-white">
        @foreach ($sales as $sale)
            @php
                $sum = $lineSum($sale);
            @endphp
            <tr
                class="align-top hover:bg-slate-50/80"
                x-data="{
                    orgId: '{{ (int) $defaultOrgId }}',
                    paymentKind: 'cash',
                    accountId: '',
                    orgs: {{ \Illuminate\Support\Js::from($orgsPayload) }},
                    xmlBase: @js(route('admin.esf.xml', $sale)),
                    currentOrg() {
                        const id = String(this.orgId ?? '');
                        return this.orgs.find(o => String(o.id) === id) || this.orgs[0];
                    },
                    bankAccounts() {
                        return (this.currentOrg()?.accounts || []).filter(a => a.type !== 'cash');
                    },
                    cashAccounts() {
                        return (this.currentOrg()?.accounts || []).filter(a => a.type === 'cash');
                    },
                    xmlHref() {
                        const p = new URLSearchParams();
                        p.set('organization_id', String(this.orgId));
                        p.set('payment_kind', this.paymentKind);
                        if (this.paymentKind === 'bank' && this.accountId) {
                            p.set('organization_bank_account_id', String(this.accountId));
                        }
                        if (this.paymentKind === 'cash' && this.accountId) {
                            p.set('organization_bank_account_id', String(this.accountId));
                        }
                        return this.xmlBase + '?' + p.toString();
                    },
                    canDownload() {
                        if (!this.orgId) return false;
                        if (this.paymentKind === 'bank') {
                            const b = this.bankAccounts();
                            return b.length > 0 && String(this.accountId) !== '';
                        }
                        return true;
                    },
                    onOrgChange() {
                        this.accountId = '';
                        if (this.paymentKind === 'bank') {
                            const b = this.bankAccounts();
                            this.accountId = b[0] ? String(b[0].id) : '';
                        }
                    },
                    onPaymentChange() {
                        if (this.paymentKind === 'bank') {
                            const b = this.bankAccounts();
                            this.accountId = b[0] ? String(b[0].id) : '';
                        } else {
                            this.accountId = '';
                        }
                    },
                }"
            >
                <td class="border border-slate-300 whitespace-nowrap px-2 py-2 text-slate-900">{{ $sale->document_date->format('d.m.Y') }}</td>
                <td class="border border-slate-300 px-2 py-2 text-slate-800">{{ $sale->buyer_name !== '' ? $sale->buyer_name : '—' }}</td>
                <td class="border border-slate-300 whitespace-nowrap px-2 py-2 text-right tabular-nums text-slate-900">{{ $fmt($sum) }}</td>
                <td class="border border-slate-300 px-2 py-2">
                    <select
                        x-model="orgId"
                        @change="onOrgChange()"
                        class="w-full min-w-[10rem] max-w-[14rem] rounded border border-slate-300 bg-white py-1 pl-2 pr-6 text-xs text-slate-900"
                    >
                        @foreach ($organizations as $o)
                            <option value="{{ $o->id }}">{{ $o->name }}</option>
                        @endforeach
                    </select>
                </td>
                <td class="border border-slate-300 px-2 py-2">
                    <select x-model="paymentKind" @change="onPaymentChange()" class="w-full rounded border border-slate-300 bg-white py-1 pl-2 pr-6 text-xs">
                        <option value="cash">Наличные</option>
                        <option value="bank">Безнал (банк)</option>
                    </select>
                </td>
                <td class="border border-slate-300 px-2 py-2">
                    <div class="space-y-1">
                        <template x-if="paymentKind === 'bank'">
                            <div>
                                <select x-model="accountId" class="w-full min-w-[12rem] max-w-[18rem] rounded border border-slate-300 bg-white py-1 pl-2 pr-6 text-xs">
                                    <option value="">— выберите счёт —</option>
                                    <template x-for="acc in bankAccounts()" :key="acc.id">
                                        <option :value="acc.id" x-text="acc.label"></option>
                                    </template>
                                </select>
                                <p x-show="bankAccounts().length === 0" class="mt-1 text-[10px] text-red-600">Нет банковских счетов — добавьте в организации.</p>
                            </div>
                        </template>
                        <template x-if="paymentKind === 'cash'">
                            <div>
                                <select x-model="accountId" class="w-full min-w-[12rem] max-w-[18rem] rounded border border-slate-300 bg-white py-1 pl-2 pr-6 text-xs">
                                    <option value="">— без уточнения —</option>
                                    <template x-for="acc in cashAccounts()" :key="acc.id">
                                        <option :value="acc.id" x-text="acc.label"></option>
                                    </template>
                                </select>
                            </div>
                        </template>
                    </div>
                </td>
                <td class="border border-slate-300 px-2 py-2 text-center text-xs text-amber-800">не записано</td>
                <td class="border border-slate-300 px-2 py-2">
                    <div class="flex flex-col gap-2">
                        <a
                            x-bind:href="canDownload() ? xmlHref() : '#'"
                            @click.prevent="if (canDownload()) window.location.href = xmlHref()"
                            class="inline-flex justify-center rounded-md border px-2 py-1.5 text-xs font-semibold"
                            :class="canDownload() ? 'border-emerald-600 bg-emerald-50 text-emerald-900 hover:bg-emerald-100' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400'"
                        >
                            Скачать XML
                        </a>
                        <form method="POST" action="{{ route('admin.esf.submitted', $sale) }}">
                            @csrf
                            <input type="hidden" name="date_from" value="{{ $esfFilter['date_from'] ?? '' }}" />
                            <input type="hidden" name="date_to" value="{{ $esfFilter['date_to'] ?? '' }}" />
                            <button
                                type="submit"
                                class="w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 text-xs font-medium text-slate-800 hover:bg-slate-50"
                                onclick="return confirm('Отметить, что ЭСФ уже записана в налоговой? Повторная выгрузка будет недоступна, пока не снять отметку.');"
                            >
                                Записано в ЭСФ
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.esf.unqueue', $sale) }}">
                            @csrf
                            <input type="hidden" name="date_from" value="{{ $esfFilter['date_from'] ?? '' }}" />
                            <input type="hidden" name="date_to" value="{{ $esfFilter['date_to'] ?? '' }}" />
                            <button
                                type="submit"
                                class="w-full rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100"
                                onclick="return confirm('Убрать документ из очереди на ЭСФ? Отметку «нужна ЭСФ» можно будет выставить снова из списка выше.');"
                            >
                                Убрать из очереди
                            </button>
                        </form>
                        <a href="{{ route('admin.legal-entity-sales.edit', $sale) }}" class="text-center text-[11px] text-emerald-700 underline">Документ</a>
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
