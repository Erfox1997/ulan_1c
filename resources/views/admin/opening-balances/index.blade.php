<x-admin-layout pageTitle="Ввод начальных остатков" main-class="px-3 py-6 sm:px-6 lg:px-8">
    @include('admin.partials.cp-brush')
    <div class="cp-root mx-auto w-full max-w-[min(100%,112rem)] space-y-6">
        @include('admin.partials.status-flash')

        @if ($warehouses->isEmpty())
            <div
                class="rounded-2xl border border-amber-200/90 bg-gradient-to-r from-amber-50 via-white to-orange-50/40 px-5 py-4 text-sm text-amber-950 shadow-sm ring-1 ring-amber-100/80"
            >
                <p class="font-semibold text-amber-950">Сначала заведите хотя бы один склад.</p>
                <p class="mt-2 text-amber-900/90">
                    <a
                        href="{{ route('admin.warehouses.create') }}"
                        class="font-semibold text-emerald-700 underline decoration-emerald-300 underline-offset-2 hover:text-emerald-800"
                    >Добавить склад</a>
                </p>
            </div>
        @else
            <div
                class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50"
            >
                <form
                    method="GET"
                    action="{{ route('admin.opening-balances.index') }}"
                    class="rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90 px-4 py-4 sm:px-6 sm:py-5"
                >
                    <x-input-label for="opening_balance_warehouse" value="Склад для остатков *" />
                    <select
                        id="opening_balance_warehouse"
                        name="warehouse_id"
                        class="mt-2 block w-full max-w-md rounded-xl border border-slate-200/90 bg-white py-2.5 pl-3 pr-10 text-sm text-slate-900 shadow-sm ring-1 ring-slate-900/5 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/25"
                        onchange="this.form.submit()"
                    >
                        @foreach ($warehouses as $w)
                            <option value="{{ $w->id }}" @selected((int) $w->id === (int) $selectedWarehouseId)>{{ $w->name }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            @if (session('import_errors'))
                <div
                    class="rounded-2xl border border-amber-200/90 bg-gradient-to-r from-amber-50 to-white px-4 py-3 text-sm text-amber-950 shadow-sm ring-1 ring-amber-100/60"
                >
                    <p class="font-semibold">Замечания по строкам файла:</p>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        @foreach (session('import_errors') as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <x-input-error class="mt-1" :messages="$errors->get('warehouse_id')" />
            <x-input-error class="mt-1" :messages="$errors->get('file')" />

            @if ($selectedWarehouseId !== 0)
                <form
                    id="ob-import-form"
                    method="POST"
                    action="{{ route('admin.opening-balances.import') }}"
                    enctype="multipart/form-data"
                    class="sr-only"
                    aria-hidden="true"
                >
                    @csrf
                    <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}" />
                    <input
                        type="file"
                        name="file"
                        id="opening_balance_file"
                        accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
                        onchange="if (this.files.length) this.form.requestSubmit()"
                    />
                </form>
            @endif

            <script>
            (function () {
                if (window.obGenEan13) {
                    return;
                }
                window.obGenEan13 = function () {
                    var base = '2';
                    var a = new Uint8Array(11);
                    crypto.getRandomValues(a);
                    for (var i = 0; i < 11; i++) {
                        base += String(a[i] % 10);
                    }
                    var sum = 0;
                    for (var j = 0; j < 12; j++) {
                        var d = parseInt(base[j], 10);
                        sum += j % 2 === 0 ? d : d * 3;
                    }
                    var check = (10 - (sum % 10)) % 10;
                    return base + check;
                };
                window.obGenArticle = function () {
                    var d = new Date();
                    var ymd =
                        String(d.getFullYear()).slice(2) +
                        String(d.getMonth() + 1).padStart(2, '0') +
                        String(d.getDate()).padStart(2, '0');
                    var buf = new Uint8Array(4);
                    crypto.getRandomValues(buf);
                    var hex = '';
                    for (var k = 0; k < buf.length; k++) {
                        hex += buf[k].toString(16).padStart(2, '0');
                    }
                    return 'G' + ymd + '-' + hex.slice(0, 6).toUpperCase();
                };
            })();
        </script>
        <style>
            .ob-1c-scope {
                font-family: Tahoma, 'Segoe UI', Arial, sans-serif;
                font-size: 12px;
                color: #0f172a;
            }
            .ob-1c-toolbar {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 6px;
                margin-bottom: 0;
                border: 1px solid rgb(167 243 208 / 0.55);
                border-left: 0;
                border-right: 0;
                background: linear-gradient(180deg, #ecfdf5 0%, #f0fdfa 45%, #f8fafc 100%);
                padding: 0.55rem 0.65rem;
            }
            .ob-tb-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 4px;
                min-height: 24px;
                padding: 3px 11px;
                font-size: 11px;
                line-height: 1.2;
                font-weight: 600;
                color: #0f172a;
                white-space: nowrap;
                cursor: pointer;
                border: 1px solid rgb(186 230 253 / 0.95);
                border-radius: 0.375rem;
                background: linear-gradient(180deg, #fff 0%, #f0fdfa 100%);
                box-shadow: 0 1px 0 rgb(255 255 255 / 0.85) inset;
            }
            .ob-tb-btn:hover {
                background: linear-gradient(180deg, #f0fdfa 0%, #e0f2fe 100%);
                border-color: rgb(125 211 252);
            }
            .ob-tb-btn:active {
                background: #e0f2fe;
            }
            .ob-tb-btn-icon {
                padding: 3px 7px;
                min-width: 26px;
            }
            .ob-1c-table {
                width: 100%;
                border-collapse: collapse;
                table-layout: auto;
                background: #fff;
            }
            .ob-1c-table th,
            .ob-1c-table td {
                border: 1px solid rgb(226 232 240);
                padding: 0;
                vertical-align: middle;
            }
            .ob-1c-table th {
                background: linear-gradient(180deg, #ecfdf5 0%, #e0f2fe 100%);
                font-weight: 700;
                text-align: left;
                padding: 6px 8px;
                white-space: nowrap;
                color: #0f766e;
                font-size: 11px;
                letter-spacing: 0.02em;
            }
            .ob-1c-table th.ob-num,
            .ob-1c-table td.ob-num {
                text-align: center;
                width: 2.25rem;
                color: #475569;
            }
            .ob-1c-table .ob-inp {
                display: block;
                width: 100%;
                min-height: 26px;
                margin: 0;
                padding: 4px 8px;
                border: 0;
                background: transparent;
                font: inherit;
                color: #0f172a;
                outline: none;
                box-shadow: none;
            }
            .ob-1c-table .ob-inp:focus {
                background: rgb(236 253 245 / 0.95);
            }
            .ob-1c-table td.ob-numr .ob-inp {
                text-align: right;
            }
            .ob-row-active {
                background: rgb(254 252 232 / 0.95) !important;
            }
            .ob-row-active .ob-inp {
                background: rgb(254 252 232 / 0.95) !important;
            }
            .ob-more-wrap {
                position: relative;
                margin-left: auto;
            }
            .ob-more-dd {
                position: absolute;
                right: 0;
                top: 100%;
                z-index: 40;
                margin-top: 4px;
                min-width: 11rem;
                overflow: hidden;
                border-radius: 0.5rem;
                border: 1px solid rgb(186 230 253 / 0.9);
                background: #fff;
                box-shadow: 0 10px 30px -8px rgb(14 165 233 / 0.25);
            }
            .ob-more-dd button {
                display: block;
                width: 100%;
                text-align: left;
                padding: 8px 12px;
                font-size: 11px;
                font-weight: 600;
                border: 0;
                background: #fff;
                cursor: pointer;
                color: #0f172a;
            }
            .ob-more-dd button:hover {
                background: rgb(240 249 255);
            }
            .ob-1c-foot {
                display: flex;
                justify-content: flex-end;
                gap: 8px;
                margin-top: 0;
                padding: 0.75rem 0.85rem 0.85rem;
                border-top: 1px solid rgb(203 213 225 / 0.85);
                background: linear-gradient(180deg, rgb(248 250 252 / 0.6) 0%, #fff 100%);
            }
            .ob-btn-submit {
                min-height: 28px !important;
                padding: 5px 18px !important;
                font-size: 12px !important;
                border-color: #b8a642 !important;
                background: linear-gradient(180deg, #fffef0 0%, #f0e68c 100%) !important;
                font-weight: 700 !important;
            }
            .ob-btn-submit:hover {
                background: linear-gradient(180deg, #fffce8 0%, #e8dc7a 100%) !important;
                border-color: #a89838 !important;
            }
        </style>
        <form
            method="POST"
            action="{{ route('admin.opening-balances.store') }}"
            class="space-y-6"
            x-data="{
                lines: {{ \Illuminate\Support\Js::from($linesForForm) }},
                selectedRow: 0,
                moreOpen: false,
                addRow() {
                    this.lines.push({
                        article_code: '',
                        name: '',
                        barcode: '',
                        category: '',
                        quantity: '',
                        unit_cost: '',
                        sale_price: '',
                        unit: 'шт.',
                    });
                    this.selectedRow = this.lines.length - 1;
                },
                removeSelectedRow() {
                    if (this.lines.length <= 1) return;
                    this.lines.splice(this.selectedRow, 1);
                    this.selectedRow = Math.min(this.selectedRow, this.lines.length - 1);
                    this.moreOpen = false;
                },
                moveUp() {
                    const i = this.selectedRow;
                    if (i <= 0) return;
                    const next = this.lines.slice();
                    [next[i - 1], next[i]] = [next[i], next[i - 1]];
                    this.lines = next;
                    this.selectedRow = i - 1;
                },
                moveDown() {
                    const i = this.selectedRow;
                    if (i >= this.lines.length - 1) return;
                    const next = this.lines.slice();
                    [next[i], next[i + 1]] = [next[i + 1], next[i]];
                    this.lines = next;
                    this.selectedRow = i + 1;
                },
                genBarcodesEmptyOnly() {
                    const used = new Set(this.lines.map((r) => (r.barcode || '').trim()).filter(Boolean));
                    let n = 0;
                    this.lines.forEach((row) => {
                        if ((row.barcode || '').trim() !== '') return;
                        let code = '';
                        for (let k = 0; k < 60; k++) {
                            code = window.obGenEan13();
                            if (!used.has(code)) break;
                        }
                        row.barcode = code;
                        used.add(code);
                        n++;
                    });
                    if (n === 0) window.alert('Нет строк с пустым штрихкодом.');
                },
                genArticlesEmptyOnly() {
                    const used = new Set(this.lines.map((r) => (r.article_code || '').trim()).filter(Boolean));
                    let n = 0;
                    this.lines.forEach((row) => {
                        if ((row.article_code || '').trim() !== '') return;
                        let code = '';
                        for (let k = 0; k < 60; k++) {
                            code = window.obGenArticle();
                            if (!used.has(code)) break;
                        }
                        row.article_code = code;
                        used.add(code);
                        n++;
                    });
                    if (n === 0) window.alert('Нет строк с пустым артикулом.');
                },
                focusExcelImport() {
                    const el = document.getElementById('opening_balance_file');
                    if (!el) {
                        window.alert('Выберите склад в списке выше.');
                        return;
                    }
                    el.click();
                },
            }"
        >
            @csrf
            <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}" />
            <div
                class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50"
            >
                <div class="overflow-hidden rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90">
                    <div class="ob-1c-scope overflow-hidden rounded-[1.5rem] bg-white/95">
                        <div
                            class="border-b border-emerald-200/55 bg-gradient-to-r from-emerald-50/95 via-white to-sky-50/50 px-4 py-3 sm:px-5"
                        >
                            <p class="mb-0.5 text-[10px] font-semibold uppercase tracking-wider text-teal-700/90">Ввод остатков</p>
                            <h2 class="text-[15px] font-bold leading-tight text-slate-800">Ручной ввод</h2>
                        </div>

                <div class="ob-1c-toolbar">
                    <button type="button" class="ob-tb-btn" @click="addRow()">Добавить</button>
                    <button type="button" class="ob-tb-btn ob-tb-btn-icon" title="Переместить строку вверх" @click="moveUp()">▲</button>
                    <button type="button" class="ob-tb-btn ob-tb-btn-icon" title="Переместить строку вниз" @click="moveDown()">▼</button>
                    <span class="mx-1 h-4 w-px bg-slate-300/90" aria-hidden="true"></span>
                    <button type="button" class="ob-tb-btn" title="EAN-13 только для пустых ячеек" @click="genBarcodesEmptyOnly()">Штрихкоды</button>
                    <button type="button" class="ob-tb-btn" title="Только для пустых ячеек" @click="genArticlesEmptyOnly()">Артикулы</button>
                    <span class="mx-1 h-4 w-px bg-slate-300/90" aria-hidden="true"></span>
                    <button type="button" class="ob-tb-btn" title="Загрузить .xlsx / .xls / .csv на выбранный склад" @click="focusExcelImport()">Excel…</button>
                    <div class="ob-more-wrap" @keydown.escape.window="moreOpen = false">
                        <button type="button" class="ob-tb-btn" @click="moreOpen = !moreOpen" :aria-expanded="moreOpen">Ещё ▾</button>
                        <div
                            x-cloak
                            x-show="moreOpen"
                            @click.outside="moreOpen = false"
                            class="ob-more-dd"
                            x-transition
                        >
                            <button type="button" @click="removeSelectedRow(); moreOpen = false">Удалить строку</button>
                            <a
                                href="{{ route('admin.opening-balances.sample') }}"
                                class="block border-t border-slate-100 px-2.5 py-2 text-[11px] font-semibold text-sky-800 hover:bg-sky-50"
                                @click="moreOpen = false"
                            >Скачать образец Excel</a>
                        </div>
                    </div>
                </div>

                <x-input-error class="mx-3 mt-2" :messages="$errors->get('lines')" />

                @php
                    $lineFieldErrors = collect($errors->getMessages())->filter(fn ($_, $k) => str_starts_with((string) $k, 'lines.'));
                @endphp
                @if ($lineFieldErrors->isNotEmpty())
                    <div class="mx-3 mt-2 rounded-xl border border-red-200/90 bg-red-50/95 px-3 py-2 text-[11px] text-red-900 shadow-sm">
                        <ul class="list-inside list-disc space-y-0.5">
                            @foreach ($lineFieldErrors->flatten() as $msg)
                                <li>{{ $msg }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="overflow-x-auto border-t border-slate-200/90">
                    <table
                        class="ob-1c-table"
                        @focusin="$event.target.classList.contains('ob-inp') && $event.target.select()"
                        @mouseup="$event.target.classList.contains('ob-inp') && $event.preventDefault()"
                    >
                        <thead>
                            <tr>
                                <th class="ob-num">N</th>
                                <th>Наименование *</th>
                                <th>Штрихкод</th>
                                <th>Категория</th>
                                <th>Артикул *</th>
                                <th>Ед. изм.</th>
                                <th>Количество *</th>
                                <th>Цена (закуп.)</th>
                                <th>Цена (продаж.)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, index) in lines" :key="index">
                                <tr
                                    class="cursor-pointer"
                                    :class="{ 'ob-row-active': selectedRow === index }"
                                    @click="selectedRow = index"
                                >
                                    <td class="ob-num" x-text="index + 1"></td>
                                    <td class="min-w-[10rem]">
                                        <input type="text" :name="`lines[${index}][name]`" x-model="row.name" class="ob-inp" autocomplete="off" />
                                    </td>
                                    <td class="min-w-[7.5rem]">
                                        <input type="text" :name="`lines[${index}][barcode]`" x-model="row.barcode" class="ob-inp font-mono text-[11px]" inputmode="numeric" autocomplete="off" />
                                    </td>
                                    <td class="min-w-[6rem]">
                                        <input type="text" :name="`lines[${index}][category]`" x-model="row.category" class="ob-inp" autocomplete="off" />
                                    </td>
                                    <td class="min-w-[7rem]">
                                        <input type="text" :name="`lines[${index}][article_code]`" x-model="row.article_code" class="ob-inp font-mono text-[11px]" autocomplete="off" />
                                    </td>
                                    <td class="min-w-[3.5rem]">
                                        <input type="text" :name="`lines[${index}][unit]`" x-model="row.unit" class="ob-inp" autocomplete="off" />
                                    </td>
                                    <td class="min-w-[4.5rem] ob-numr">
                                        <input type="text" :name="`lines[${index}][quantity]`" x-model="row.quantity" class="ob-inp" inputmode="decimal" autocomplete="off" />
                                    </td>
                                    <td class="min-w-[4.5rem] ob-numr">
                                        <input type="text" :name="`lines[${index}][unit_cost]`" x-model="row.unit_cost" class="ob-inp" inputmode="decimal" autocomplete="off" />
                                    </td>
                                    <td class="min-w-[4.5rem] ob-numr">
                                        <input type="text" :name="`lines[${index}][sale_price]`" x-model="row.sale_price" class="ob-inp" inputmode="decimal" autocomplete="off" />
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="ob-1c-foot">
                    <button type="submit" class="ob-tb-btn ob-btn-submit">Записать</button>
                </div>
                    </div>
                </div>
            </div>
        </form>

        @if ($balances->isNotEmpty())
            <div
                class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50"
            >
                <div class="overflow-hidden rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90">
                    <div class="border-b border-emerald-100/80 bg-gradient-to-r from-emerald-50/95 via-white to-sky-50/60 px-5 py-3.5 sm:px-6">
                        <p class="mb-0.5 text-[10px] font-semibold uppercase tracking-wider text-teal-700/90">На выбранном складе</p>
                        <h2 class="text-[15px] font-bold leading-tight text-slate-800">Текущие сохранённые остатки</h2>
                    </div>
                    <div class="cp-table-wrap bg-gradient-to-b from-slate-50/30 via-white to-emerald-50/20 p-3 sm:p-4">
                        <table class="cp-table cp-directory-table text-[13px]">
                            <thead>
                                <tr>
                                    <th class="text-center">N</th>
                                    <th>Наименование</th>
                                    <th>Штрихкод</th>
                                    <th>Категория</th>
                                    <th>Артикул</th>
                                    <th>Ед.</th>
                                    <th class="text-right">Кол-во</th>
                                    <th class="text-right">Закуп.</th>
                                    <th class="text-right">Продаж.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($balances as $i => $b)
                                    <tr>
                                        <td class="text-center tabular-nums text-slate-600">{{ $i + 1 }}</td>
                                        <td class="font-medium text-slate-900">{{ $b->good->name }}</td>
                                        <td class="whitespace-nowrap font-mono text-xs text-slate-800">{{ $b->good->barcode ?? '—' }}</td>
                                        <td class="text-slate-800">{{ $b->good->category ?? '—' }}</td>
                                        <td class="whitespace-nowrap font-mono text-xs text-slate-900">{{ $b->good->article_code }}</td>
                                        <td class="whitespace-nowrap text-slate-700">{{ $b->good->unit }}</td>
                                        <td class="text-right tabular-nums text-slate-900">{{ $b->quantity }}</td>
                                        <td class="text-right tabular-nums text-slate-800">{{ $b->unit_cost ?? '—' }}</td>
                                        <td class="text-right tabular-nums text-slate-800">{{ $b->good->sale_price ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
        @endif
    </div>
</x-admin-layout>
