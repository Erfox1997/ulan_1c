@props(['submitLabel' => 'Сохранить'])
@php
    /** @var \App\Models\Good $service */
    $priceOld = old('sale_price');
    if ($priceOld === null && $service->exists && $service->sale_price !== null) {
        $priceDisplay = number_format((float) $service->sale_price, 2, ',', ' ');
    } else {
        $priceDisplay = $priceOld ?? '';
    }
@endphp

<form
    method="POST"
    class="block w-full min-w-0"
    action="{{ $service->exists ? route('admin.sale-services.update', $service) : route('admin.sale-services.store') }}"
>
    @csrf
    @if ($service->exists)
        @method('PUT')
    @endif

    <div class="ob-1c-header">
        <div class="col-span-2 min-w-0 max-sm:col-span-1">
            <label for="svc_name">Наименование услуги *</label>
            <input
                id="svc_name"
                name="name"
                type="text"
                class="mt-1 w-full"
                value="{{ old('name', $service->name) }}"
                required
                maxlength="500"
            />
            <x-input-error class="mt-1.5 text-xs text-red-700" :messages="$errors->get('name')" />
        </div>
        <div class="min-w-0">
            <label for="svc_unit">Единица</label>
            <input
                id="svc_unit"
                name="unit"
                type="text"
                class="mt-1 w-full"
                value="{{ old('unit', $service->unit ?? 'усл.') }}"
                maxlength="32"
            />
            <x-input-error class="mt-1.5 text-xs text-red-700" :messages="$errors->get('unit')" />
        </div>
        <div class="col-span-2 min-w-0 max-sm:col-span-1">
            <label for="svc_category">Категория</label>
            <input
                id="svc_category"
                name="category"
                type="text"
                class="mt-1 w-full"
                value="{{ old('category', $service->category ?? '') }}"
                maxlength="120"
                list="svc_category_datalist"
                placeholder="Выберите или введите новую"
                autocomplete="off"
            />
            <datalist id="svc_category_datalist">
                @foreach ($serviceCategories ?? [] as $c)
                    <option value="{{ $c }}"></option>
                @endforeach
            </datalist>
            <x-input-error class="mt-1.5 text-xs text-red-700" :messages="$errors->get('category')" />
        </div>
        <div class="min-w-0">
            <label for="svc_price">Цена (сом)</label>
            <input
                id="svc_price"
                name="sale_price"
                type="text"
                class="mt-1 w-full"
                value="{{ $priceDisplay }}"
                inputmode="decimal"
                placeholder="0,00"
                autocomplete="off"
            />
            <x-input-error class="mt-1.5 text-xs text-red-700" :messages="$errors->get('sale_price')" />
        </div>
    </div>

    <div class="ob-1c-foot flex flex-wrap items-center justify-end gap-2">
        <a href="{{ route('admin.sale-services.index') }}" class="ob-tb-btn !no-underline">К списку</a>
        <button type="submit" class="ob-tb-btn ob-btn-submit">{{ $submitLabel }}</button>
    </div>
</form>
