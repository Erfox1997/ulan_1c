@props(['submitLabel' => 'Сохранить'])
@php
    /** @var \App\Models\Good $good */
    $fmtOpt = static function ($v): string {
        if ($v === null || $v === '') {
            return '';
        }

        return number_format((float) $v, 2, ',', ' ');
    };
    $fmtStock = static function ($v): string {
        if ($v === null || $v === '') {
            return '';
        }

        return number_format((float) $v, 4, ',', ' ');
    };
    $field = static function (string $key, $default = '') {
        $o = old($key);
        if ($o !== null) {
            return $o;
        }

        return $default;
    };
@endphp

<form
    method="POST"
    class="block w-full min-w-0"
    action="{{ $good->exists ? route('admin.sale-goods.update', $good) : route('admin.sale-goods.store') }}"
>
    @csrf
    @if ($good->exists)
        @method('PUT')
    @endif

    <div class="ob-1c-header">
        <div class="min-w-0">
            <label for="g_article">Артикул *</label>
            <input
                id="g_article"
                name="article_code"
                type="text"
                class="mt-1 w-full font-mono"
                value="{{ $field('article_code', $good->article_code) }}"
                required
                maxlength="100"
                autocomplete="off"
            />
            <x-input-error class="mt-1.5 text-xs text-red-700" :messages="$errors->get('article_code')" />
        </div>
        <div class="col-span-2 min-w-0 max-sm:col-span-1">
            <label for="g_name">Наименование *</label>
            <input
                id="g_name"
                name="name"
                type="text"
                class="mt-1 w-full"
                value="{{ $field('name', $good->name) }}"
                required
                maxlength="500"
            />
            <x-input-error class="mt-1.5 text-xs text-red-700" :messages="$errors->get('name')" />
        </div>
        <div class="min-w-0">
            <label for="g_unit">Ед. изм.</label>
            <input
                id="g_unit"
                name="unit"
                type="text"
                class="mt-1 w-full"
                value="{{ $field('unit', $good->unit ?? 'шт.') }}"
                maxlength="32"
            />
            <x-input-error class="mt-1.5 text-xs text-red-700" :messages="$errors->get('unit')" />
        </div>
        <div class="min-w-0">
            <label for="g_category">Категория</label>
            <input
                id="g_category"
                name="category"
                type="text"
                class="mt-1 w-full"
                value="{{ $field('category', $good->category) }}"
                maxlength="120"
            />
            <x-input-error class="mt-1.5 text-xs text-red-700" :messages="$errors->get('category')" />
        </div>
        <div class="min-w-0">
            <label for="g_barcode">Штрихкод</label>
            <input
                id="g_barcode"
                name="barcode"
                type="text"
                class="mt-1 w-full"
                value="{{ $field('barcode', $good->barcode) }}"
                maxlength="64"
                autocomplete="off"
            />
            <x-input-error class="mt-1.5 text-xs text-red-700" :messages="$errors->get('barcode')" />
        </div>
        <div class="min-w-0">
            <label for="g_sale_price">Цена розница (сом)</label>
            <input
                id="g_sale_price"
                name="sale_price"
                type="text"
                class="mt-1 w-full"
                value="{{ $field('sale_price', $fmtOpt($good->sale_price)) }}"
                inputmode="decimal"
                placeholder="0,00"
                autocomplete="off"
            />
            <x-input-error class="mt-1.5 text-xs text-red-700" :messages="$errors->get('sale_price')" />
        </div>
        <div class="min-w-0">
            <label for="g_wholesale">Опт (сом)</label>
            <input
                id="g_wholesale"
                name="wholesale_price"
                type="text"
                class="mt-1 w-full"
                value="{{ $field('wholesale_price', $fmtOpt($good->wholesale_price)) }}"
                inputmode="decimal"
                autocomplete="off"
            />
            <x-input-error class="mt-1.5 text-xs text-red-700" :messages="$errors->get('wholesale_price')" />
        </div>
        <div class="min-w-0">
            <label for="g_min_sale">Мин. цена (сом)</label>
            <input
                id="g_min_sale"
                name="min_sale_price"
                type="text"
                class="mt-1 w-full"
                value="{{ $field('min_sale_price', $fmtOpt($good->min_sale_price)) }}"
                inputmode="decimal"
                autocomplete="off"
            />
            <x-input-error class="mt-1.5 text-xs text-red-700" :messages="$errors->get('min_sale_price')" />
        </div>
        <div class="min-w-0">
            <label for="g_oem">ОЭМ</label>
            <input
                id="g_oem"
                name="oem"
                type="text"
                class="mt-1 w-full"
                value="{{ $field('oem', $good->oem) }}"
                maxlength="120"
            />
            <x-input-error class="mt-1.5 text-xs text-red-700" :messages="$errors->get('oem')" />
        </div>
        <div class="min-w-0">
            <label for="g_factory">Номер завода</label>
            <input
                id="g_factory"
                name="factory_number"
                type="text"
                class="mt-1 w-full"
                value="{{ $field('factory_number', $good->factory_number) }}"
                maxlength="120"
            />
            <x-input-error class="mt-1.5 text-xs text-red-700" :messages="$errors->get('factory_number')" />
        </div>
        <div class="min-w-0">
            <label for="g_min_stock">Мин. остаток</label>
            <input
                id="g_min_stock"
                name="min_stock"
                type="text"
                class="mt-1 w-full"
                value="{{ $field('min_stock', $fmtStock($good->min_stock)) }}"
                inputmode="decimal"
                autocomplete="off"
            />
            <x-input-error class="mt-1.5 text-xs text-red-700" :messages="$errors->get('min_stock')" />
        </div>
    </div>

    <div class="ob-1c-foot flex flex-wrap items-center justify-end gap-2">
        <a href="{{ route('admin.sale-goods.index') }}" class="ob-tb-btn !no-underline">К списку</a>
        <button type="submit" class="ob-tb-btn ob-btn-submit">{{ $submitLabel }}</button>
    </div>
</form>
