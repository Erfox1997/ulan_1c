@props(['submitLabel' => 'Сохранить', 'formTitle' => 'Склад'])
@php
    /** @var \App\Models\Warehouse $warehouse */
@endphp
@include('admin.counterparties.partials.cp-theme')
@include('admin.organizations.partials.org-form-styles')

<form
    method="POST"
    class="org-form-scope cp-root w-full min-w-0"
    action="{{ $warehouse->exists ? route('admin.warehouses.update', $warehouse) : route('admin.warehouses.store') }}"
>
    @csrf
    @if ($warehouse->exists)
        @method('PUT')
    @endif

    <div class="cp-panel org-panel">
        <div class="cp-toolbar">
            <a href="{{ route('admin.warehouses.index') }}" class="cp-btn org-btn-ghost">← К списку</a>
        </div>

        <div class="org-titlebar px-4 py-3.5 sm:px-5 sm:py-4">
            <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-600 text-white shadow-lg shadow-emerald-500/30 ring-2 ring-white/60" aria-hidden="true">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="mb-0.5 text-[10px] font-semibold uppercase tracking-wider text-teal-700/90">Справочник</p>
                    <h2 class="cp-title text-[15px] leading-tight text-slate-800">{{ $formTitle }}</h2>
                </div>
            </div>
        </div>

        <div class="org-subhead">Основные данные</div>
        <div class="cp-grid cp-grid-2 org-grid-block px-3 py-4 sm:px-5 sm:py-5">
            <p class="sm:col-span-2 text-[11px] leading-relaxed text-slate-600">
                Склады привязаны к филиалу. Позже остатки товаров можно будет вести по складам.
            </p>
            <div class="sm:col-span-2">
                <label for="warehouse_name" class="cp-label">Наименование *</label>
                <input
                    id="warehouse_name"
                    name="name"
                    type="text"
                    class="cp-field"
                    value="{{ old('name', $warehouse->name) }}"
                    required
                />
                <x-input-error class="mt-1 text-xs" :messages="$errors->get('name')" />
            </div>
            <div>
                <label for="warehouse_code" class="cp-label">Код (необязательно)</label>
                <input
                    id="warehouse_code"
                    name="code"
                    type="text"
                    class="cp-field"
                    value="{{ old('code', $warehouse->code) }}"
                    maxlength="64"
                />
                <x-input-error class="mt-1 text-xs" :messages="$errors->get('code')" />
            </div>
            <div>
                <label for="warehouse_sort_order" class="cp-label">Порядок в списке</label>
                <input
                    id="warehouse_sort_order"
                    name="sort_order"
                    type="number"
                    min="0"
                    class="cp-field tabular-nums"
                    value="{{ old('sort_order', $warehouse->sort_order ?? 0) }}"
                />
                <x-input-error class="mt-1 text-xs" :messages="$errors->get('sort_order')" />
            </div>
            <div class="sm:col-span-2">
                <label for="warehouse_address" class="cp-label">Адрес / примечание</label>
                <textarea
                    id="warehouse_address"
                    name="address"
                    rows="2"
                    class="cp-field min-h-[4rem] resize-y"
                >{{ old('address', $warehouse->address) }}</textarea>
                <x-input-error class="mt-1 text-xs" :messages="$errors->get('address')" />
            </div>
            <div class="sm:col-span-2 flex flex-wrap gap-3">
                <div class="flex min-w-0 flex-1 items-center gap-3 rounded-lg border border-emerald-100/90 bg-emerald-50/40 px-3 py-2.5">
                    <input type="hidden" name="is_default" value="0" />
                    <input
                        id="warehouse_is_default"
                        name="is_default"
                        type="checkbox"
                        value="1"
                        class="h-4 w-4 shrink-0 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500/40"
                        @checked(old('is_default', $warehouse->is_default))
                    />
                    <label for="warehouse_is_default" class="cp-label !mb-0 cursor-pointer font-normal leading-snug text-emerald-950/90">Основной склад по умолчанию</label>
                </div>
                <div class="flex min-w-0 flex-1 items-center gap-3 rounded-lg border border-sky-100/90 bg-sky-50/40 px-3 py-2.5">
                    <input type="hidden" name="is_active" value="0" />
                    <input
                        id="warehouse_is_active"
                        name="is_active"
                        type="checkbox"
                        value="1"
                        class="h-4 w-4 shrink-0 rounded border-sky-300 text-sky-600 focus:ring-sky-500/40"
                        @checked(old('is_active', $warehouse->is_active ?? true))
                    />
                    <label for="warehouse_is_active" class="cp-label !mb-0 cursor-pointer font-normal leading-snug text-slate-800">Активен</label>
                </div>
            </div>
        </div>

        <div class="cp-foot org-foot flex flex-wrap items-center justify-end gap-3 px-4 py-3.5 sm:px-5">
            <a href="{{ route('admin.warehouses.index') }}" class="cp-btn min-h-[32px] px-5 org-btn-ghost">Отмена</a>
            <button type="submit" class="cp-btn cp-btn-primary min-h-[32px] px-6 font-bold shadow-md shadow-amber-400/25 ring-1 ring-amber-300/50">{{ $submitLabel }}</button>
        </div>
    </div>
</form>
