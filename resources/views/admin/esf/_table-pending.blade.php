@php
    $esfFilter = $esfFilter ?? ['date_from' => '', 'date_to' => ''];
    $esfLinesExcelPreviewUrl = route('admin.esf.lines-excel-preview');
    $mergeFormId = 'esf-pending-merge-form';
    $bulkSubmittedFormId = 'esf-pending-bulk-submitted-form';
    $bulkUnqueueFormId = 'esf-pending-bulk-unqueue-form';
    $excelFormId = 'esf-pending-excel-form';
@endphp
<div
    id="esf-pending-merge-panel"
    class="space-y-3"
    x-data="{
        mergeOrgs: {{ \Illuminate\Support\Js::from($orgsPayload) }},
        mergeOrgId: '{{ (int) $defaultOrgId }}',
        mergePaymentKind: 'cash',
        mergeAccountId: '',
        mergeCurrentOrg() {
            const id = String(this.mergeOrgId ?? '');
            return this.mergeOrgs.find((o) => String(o.id) === id) || this.mergeOrgs[0];
        },
        mergeBankAccounts() {
            return (this.mergeCurrentOrg()?.accounts || []).filter((a) => a.type !== 'cash');
        },
        mergeCashAccounts() {
            return (this.mergeCurrentOrg()?.accounts || []).filter((a) => a.type === 'cash');
        },
        onMergeBarOrgChange() {
            this.mergeAccountId = '';
            if (this.mergePaymentKind === 'bank') {
                const b = this.mergeBankAccounts();
                this.mergeAccountId = b[0] ? String(b[0].id) : '';
            }
        },
        onMergeBarPayChange() {
            if (this.mergePaymentKind === 'bank') {
                const b = this.mergeBankAccounts();
                this.mergeAccountId = b[0] ? String(b[0].id) : '';
            } else {
                this.mergeAccountId = '';
            }
        },
    }"
    x-init="onMergeBarPayChange()"
