@php
    $obPage = $goodsPaginator !== null ? $goodsPaginator->currentPage() : max(1, (int) old('page', request()->integer('page') ?: 1));
    $gcShowAll = count($missingKeys) === 0;
    /** @var list<string> Поля строки формы кроме good_id и name */
    $gcDataKeys = [
        'article_code',
        'barcode',
        'category',
        'unit',
        'unit_cost',
        'wholesale_price',
        'sale_price',
        'oem',
        'factory_number',
        'min_stock',
    ];
    $gcNumericFields = ['unit_cost', 'wholesale_price', 'sale_price', 'min_stock'];
    $gcTableColspan = $gcShowAll ? 12 : 2 + count($missingKeys);
@endphp
<x-admin-layout :pageTitle="$pageTitle" main-class="px-3 py-6 sm:px-6 lg:px-8">
    @include('admin.partials.cp-brush')
    <div class="cp-root mx-auto w-full max-w-[min(100%,112rem)] space-y-6">
        @include('admin.partials.status-flash')

        @if ($errors->any())
            <div
                class="rounded-2xl border border-rose-200/90 bg-gradient-to-r from-rose-50 to-white px-4 py-3 text-sm text-rose-950 shadow-sm ring-1 ring-rose-100/80"
                role="alert"
            >
                <p class="font-semibold">Исправьте ошибки и сохраните снова.</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

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
                    action="{{ route('admin.reports.goods-characteristics') }}"
                    class="rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90 px-4 py-4 sm:px-6 sm:py-5"
                >
                    <input type="hidden" name="page" value="1" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="gc_wh" value="Склад *" />
                            <select
                                id="gc_wh"
                                name="warehouse_id"
                                class="mt-2 block w-full max-w-md rounded-xl border border-slate-200/90 bg-white py-2.5 pl-3 pr-10 text-sm text-slate-900 shadow-sm ring-1 ring-slate-900/5 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/25"
                                onchange="this.form.submit()"
                            >
                                @foreach ($warehouses as $w)
                                    <option value="{{ $w->id }}" @selected((int) $w->id === (int) $selectedWarehouseId)>{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <span class="text-sm font-medium text-slate-700">Показать товары без</span>
                            <div class="mt-2 space-y-3 rounded-xl border border-slate-200/90 bg-white/80 p-4">
                                <label class="flex cursor-pointer items-start gap-2.5 text-sm text-slate-800">
                                    <input
                                        type="checkbox"
                                        id="gc_all_incomplete"
                                        name="all_incomplete"
                                        value="1"
                                        class="gc-all-incomplete mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                        @checked(count($missingKeys) === 0)
                                    />
                                    <span>{{ $filterOptions['all'] }} (любая неполная характеристика)</span>
                                </label>
                                <div class="flex flex-wrap gap-x-4 gap-y-2 border-t border-slate-200/80 pt-3" id="gc-missing-checkboxes">
                                    @foreach (\App\Services\GoodsCharacteristicsService::missingFilterKeyList() as $key)
                                        <label class="inline-flex cursor-pointer items-center gap-1.5 text-sm text-slate-700">
                                            <input
                                                type="checkbox"
                                                name="missing[]"
                                                value="{{ $key }}"
                                                @checked(in_array($key, $missingKeys, true))
                                                class="gc-missing-cb rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                            />
                                            <span>{{ $filterOptions[$key] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <div class="flex flex-wrap items-center gap-3 border-t border-slate-200/80 pt-3">
                                    <button
                                        type="submit"
                                        class="inline-flex items-center justify-center rounded-xl border border-emerald-200/90 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-900 shadow-sm ring-1 ring-emerald-900/5 hover:bg-emerald-100/90"
                                    >
                                        Применить
                                    </button>
                                    @if (count($missingKeys) > 0)
                                        <a
                                            href="{{ route('admin.reports.goods-characteristics', ['warehouse_id' => $selectedWarehouseId, 'page' => 1, 'all_incomplete' => 1]) }}"
                                            class="text-sm font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-slate-900"
                                        >Сбросить фильтр</a>
                                    @endif
                                </div>
                            </div>
                            <script>
                                (function () {
                                    var all = document.getElementById('gc_all_incomplete');
                                    var cbs = document.querySelectorAll('#gc-missing-checkboxes .gc-missing-cb');
                                    if (!all || !cbs.length) return;
                                    function sync() {
                                        if (all.checked) {
                                            cbs.forEach(function (cb) {
                                                cb.checked = false;
                                                cb.disabled = true;
                                            });
                                        } else {
                                            cbs.forEach(function (cb) {
                                                cb.disabled = false;
                                            });
                                        }
                                    }
                                    all.addEventListener('change', sync);
                                    cbs.forEach(function (cb) {
                                        cb.addEventListener('change', function () {
                                            if (cb.checked) {
                                                all.checked = false;
                                                sync();
                                            }
                                        });
                                    });
                                    sync();
                                })();
                            </script>
                        </div>
                    </div>
                </form>
            </div>

            @if ($selectedWarehouseId !== 0 && $goodsPaginator !== null)
                <div
                    class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50"
                >
                    <div class="overflow-hidden rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90">
                        <div class="ob-1c-scope overflow-hidden rounded-[1.5rem] bg-white/95">
                            <style>
                                .ob-1c-scope {
                                    font-family: Tahoma, 'Segoe UI', Arial, sans-serif;
                                    font-size: 12px;
                                    color: #0f172a;
                                }
                                .ob-1c-scope .ob-1c-table {
                                    width: 100%;
                                    border-collapse: collapse;
                                    table-layout: auto;
                                    background: #fff;
                                }
                                .ob-1c-scope .ob-1c-table th,
                                .ob-1c-scope .ob-1c-table td {
                                    border: 1px solid rgb(226 232 240);
                                    padding: 0;
                                    vertical-align: middle;
                                }
                                .ob-1c-scope .ob-1c-table th {
                                    background: linear-gradient(180deg, #ecfdf5 0%, #e0f2fe 100%);
                                    font-weight: 700;
                                    text-align: left;
                                    padding: 6px 8px;
                                    white-space: nowrap;
                                    color: #0f766e;
                                    font-size: 11px;
                                    letter-spacing: 0.02em;
                                }
                                .ob-1c-scope .ob-1c-table th.ob-num,
                                .ob-1c-scope .ob-1c-table td.ob-num {
                                    text-align: center;
                                    width: 2.25rem;
                                    color: #475569;
                                }
                                .ob-1c-scope .ob-1c-table .ob-inp {
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
                                .ob-1c-scope .ob-1c-table .ob-inp:focus {
                                    background: rgb(236 253 245 / 0.95);
                                }
                                .ob-1c-scope .ob-1c-table td.ob-numr .ob-inp {
                                    text-align: right;
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
                                .gc-1c-toolbar {
                                    display: flex;
                                    flex-wrap: wrap;
                                    align-items: center;
                                    gap: 8px;
                                    padding: 0.55rem 0.85rem;
                                    border-bottom: 1px solid rgb(167 243 208 / 0.55);
                                    background: linear-gradient(180deg, #ecfdf5 0%, #f0fdfa 45%, #f8fafc 100%);
                                }
                                .gc-tb-btn {
                                    display: inline-flex;
                                    align-items: center;
                                    justify-content: center;
                                    gap: 4px;
                                    min-height: 26px;
                                    padding: 4px 12px;
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
                                .gc-tb-btn:hover {
                                    background: linear-gradient(180deg, #f0fdfa 0%, #e0f2fe 100%);
                                    border-color: rgb(125 211 252);
                                }
                            </style>
                            <div
                                class="border-b border-emerald-200/55 bg-gradient-to-r from-emerald-50/95 via-white to-sky-50/50 px-4 py-3 sm:px-5"
                            >
                                <p class="mb-0.5 text-[10px] font-semibold uppercase tracking-wider text-teal-700/90">Отчёт</p>
                                <h2 class="text-[15px] font-bold leading-tight text-slate-800">{{ $pageTitle }}</h2>
                            </div>

                            @if ($goodsPaginator->lastPage() > 1)
                                <div class="border-b border-emerald-100/80 bg-slate-50/90 px-3 py-2 text-[11px] text-slate-700">
                                    {{ $goodsPaginator->links() }}
                                </div>
                            @endif

                            <form
                                id="gc-characteristics-form"
                                method="POST"
                                action="{{ route('admin.reports.goods-characteristics.store') }}"
                            >
                                @csrf
                                <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}" />
                                <input type="hidden" name="page" value="{{ $obPage }}" />
                                @if (count($missingKeys) === 0)
                                    <input type="hidden" name="all_incomplete" value="1" />
                                @else
                                    @foreach ($missingKeys as $mk)
                                        <input type="hidden" name="missing[]" value="{{ $mk }}" />
                                    @endforeach
                                @endif

                                @if ($linesForForm !== [])
                                    <div class="gc-1c-toolbar">
                                        <span class="text-[11px] font-semibold text-slate-600">Автозаполнение пустых:</span>
                                        <button type="button" class="gc-tb-btn" onclick="window.gcGenBarcodesEmptyOnly && window.gcGenBarcodesEmptyOnly()">
                                            Штрихкод EAN-13
                                        </button>
                                        <button type="button" class="gc-tb-btn" onclick="window.gcGenArticlesEmptyOnly && window.gcGenArticlesEmptyOnly()">
                                            Артикул
                                        </button>
                                    </div>
                                    @include('admin.partials.article-code-reserve-script')
                                    <script>
                                        (function () {
                                            if (window.gcGenBarcodesEmptyOnly) return;
                                            window.gcGenEan13Value = function () {
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
                                            window.gcGenBarcodesEmptyOnly = function () {
                                                var form = document.getElementById('gc-characteristics-form');
                                                if (!form) return;
                                                var inputs = form.querySelectorAll('input[name$="[barcode]"]');
                                                var used = new Set();
                                                inputs.forEach(function (el) {
                                                    var v = (el.value || '').trim();
                                                    if (v) used.add(v);
                                                });
                                                var n = 0;
                                                inputs.forEach(function (el) {
                                                    if ((el.value || '').trim() !== '') return;
                                                    var code = '';
                                                    for (var k = 0; k < 60; k++) {
                                                        code = window.gcGenEan13Value();
                                                        if (!used.has(code)) break;
                                                    }
                                                    el.value = code;
                                                    used.add(code);
                                                    n++;
                                                });
                                                if (n === 0) window.alert('Нет пустых штрихкодов на этой странице.');
                                            };
                                            window.gcGenArticlesEmptyOnly = async function () {
                                                var form = document.getElementById('gc-characteristics-form');
                                                if (!form) return;
                                                if (typeof window.reserveBranchArticleCodes !== 'function') {
                                                    window.alert('Обновите страницу и попробуйте снова.');
                                                    return;
                                                }
                                                var empty = [];
                                                form.querySelectorAll('input[name$="[article_code]"]').forEach(function (el) {
                                                    if ((el.value || '').trim() === '') empty.push(el);
                                                });
                                                if (empty.length === 0) {
                                                    window.alert('Нет пустых артикулов на этой странице.');
                                                    return;
                                                }
                                                try {
                                                    var codes = await window.reserveBranchArticleCodes(empty.length);
                                                    empty.forEach(function (el, i) {
                                                        el.value = codes[i];
                                                    });
                                                } catch (e) {
                                                    window.alert(e && e.message ? e.message : 'Не удалось получить артикулы.');
                                                }
                                            };
                                        })();
                                    </script>
                                @endif

                                <div class="overflow-x-auto border-t border-slate-200/90">
                                    <table class="ob-1c-table">
                                        <thead>
                                            <tr>
                                                <th class="ob-num">N</th>
                                                <th>Наименование *</th>
                                                @if ($gcShowAll)
                                                    <th>Артикул *</th>
                                                    <th>Штрихкод</th>
                                                    <th>Категория</th>
                                                    <th>Ед. изм.</th>
                                                    <th>Цена (закуп.)</th>
                                                    <th>Оптовая цена</th>
                                                    <th>Цена (продаж.)</th>
                                                    <th>ОЭМ</th>
                                                    <th>Заводской №</th>
                                                    <th>Мин. остаток</th>
                                                @else
                                                    @foreach ($missingKeys as $mk)
                                                        <th>{{ $filterOptions[$mk] ?? $mk }}</th>
                                                    @endforeach
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($linesForForm as $idx => $line)
                                                <tr class="hover:bg-emerald-50/40">
                                                    <td class="ob-num">
                                                        {{ $goodsPaginator->firstItem() !== null ? $goodsPaginator->firstItem() + $idx : $idx + 1 }}
                                                    </td>
                                                    <td>
                                                        <input type="hidden" name="lines[{{ $idx }}][good_id]" value="{{ $line['good_id'] }}" />
                                                        <input
                                                            type="text"
                                                            name="lines[{{ $idx }}][name]"
                                                            value="{{ $line['name'] }}"
                                                            class="ob-inp min-w-[10rem]"
                                                            autocomplete="off"
                                                        />
                                                    </td>
                                                    @if ($gcShowAll)
                                                        <td>
                                                            <input
                                                                type="text"
                                                                name="lines[{{ $idx }}][article_code]"
                                                                value="{{ $line['article_code'] }}"
                                                                class="ob-inp min-w-[6rem]"
                                                                autocomplete="off"
                                                            />
                                                        </td>
                                                        <td>
                                                            <input
                                                                type="text"
                                                                name="lines[{{ $idx }}][barcode]"
                                                                value="{{ $line['barcode'] }}"
                                                                class="ob-inp min-w-[7rem]"
                                                                autocomplete="off"
                                                            />
                                                        </td>
                                                        <td>
                                                            <input
                                                                type="text"
                                                                name="lines[{{ $idx }}][category]"
                                                                value="{{ $line['category'] }}"
                                                                class="ob-inp min-w-[6rem]"
                                                                autocomplete="off"
                                                            />
                                                        </td>
                                                        <td>
                                                            <input
                                                                type="text"
                                                                name="lines[{{ $idx }}][unit]"
                                                                value="{{ $line['unit'] }}"
                                                                class="ob-inp w-14 min-w-[3rem]"
                                                                autocomplete="off"
                                                            />
                                                        </td>
                                                        <td class="ob-numr">
                                                            <input
                                                                type="text"
                                                                name="lines[{{ $idx }}][unit_cost]"
                                                                value="{{ $line['unit_cost'] }}"
                                                                class="ob-inp w-24 min-w-[4.5rem]"
                                                                inputmode="decimal"
                                                                autocomplete="off"
                                                            />
                                                        </td>
                                                        <td class="ob-numr">
                                                            <input
                                                                type="text"
                                                                name="lines[{{ $idx }}][wholesale_price]"
                                                                value="{{ $line['wholesale_price'] }}"
                                                                class="ob-inp w-24 min-w-[4.5rem]"
                                                                inputmode="decimal"
                                                                autocomplete="off"
                                                            />
                                                        </td>
                                                        <td class="ob-numr">
                                                            <input
                                                                type="text"
                                                                name="lines[{{ $idx }}][sale_price]"
                                                                value="{{ $line['sale_price'] }}"
                                                                class="ob-inp w-24 min-w-[4.5rem]"
                                                                inputmode="decimal"
                                                                autocomplete="off"
                                                            />
                                                        </td>
                                                        <td>
                                                            <input
                                                                type="text"
                                                                name="lines[{{ $idx }}][oem]"
                                                                value="{{ $line['oem'] }}"
                                                                class="ob-inp min-w-[5rem]"
                                                                autocomplete="off"
                                                            />
                                                        </td>
                                                        <td>
                                                            <input
                                                                type="text"
                                                                name="lines[{{ $idx }}][factory_number]"
                                                                value="{{ $line['factory_number'] }}"
                                                                class="ob-inp min-w-[5rem]"
                                                                autocomplete="off"
                                                            />
                                                        </td>
                                                        <td class="ob-numr">
                                                            <input
                                                                type="text"
                                                                name="lines[{{ $idx }}][min_stock]"
                                                                value="{{ $line['min_stock'] }}"
                                                                class="ob-inp w-24 min-w-[4.5rem]"
                                                                inputmode="decimal"
                                                                autocomplete="off"
                                                            />
                                                        </td>
                                                    @else
                                                        @foreach ($gcDataKeys as $dataKey)
                                                            @if (! in_array($dataKey, $missingKeys, true))
                                                                <input
                                                                    type="hidden"
                                                                    name="lines[{{ $idx }}][{{ $dataKey }}]"
                                                                    value="{{ $line[$dataKey] ?? '' }}"
                                                                />
                                                            @endif
                                                        @endforeach
                                                        @foreach ($missingKeys as $mk)
                                                            <td @class(['ob-numr' => in_array($mk, $gcNumericFields, true)])>
                                                                @if (in_array($mk, $gcNumericFields, true))
                                                                    <input
                                                                        type="text"
                                                                        name="lines[{{ $idx }}][{{ $mk }}]"
                                                                        value="{{ $line[$mk] ?? '' }}"
                                                                        class="ob-inp w-28 min-w-[6rem]"
                                                                        inputmode="decimal"
                                                                        autocomplete="off"
                                                                    />
                                                                @else
                                                                    <input
                                                                        type="text"
                                                                        name="lines[{{ $idx }}][{{ $mk }}]"
                                                                        value="{{ $line[$mk] ?? '' }}"
                                                                        class="ob-inp min-w-[10rem]"
                                                                        autocomplete="off"
                                                                    />
                                                                @endif
                                                            </td>
                                                        @endforeach
                                                    @endif
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="{{ $gcTableColspan }}" class="px-4 py-12 text-center text-sm text-slate-500">
                                                        Нет товаров с неполной характеристикой по выбранному фильтру.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                @if ($linesForForm !== [])
                                    <div class="ob-1c-foot">
                                        <x-primary-button type="submit" class="ob-btn-submit">Сохранить</x-primary-button>
                                    </div>
                                @endif
                            </form>

                            @if ($goodsPaginator->lastPage() > 1)
                                <div class="border-t border-slate-200/90 bg-slate-50/90 px-3 py-2 text-[11px] text-slate-700">
                                    {{ $goodsPaginator->links() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-admin-layout>
