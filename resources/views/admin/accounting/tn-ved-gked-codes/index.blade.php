@php
    use Illuminate\Support\Js;
    $tabs = [
        'goods' => 'Товары',
        'services' => 'Услуги',
    ];
@endphp
<x-admin-layout pageTitle="ТНВЭД / ГКЭД коды" main-class="bg-slate-100/80 px-3 py-5 sm:px-4 lg:px-6">
    <div
        class="mx-auto max-w-4xl space-y-4"
        @keydown.escape.window="modalOpen = false; universalModalOpen = false"
        x-data="{
            modalOpen: false,
            universalModalOpen: false,
            loading: false,
            previewCount: 0,
            previewKeyword: '',
            previewCode: '',
            applyFormId: '',
            universalCount: 0,
            universalCode: '',
            openPreview(previewUrl, formId) {
                this.loading = true;
                this.applyFormId = formId;
                fetch(previewUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then((r) => r.json())
                    .then((d) => {
                        this.previewCount = d.count ?? 0;
                        this.previewKeyword = d.keyword ?? '';
                        this.previewCode = d.tnved_code ?? '';
                        this.modalOpen = true;
                    })
                    .catch(() => {
                        alert('Не удалось получить данные.');
                    })
                    .finally(() => { this.loading = false; });
            },
            confirmApply() {
                const f = document.getElementById(this.applyFormId);
                if (f) f.submit();
            },
            openUniversalModal() {
                const i = document.getElementById('univ_tnved');
                const code = (i && i.value) ? String(i.value).trim() : '';
                if (!/^\d+$/.test(code)) {
                    alert('Введите универсальный ТНВЭД (только цифры).');
                    return;
                }
                this.universalCode = code;
                this.loading = true;
                fetch({{ Js::from(route('admin.accounting.tn-ved-gked-codes.universal-preview')) }}, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then((r) => r.json())
                    .then((d) => {
                        this.universalCount = d.count ?? 0;
                        this.universalModalOpen = true;
                    })
                    .catch(() => { alert('Не удалось получить данные.'); })
                    .finally(() => { this.loading = false; });
            },
            confirmUniversal() {
                const f = document.getElementById('universal-apply-form');
                if (f) f.requestSubmit();
            }
        }"
    >
        @if (session('status'))
            <div
                class="flex items-start gap-3 rounded-2xl border border-emerald-200/90 bg-emerald-50 px-4 py-3 text-sm text-emerald-950 shadow-sm"
                role="status"
            >
                <span class="mt-0.5 text-emerald-600" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/5">
            <div class="border-b border-slate-200/80 bg-gradient-to-r from-slate-50 to-white px-4 py-4 sm:px-5">
                <h1 class="text-lg font-bold text-slate-900">ТНВЭД / ГКЭД</h1>
                <p class="mt-3 inline-flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-sm font-medium text-amber-950 ring-1 ring-amber-200/80">
                    <span class="tabular-nums font-bold">{{ $goodsWithoutTnved }}</span>
                    <span>товаров без ТНВЭД (не услуги)</span>
                </p>
            </div>

            <div class="flex border-b border-slate-200 bg-slate-50/80 px-2 pt-2 sm:px-3">
                @foreach ($tabs as $id => $label)
                    <a
                        href="{{ route('admin.accounting.tn-ved-gked-codes', ['tab' => $id]) }}"
                        @class([
                            'flex-1 rounded-t-lg border border-b-0 px-3 py-2.5 text-center text-sm font-semibold transition',
                            'border-slate-200 bg-white text-emerald-800 shadow-sm' => $activeTab === $id,
                            'border-transparent text-slate-600 hover:bg-white/60 hover:text-slate-900' => $activeTab !== $id,
                        ])
                    >{{ $label }}</a>
                @endforeach
            </div>

            @if ($activeTab === 'goods')
                <div class="space-y-5 p-4 sm:p-5">
                    <form
                        id="universal-apply-form"
                        method="POST"
                        action="{{ route('admin.accounting.tn-ved-gked-codes.universal-apply') }}"
                        class="rounded-xl border border-indigo-200/80 bg-indigo-50/40 p-4 sm:p-5"
                    >
                        @csrf
                        <div class="space-y-3 sm:flex sm:flex-wrap sm:items-end sm:gap-3">
                            <div class="min-w-0 w-full sm:flex-1 sm:min-w-[12rem]">
                                <label for="univ_tnved" class="mb-1.5 block text-sm font-medium text-slate-800">Универсальный ТНВЭД</label>
                                <input
                                    type="text"
                                    name="universal_tnved_code"
                                    id="univ_tnved"
                                    value="{{ old('universal_tnved_code', $branch->universal_tnved_code ?? '') }}"
                                    inputmode="numeric"
                                    pattern="[0-9]+"
                                    class="box-border w-full min-w-0 rounded-lg border-2 border-indigo-200/90 bg-white px-3 py-2.5 font-mono text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                />
                                @error('universal_tnved_code')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <button
                                type="button"
                                class="w-full rounded-lg border border-indigo-300 bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-indigo-700 sm:w-auto sm:shrink-0"
                                @click="openUniversalModal()"
                            >Проставить товарам без ТНВЭД</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('admin.accounting.tn-ved-gked-codes.rules.store') }}" class="rounded-xl border border-slate-200/90 bg-slate-50/50 p-4 sm:p-5">
                        @csrf
                        <div class="space-y-4">
                            <div class="w-full min-w-0">
                                <label for="tnk_keyword" class="mb-1.5 block text-sm font-medium text-slate-800">Ключевое слово</label>
                                <input
                                    type="text"
                                    name="keyword"
                                    id="tnk_keyword"
                                    value="{{ old('keyword') }}"
                                    class="box-border w-full min-w-0 rounded-lg border-2 border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                    autocomplete="off"
                                />
                                @error('keyword')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="w-full min-w-0 sm:max-w-md">
                                <label for="tnk_tnved" class="mb-1.5 block text-sm font-medium text-slate-800">Код ТНВЭД</label>
                                <input
                                    type="text"
                                    name="tnved_code"
                                    id="tnk_tnved"
                                    value="{{ old('tnved_code') }}"
                                    inputmode="numeric"
                                    pattern="[0-9]+"
                                    class="box-border w-full min-w-0 rounded-lg border-2 border-slate-200 bg-white px-3 py-2.5 font-mono text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                />
                                @error('tnved_code')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <button
                                    type="submit"
                                    class="w-full rounded-lg bg-emerald-600 px-4 py-3 text-sm font-semibold text-white shadow hover:bg-emerald-700 sm:w-auto sm:px-6"
                                >Добавить</button>
                            </div>
                        </div>
                    </form>

                    @if ($rules->isEmpty())
                        <p class="text-center text-sm text-slate-500">Нет записей</p>
                    @else
                        <div class="overflow-x-auto rounded-xl border border-slate-200/90">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-slate-100/90 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    <tr>
                                        <th class="px-3 py-2.5">Ключ</th>
                                        <th class="px-3 py-2.5">ТНВЭД</th>
                                        <th class="px-3 py-2.5 text-right">Действия</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200/80">
                                    @foreach ($rules as $rule)
                                        <tr class="bg-white/80">
                                            <td class="px-3 py-2.5 font-medium text-slate-900">{{ $rule->keyword }}</td>
                                            <td class="px-3 py-2.5 tabular-nums text-slate-800">{{ $rule->tnved_code }}</td>
                                            <td class="px-3 py-2.5 text-right">
                                                <div class="flex flex-wrap items-center justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-900 hover:bg-emerald-100"
                                                        @click="openPreview({{ Js::from(route('admin.accounting.tn-ved-gked-codes.rules.preview', $rule)) }}, 'apply-form-{{ $rule->id }}')"
                                                    >Проставить к товарам</button>
                                                    <form
                                                        method="POST"
                                                        action="{{ route('admin.accounting.tn-ved-gked-codes.rules.destroy', $rule) }}"
                                                        onsubmit="return confirm('Удалить правило из справочника?');"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Удалить</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @foreach ($rules as $rule)
                        <form
                            id="apply-form-{{ $rule->id }}"
                            method="POST"
                            action="{{ route('admin.accounting.tn-ved-gked-codes.rules.apply', $rule) }}"
                            class="hidden"
                        >@csrf</form>
                    @endforeach
                </div>
            @endif

            @if ($activeTab === 'services')
                <form method="POST" action="{{ route('admin.accounting.tn-ved-gked-codes.service-tnved') }}" class="space-y-4 p-4 sm:p-5">
                    @csrf
                    <div class="w-full min-w-0 sm:max-w-md">
                        <label for="svc_gked" class="mb-1.5 block text-sm font-medium text-slate-800">Код ГКЭД (услуги)</label>
                        <input
                            type="text"
                            name="service_tnved_code"
                            id="svc_gked"
                            value="{{ old('service_tnved_code', $branch->service_tnved_code ?? '') }}"
                            inputmode="decimal"
                            pattern="[0-9]+(\.[0-9]+)*"
                            class="box-border w-full min-w-0 rounded-lg border-2 border-slate-200 bg-white px-3 py-2.5 font-mono text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        />
                        @error('service_tnved_code')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="w-full min-w-0 sm:max-w-2xl">
                        <label for="svc_esf_name" class="mb-1.5 block text-sm font-medium text-slate-800">Общее наименование для ЭСФ (услуги)</label>
                        <textarea
                            name="service_esf_export_name"
                            id="svc_esf_name"
                            rows="2"
                            maxlength="500"
                            class="box-border w-full min-w-0 rounded-lg border-2 border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                            placeholder="Одно и то же для всех услуг в XML и Excel выгрузок ЭСФ"
                        >{{ old('service_esf_export_name', $branch->service_esf_export_name ?? '') }}</textarea>
                        <p class="mt-1.5 text-sm text-slate-600">Используется только в выгрузке ЭСФ (XML, Excel) для кабинета налогоплательщика. Названия номенклатуры в учёте не меняются.</p>
                        @error('service_esf_export_name')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-3 text-sm font-semibold text-white shadow hover:bg-emerald-700 sm:w-auto sm:px-6">Сохранить</button>
                    </div>
                </form>
            @endif
        </div>

        <div
            x-cloak
            x-show="universalModalOpen"
            class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center"
        >
            <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-xl" @click.outside="universalModalOpen = false">
                <h3 class="text-base font-bold text-slate-900">Универсальный ТНВЭД</h3>
                <p class="mt-2 text-sm text-slate-700">Код <span class="font-mono font-semibold" x-text="universalCode"></span> будет проставлен товарам (не услугам) без кода.</p>
                <p class="mt-3 text-sm text-slate-800">Таких товаров: <span class="text-lg font-bold tabular-nums text-indigo-800" x-text="universalCount"></span></p>
                <p x-show="universalCount === 0" x-cloak class="mt-2 text-sm text-amber-800">Код всё равно сохранится в настройке.</p>
                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="universalModalOpen = false">Отмена</button>
                    <button
                        type="button"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="loading"
                        @click="confirmUniversal()"
                    >Подтвердить</button>
                </div>
            </div>
        </div>

        <div
            x-cloak
            x-show="modalOpen"
            class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center"
        >
            <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-xl" @click.outside="modalOpen = false">
                <h3 class="text-base font-bold text-slate-900">Подтверждение</h3>
                <p class="mt-2 text-sm text-slate-700">ТНВЭД <span class="font-mono font-semibold" x-text="previewCode"></span>, ключ <span class="font-semibold" x-text="previewKeyword"></span></p>
                <p class="mt-3 text-sm text-slate-800">Товаров: <span class="text-lg font-bold tabular-nums text-emerald-800" x-text="previewCount"></span></p>
                <p x-show="previewCount === 0" x-cloak class="mt-2 text-sm text-amber-800">Нет товаров по ключу</p>
                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="modalOpen = false">Отмена</button>
                    <button
                        type="button"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="loading || previewCount === 0"
                        @click="confirmApply()"
                    >Подтвердить</button>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