>
    <form id="{{ $mergeFormId }}" method="POST" action="{{ route('admin.esf.xml-merge') }}" class="hidden">
        @csrf
        <input type="hidden" name="organization_id" id="esfMergeOrg" value="" />
        <input type="hidden" name="payment_kind" id="esfMergePay" value="" />
        <input type="hidden" name="organization_bank_account_id" id="esfMergeAcc" value="" />
        <input type="hidden" name="esf_lines" id="esfMergeLines" value="" />
        <input type="hidden" name="date_from" value="{{ $esfFilter['date_from'] ?? '' }}" />
        <input type="hidden" name="date_to" value="{{ $esfFilter['date_to'] ?? '' }}" />
        <div id="esfMergeOrgIdsMount" class="hidden" aria-hidden="true"></div>
    </form>
    <form id="{{ $bulkSubmittedFormId }}" method="POST" action="{{ route('admin.esf.submitted.bulk') }}" class="hidden">
        @csrf
        <input type="hidden" name="date_from" value="{{ $esfFilter['date_from'] ?? '' }}" />
        <input type="hidden" name="date_to" value="{{ $esfFilter['date_to'] ?? '' }}" />
        <div id="esfBulkSubmittedItemsMount" class="hidden" aria-hidden="true"></div>
    </form>
    <form id="{{ $bulkUnqueueFormId }}" method="POST" action="{{ route('admin.esf.unqueue.bulk') }}" class="hidden">
        @csrf
        <input type="hidden" name="date_from" value="{{ $esfFilter['date_from'] ?? '' }}" />
        <input type="hidden" name="date_to" value="{{ $esfFilter['date_to'] ?? '' }}" />
        <div id="esfBulkUnqueueItemsMount" class="hidden" aria-hidden="true"></div>
    </form>
    <form id="{{ $excelFormId }}" method="POST" action="{{ route('admin.esf.lines-excel') }}" class="hidden">
        @csrf
        <div id="esfExcelItemsMount" class="hidden" aria-hidden="true"></div>
    </form>
    <table class="w-full min-w-[1100px] border-collapse border border-slate-300 text-sm">
    <thead>
        <tr class="bg-slate-100">
            <th class="border border-slate-300 w-9 px-1 py-2 text-center text-[9px] font-semibold uppercase tracking-wide text-slate-600" title="Отметка строк для XML и для действий внизу таблицы">XML</th>
            <th class="border border-slate-300 px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-700">Дата</th>
            <th
                class="border border-slate-300 border-b-2 border-b-amber-500 bg-amber-100 px-2 py-2.5 text-left text-[10px] font-extrabold uppercase tracking-wider text-amber-950 shadow-inner"
            >
                Покупатель
            </th>
            <th class="border border-slate-300 px-2 py-2 text-right text-[10px] font-semibold uppercase tracking-wide text-slate-700">Сумма</th>
            <th
                class="border border-slate-300 border-b-2 border-b-slate-500 bg-sky-100 px-2 py-2.5 text-left text-[10px] font-extrabold uppercase tracking-wider text-slate-800 whitespace-nowrap shadow-inner"
            >
                Товары / услуги
            </th>
            <th class="border border-slate-300 px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-700">Организация</th>
            <th class="border border-slate-300 px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-700">Оплата</th>
            <th class="border border-slate-300 px-2 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-700">Счёт</th>
            <th class="border border-slate-300 px-2 py-2 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-700">Действия</th>
        </tr>
    </thead>
    <tbody class="bg-white">
        @foreach ($sales as $row)
            @php
                $sale = $row->sale;
                $esfKind = $row->esf_lines;
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
                    esfLines: @js($esfKind),
                    documentUrl: @js(route('admin.legal-entity-sales.edit', $sale)),
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
                        p.set('esf_lines', this.esfLines);
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
                    onEsfAction(e) {
                        const sel = e.target;
                        const v = sel.value;
                        sel.value = '';
                        if (!v) return;
                        if (v === 'xml') {
                            if (!this.canDownload()) {
                                alert('Для выгрузки XML выберите организацию и для безнала — банковский счёт.');
                                return;
                            }
                            window.location.href = this.xmlHref();
                            return;
                        }
                        if (v === 'submitted') {
                            if (confirm('Отметить, что ЭСФ уже записана в налоговой? Повторная выгрузка будет недоступна, пока не снять отметку.')) {
                                this.$refs.formEsfSubmitted.submit();
                            }
                            return;
                        }
                        if (v === 'unqueue') {
                            if (confirm('Убрать эту часть из очереди на ЭСФ? Снова можно отметить из списка выше.')) {
                                this.$refs.formEsfUnqueue.submit();
                            }
                            return;
                        }
                        if (v === 'document') {
                            window.location.href = this.documentUrl;
                        }
                    },
                }"
            >
                <td class="border border-slate-300 px-1 py-2 text-center align-middle">
                    <input
                        type="checkbox"
                        class="esf-merge-cb h-4 w-4 rounded border-slate-300 text-slate-600"
                        name="merge_items[]"
                        value="{{ $sale->id }}:{{ $esfKind }}"
                        form="{{ $mergeFormId }}"
                        title="Отметить для массовых действий и XML"
                    />
                </td>
                <td class="border border-slate-300 whitespace-nowrap px-2 py-2 text-slate-900">{{ $sale->document_date->format('d.m.Y') }}</td>
                <td
                    class="max-w-xs border border-slate-300 border-l-4 border-l-amber-500 bg-gradient-to-b from-amber-50/95 to-amber-50/30 px-2 py-2.5 align-top shadow-[inset_0_0_0_1px_rgba(245,158,11,0.1)]"
                >
                    <span class="line-clamp-3 break-words text-sm font-semibold leading-snug text-slate-900">
                        {{ $sale->buyer_name !== '' ? $sale->buyer_name : '—' }}
                    </span>
                </td>
                <td class="border border-slate-300 whitespace-nowrap px-2 py-2 text-right tabular-nums text-slate-900">{{ $fmt($sum) }}</td>
                <td
                    class="border border-slate-300 border-l-4 border-l-sky-500 bg-gradient-to-b from-sky-50/95 to-sky-50/40 px-2 py-2.5 align-middle shadow-[inset_0_0_0_1px_rgba(14,165,233,0.12)]"
                >
                    @if ($esfKind === 'goods')
                        <span
                            class="inline-flex max-w-full items-center rounded-md border-2 border-sky-500 bg-sky-100 px-2.5 py-1.5 text-xs font-extrabold uppercase leading-tight tracking-wide text-sky-950 shadow-sm ring-1 ring-sky-200/80 sm:px-3 sm:py-2 sm:text-sm"
                            title="Товары (номенклатура не услуга)"
                        >
                            Товары
                        </span>
                    @else
                        <span
                            class="inline-flex max-w-full items-center rounded-md border-2 border-violet-500 bg-violet-100 px-2.5 py-1.5 text-xs font-extrabold uppercase leading-tight tracking-wide text-violet-950 shadow-sm ring-1 ring-violet-200/80 sm:px-3 sm:py-2 sm:text-sm"
                            title="Услуги"
                        >
                            Услуги
                        </span>
                    @endif
                </td>
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
                <td class="border border-slate-300 px-2 py-2">
                    <form x-ref="formEsfSubmitted" method="POST" action="{{ route('admin.esf.submitted', $sale) }}" class="hidden">
                        @csrf
                        <input type="hidden" name="esf_lines" value="{{ $esfKind }}" />
                        <input type="hidden" name="date_from" value="{{ $esfFilter['date_from'] ?? '' }}" />
                        <input type="hidden" name="date_to" value="{{ $esfFilter['date_to'] ?? '' }}" />
                    </form>
                    <form x-ref="formEsfUnqueue" method="POST" action="{{ route('admin.esf.unqueue', $sale) }}" class="hidden">
                        @csrf
                        <input type="hidden" name="esf_lines" value="{{ $esfKind }}" />
                        <input type="hidden" name="date_from" value="{{ $esfFilter['date_from'] ?? '' }}" />
                        <input type="hidden" name="date_to" value="{{ $esfFilter['date_to'] ?? '' }}" />
                    </form>
                    <select
                        @change="onEsfAction($event)"
                        class="w-full min-w-[10rem] max-w-[16rem] rounded border border-slate-300 bg-white py-1.5 pl-2 pr-6 text-xs text-slate-900"
                        aria-label="Действия по ЭСФ"
                    >
                        <option value="">— действие —</option>
                        <option value="xml">Скачать XML</option>
                        <option value="submitted">Записано в ЭСФ</option>
                        <option value="unqueue">Убрать из очереди</option>
                        <option value="document">Документ</option>
                    </select>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
    <div class="space-y-3 rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-3">
        <p class="text-xs text-slate-600">
            Галочки в колонке XML, затем реквизиты ниже и действие в списке справа (как «Действия» в строке). <strong>Организация, оплата, счёт</strong> — для скачивания XML: одна отмеченная строка (один файл) или две и более однотипных (один объединённый XML). <strong>Скачать Excel</strong> — список наименований и единиц по отмеченным документам (только товары или только услуги, без смешивания). В строках таблицы справа — отдельно по каждой строке.
        </p>
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:flex-wrap">
            <div class="min-w-[12rem] max-w-sm flex-1">
                <label for="esfMergeBarOrg" class="mb-1 block text-[11px] font-semibold text-slate-600">Организация (для XML по отмеченным)</label>
                <select
                    id="esfMergeBarOrg"
                    x-model="mergeOrgId"
                    @change="onMergeBarOrgChange()"
                    class="w-full rounded border border-slate-300 bg-white py-2 pl-2 pr-8 text-sm text-slate-900"
                >
                    @foreach ($organizations as $o)
                        <option value="{{ $o->id }}">{{ $o->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-full min-w-[10rem] max-w-xs lg:max-w-[11rem]">
                <label for="esfMergeBarPay" class="mb-1 block text-[11px] font-semibold text-slate-600">Оплата</label>
                <select
                    id="esfMergeBarPay"
                    x-model="mergePaymentKind"
                    @change="onMergeBarPayChange()"
                    class="w-full rounded border border-slate-300 bg-white py-2 pl-2 pr-8 text-sm text-slate-900"
                >
                    <option value="cash">Наличные</option>
                    <option value="bank">Безнал (банк)</option>
                </select>
            </div>
            <div class="min-w-[12rem] max-w-md flex-1">
                <span class="mb-1 block text-[11px] font-semibold text-slate-600">Счёт организации</span>
                <div class="space-y-1">
                    <template x-if="mergePaymentKind === 'bank'">
                        <div>
                            <select
                                x-model="mergeAccountId"
                                class="w-full min-w-[12rem] rounded border border-slate-300 bg-white py-2 pl-2 pr-8 text-sm text-slate-900"
                            >
                                <option value="">— выберите счёт —</option>
                                <template x-for="acc in mergeBankAccounts()" :key="acc.id">
                                    <option :value="acc.id" x-text="acc.label"></option>
                                </template>
                            </select>
                            <p x-show="mergeBankAccounts().length === 0" class="mt-1 text-[10px] text-red-600">Нет банковских счетов — добавьте в карточке организации.</p>
                        </div>
                    </template>
                    <template x-if="mergePaymentKind === 'cash'">
                        <div>
                            <select
                                x-model="mergeAccountId"
                                class="w-full min-w-[12rem] rounded border border-slate-300 bg-white py-2 pl-2 pr-8 text-sm text-slate-900"
                            >
                                <option value="">— без уточнения —</option>
                                <template x-for="acc in mergeCashAccounts()" :key="acc.id">
                                    <option :value="acc.id" x-text="acc.label"></option>
                                </template>
                            </select>
                        </div>
                    </template>
                </div>
            </div>
            <div class="flex w-full min-w-[12rem] max-w-sm flex-1 flex-col gap-1 pt-1 lg:ml-auto lg:max-w-[20rem]">
                <label for="esfPendingBulkAction" class="mb-0 block text-[11px] font-semibold text-slate-600">Действия для отмеченных</label>
                <select
                    id="esfPendingBulkAction"
                    class="w-full rounded border border-slate-300 bg-white py-2 pl-2 pr-8 text-sm text-slate-900"
                    aria-label="Действия для отмеченных строк"
                    onchange="esfPendingBulkSelectChange(this, '{{ $mergeFormId }}', '{{ $bulkSubmittedFormId }}', '{{ $bulkUnqueueFormId }}', '{{ $excelFormId }}')"
                >
                    <option value="">— действие —</option>
                    <option value="xml">Скачать XML</option>
                    <option value="excel">Скачать Excel (позиции)</option>
                    <option value="submitted">Записано в ЭСФ</option>
                    <option value="unqueue">Убрать из очереди</option>
                    <option value="document">Документ</option>
                </select>
            </div>
        </div>
    </div>
</div>
<script>
    function esfGetMergeFormCheckedCbs(form) {
        return Array.from(form.elements).filter(
            (el) => el instanceof HTMLInputElement && el.classList.contains('esf-merge-cb') && el.checked
        );
    }

    function esfSortCbsByTableOrder(cbs) {
        return cbs.sort((a, b) => {
            const ra = a.closest('tr');
            const rb = b.closest('tr');
            if (!ra || !rb) {
                return 0;
            }
            const pos = ra.compareDocumentPosition(rb);
            if (pos & Node.DOCUMENT_POSITION_FOLLOWING) {
                return -1;
            }
            if (pos & Node.DOCUMENT_POSITION_PRECEDING) {
                return 1;
            }
            return 0;
        });
    }

    function esfBuildXmlHrefWithBar(xmlBase, esfLines, bar) {
        const p = new URLSearchParams();
        p.set('organization_id', String(bar.mergeOrgId));
        p.set('payment_kind', String(bar.mergePaymentKind));
        p.set('esf_lines', String(esfLines));
        if (String(bar.mergePaymentKind) === 'bank' && bar.mergeAccountId) {
            p.set('organization_bank_account_id', String(bar.mergeAccountId));
        }
        if (String(bar.mergePaymentKind) === 'cash' && bar.mergeAccountId) {
            p.set('organization_bank_account_id', String(bar.mergeAccountId));
        }
        return xmlBase + '?' + p.toString();
    }

    function esfBarCanDownloadXml(bar) {
        if (!bar || String(bar.mergeOrgId ?? '') === '' || String(bar.mergeOrgId) === '0') {
            return false;
        }
        if (String(bar.mergePaymentKind) === 'bank') {
            const b = typeof bar.mergeBankAccounts === 'function' ? bar.mergeBankAccounts() : [];
            return b.length > 0 && String(bar.mergeAccountId) !== '';
        }
        return true;
    }

    function esfPendingBulkActionXml(mergeFormId) {
        const form = document.getElementById(mergeFormId);
        const panel = document.getElementById('esf-pending-merge-panel');
        if (!form || !window.Alpine) {
            window.alert('Ошибка. Обновите страницу.');
            return;
        }
        const bar = panel && window.Alpine.$data(panel);
        if (!bar) {
            window.alert('Не удалось прочитать настройки под таблицей.');
            return;
        }
        const cbs = esfSortCbsByTableOrder(esfGetMergeFormCheckedCbs(form));
        if (cbs.length < 1) {
            window.alert('Отметьте строки в колонке XML.');
            return;
        }
        if (cbs.length >= 2) {
            esfSubmitPendingMerge(mergeFormId);
            return;
        }
        if (!esfBarCanDownloadXml(bar)) {
            window.alert('Для выгрузки XML выберите в блоке ниже организацию и для безнала — банковский счёт.');
            return;
        }
        const d = window.Alpine.$data(cbs[0].closest('tr'));
        if (!d || !d.xmlBase) {
            window.alert('Ошибка в данных строки.');
            return;
        }
        window.location.href = esfBuildXmlHrefWithBar(d.xmlBase, d.esfLines, bar);
    }

    function esfSubmitPendingBulkUnqueue(mergeFormId, bulkFormId) {
        const mergeForm = document.getElementById(mergeFormId);
        const bulkForm = document.getElementById(bulkFormId);
        if (!mergeForm || !bulkForm) {
            window.alert('Не удалось отправить форму. Обновите страницу.');
            return;
        }
        const cbs = esfSortCbsByTableOrder(esfGetMergeFormCheckedCbs(mergeForm));
        if (cbs.length < 1) {
            window.alert('Отметьте хотя бы одну строку (галочка в колонке XML).');
            return;
        }
        if (!window.confirm('Убрать выбранные части (' + cbs.length + ' шт.) из очереди на ЭСФ? Снова можно отметить из списка выше.')) {
            return;
        }
        const mount = bulkForm.querySelector('#esfBulkUnqueueItemsMount');
        mount.querySelectorAll('input').forEach((n) => n.remove());
        cbs.forEach((cb) => {
            const h = document.createElement('input');
            h.type = 'hidden';
            h.name = 'unqueue_items[]';
            h.value = cb.value;
            mount.appendChild(h);
        });
        bulkForm.submit();
    }

    function esfPendingBulkActionDocuments(mergeFormId) {
        const form = document.getElementById(mergeFormId);
        if (!form) {
            return;
        }
        const cbs = esfSortCbsByTableOrder(esfGetMergeFormCheckedCbs(form));
        if (cbs.length < 1) {
            window.alert('Отметьте строки в колонке XML.');
            return;
        }
        const seen = new Set();
        cbs.forEach((cb) => {
            const d = window.Alpine.$data(cb.closest('tr'));
            const u = d && d.documentUrl ? String(d.documentUrl) : '';
            if (u === '' || seen.has(u)) {
                return;
            }
            seen.add(u);
            window.open(u, '_blank', 'noopener,noreferrer');
        });
    }

    function esfPendingBulkSelectChange(el, mergeFormId, bulkSubmittedFormId, bulkUnqueueFormId, excelFormId) {
        const v = el.value;
        el.value = '';
        if (!v) {
            return;
        }
        if (v === 'xml') {
            esfPendingBulkActionXml(mergeFormId);
            return;
        }
        if (v === 'excel') {
            esfSubmitPendingExcel(mergeFormId, excelFormId);
            return;
        }
        if (v === 'submitted') {
            esfSubmitPendingBulkSubmitted(mergeFormId, bulkSubmittedFormId);
            return;
        }
        if (v === 'unqueue') {
            esfSubmitPendingBulkUnqueue(mergeFormId, bulkUnqueueFormId);
            return;
        }
        if (v === 'document') {
            esfPendingBulkActionDocuments(mergeFormId);
            return;
        }
    }

    const esfLinesExcelPreviewUrl = {!! json_encode($esfLinesExcelPreviewUrl) !!};

    async function esfSubmitPendingExcel(mergeFormId, formId) {
        const mergeForm = document.getElementById(mergeFormId);
        const f = document.getElementById(formId);
        if (!mergeForm || !f) {
            window.alert('Не удалось отправить форму. Обновите страницу.');
            return;
        }
        const cbs = esfSortCbsByTableOrder(esfGetMergeFormCheckedCbs(mergeForm));
        if (cbs.length < 1) {
            window.alert('Отметьте строки в колонке XML.');
            return;
        }
        const kinds = new Set();
        for (const cb of cbs) {
            const d = window.Alpine.$data(cb.closest('tr'));
            if (d) {
                kinds.add(String(d.esfLines));
            }
        }
        if (kinds.size > 1) {
            window.alert('Для Excel выберите только товары или только услуги (однотипные отмеченные строки).');
            return;
        }
        const mount = f.querySelector('#esfExcelItemsMount');
        mount.querySelectorAll('input').forEach((n) => n.remove());
        cbs.forEach((cb) => {
            const h = document.createElement('input');
            h.type = 'hidden';
            h.name = 'excel_items[]';
            h.value = cb.value;
            mount.appendChild(h);
        });
        const formData = new FormData(f);
        try {
            const res = await fetch(esfLinesExcelPreviewUrl, {
                method: 'POST',
                body: formData,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await res.json().catch(function () { return {}; });
            if (!res.ok) {
                const msg = data && data.message
                    ? data.message
                    : (res.status === 422 && data && data.errors
                        ? Object.values(data.errors).flat().join(' ')
                        : 'Не удалось подготовить выгрузку.');
                window.alert(msg);
                return;
            }
            if (data.missing > 0) {
                const codeLabel = data.kind === 'services' ? 'ГКЭД' : 'ТНВЭД';
                const text =
                    'У ' + data.missing + ' из ' + data.total + ' позиций нет кода ' + codeLabel + '. ' +
                    'Добавьте коды в «ТНВЭД / ГКЭД коды» (Бухгалтерия).\\n\\n' +
                    'Скачать Excel всё равно? (у позиций без кода в файле ячейка будет пустой).';
                if (!window.confirm(text)) {
                    return;
                }
            }
        } catch (e) {
            window.alert('Сеть: не удалось проверить позиции. Повторите.');
            return;
        }
        f.submit();
    }

    function esfSubmitPendingBulkSubmitted(mergeFormId, bulkFormId) {
        const mergeForm = document.getElementById(mergeFormId);
        const bulkForm = document.getElementById(bulkFormId);
        if (!mergeForm || !bulkForm) {
            window.alert('Не удалось отправить форму. Обновите страницу.');
            return;
        }
        const cbs = esfSortCbsByTableOrder(esfGetMergeFormCheckedCbs(mergeForm));
        if (cbs.length < 1) {
            window.alert('Отметьте хотя бы одну строку (галочка в колонке XML).');
            return;
        }
        if (
            !window.confirm(
                'Отметить для всех выбранных строк (' +
                    cbs.length +
                    '), что ЭСФ уже записана в налоговой? Повторная выгрузка будет недоступна, пока не снять отметку.'
            )
        ) {
            return;
        }
        const mount = bulkForm.querySelector('#esfBulkSubmittedItemsMount');
        mount.querySelectorAll('input').forEach((n) => n.remove());
        cbs.forEach((cb) => {
            const h = document.createElement('input');
            h.type = 'hidden';
            h.name = 'submitted_items[]';
            h.value = cb.value;
            mount.appendChild(h);
        });
        bulkForm.submit();
    }

    function esfSubmitPendingMerge(formId) {
        const form = document.getElementById(formId);
        if (!form || !window.Alpine) {
            window.alert('Не удалось подготовить выгрузку. Обновите страницу.');
            return;
        }
        // Чекбоксы вне <form> с form="…" не являются потомками form — querySelectorAll их не видит; form.elements — видит.
        const cbs = esfSortCbsByTableOrder(esfGetMergeFormCheckedCbs(form));
        if (cbs.length < 2) {
            window.alert('Отметьте не меньше двух строк для объединения.');
            return;
        }
        const panel = document.getElementById('esf-pending-merge-panel');
        const bar = panel && window.Alpine ? window.Alpine.$data(panel) : null;
        if (!bar || bar.mergeOrgId === undefined) {
            window.alert('Не удалось прочитать настройки под таблицей. Обновите страницу.');
            return;
        }
        const orgIdBar = String(bar.mergeOrgId ?? '');
        if (!orgIdBar || orgIdBar === '0') {
            window.alert('В блоке под таблицей выберите организацию-продавца.');
            return;
        }
        const payBar = String(bar.mergePaymentKind ?? 'cash');
        const accBar = String(bar.mergeAccountId ?? '');
        if (payBar === 'bank') {
            const b = typeof bar.mergeBankAccounts === 'function' ? bar.mergeBankAccounts() : [];
            if (b.length === 0 || accBar === '') {
                window.alert('В блоке под таблицей для «Безнал (банк)» выберите банковский счёт организации.');
                return;
            }
        }
        const kinds = new Set();
        for (const cb of cbs) {
            const tr = cb.closest('tr');
            const d = window.Alpine.$data(tr);
            if (!d) {
                window.alert('Ошибка в данных строки.');
                return;
            }
            kinds.add(String(d.esfLines));
        }
        if (kinds.size > 1) {
            window.alert('В одном файле нельзя смешивать товары и услуги. Отметьте только строки «Товары» или только «Услуги».');
            return;
        }
        const first = cbs[0].closest('tr');
        const d0 = window.Alpine.$data(first);
        const mount = form.querySelector('#esfMergeOrgIdsMount');
        mount.querySelectorAll('input[name=\'merge_org_ids[]\']').forEach((n) => n.remove());
        cbs.forEach(() => {
            const h = document.createElement('input');
            h.type = 'hidden';
            h.name = 'merge_org_ids[]';
            h.value = orgIdBar;
            mount.appendChild(h);
        });
        document.getElementById('esfMergeOrg').value = orgIdBar;
        document.getElementById('esfMergePay').value = payBar;
        document.getElementById('esfMergeAcc').value = accBar;
        document.getElementById('esfMergeLines').value = String(d0.esfLines);
        form.submit();
    };
</script>
