{{--
    Модальное окно «Новый товар» (Alpine): newGoodModalOpen, newGoodSaving, newGoodError, newGoodForm,
    closeNewGoodModal(), submitNewGoodQuickStore()
    @param string $idPrefix префикс id полей (pr | prt | les)
    @param string|null $categoriesSuggestUrl URL подсказок категорий (по умолчанию admin.goods.categories)
--}}
@php
    $p = $idPrefix ?? 'gqm';
    $categoriesSuggestUrl = $categoriesSuggestUrl ?? route('admin.goods.categories');
@endphp
<template x-teleport="body">
    <div
        x-show="newGoodModalOpen"
        x-cloak
        class="fixed inset-0 z-[450] flex justify-center overflow-y-auto px-4 py-8 sm:py-12"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $p }}-new-good-title"
    >
        <div class="fixed inset-0 z-0 bg-slate-900/50 backdrop-blur-[2px]" @click.prevent="closeNewGoodModal()" aria-hidden="true"></div>
        <div
            class="relative z-10 mt-4 flex w-full max-w-lg flex-col self-start overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-[0_25px_50px_-12px_rgba(15,23,42,0.25)] sm:mt-8 sm:max-w-2xl"
            @click.stop
        >
            <div class="bg-[#008b8b] px-5 py-4 text-white">
                <h3 id="{{ $p }}-new-good-title" class="text-base font-bold tracking-tight">Новый товар</h3>
                <p class="mt-1 text-sm font-normal text-white/90">Артикул присвоится автоматически. Заполните карточку и сохраните — позиция попадёт в документ.</p>
            </div>
            <div class="max-h-[min(70vh,40rem)] overflow-y-auto px-5 py-4">
                <p x-show="newGoodError" x-text="newGoodError" class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"></p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700" for="{{ $p }}_new_good_name">Наименование *</label>
                        <input
                            id="{{ $p }}_new_good_name"
                            type="text"
                            x-model="newGoodForm.name"
                            required
                            maxlength="500"
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-[#008b8b] focus:outline-none focus:ring-2 focus:ring-[#008b8b]/25"
                            autocomplete="off"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700" for="{{ $p }}_new_good_barcode">Штрихкод</label>
                        <input
                            id="{{ $p }}_new_good_barcode"
                            type="text"
                            x-model="newGoodForm.barcode"
                            maxlength="64"
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-[#008b8b] focus:outline-none focus:ring-2 focus:ring-[#008b8b]/25"
                            autocomplete="off"
                        />
                    </div>
                    <div class="min-w-0">
                        <div
                            x-data="quickGoodCategoryPicker(@js($categoriesSuggestUrl))"
                            x-modelable="category"
                            x-model="newGoodForm.category"
                            class="space-y-1"
                        >
                            <label class="block text-xs font-semibold text-slate-700" for="{{ $p }}_new_good_category">Категория</label>
                            <select
                                id="{{ $p }}_new_good_category"
                                x-model="pickValue"
                                @change="onPickChange()"
                                @focus="items.length === 0 && !loading && loadItems()"
                                class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-[#008b8b] focus:outline-none focus:ring-2 focus:ring-[#008b8b]/25"
                            >
                                <option value="">— Не выбрано —</option>
                                <template x-for="c in items" :key="c">
                                    <option :value="c" x-text="c"></option>
                                </template>
                                <option value="__new__">+ Новая категория…</option>
                            </select>
                            <p x-show="loading" x-cloak class="text-[10px] text-slate-500">Загрузка списка…</p>
                            <div x-show="pickValue === '__new__'" x-cloak class="mt-1">
                                <label class="sr-only" for="{{ $p }}_new_good_category_new">Название новой категории</label>
                                <input
                                    id="{{ $p }}_new_good_category_new"
                                    type="text"
                                    x-model="newName"
                                    @input="onNewNameInput()"
                                    maxlength="120"
                                    placeholder="Введите название новой категории"
                                    class="w-full rounded-xl border border-dashed border-teal-400/70 bg-teal-50/40 px-3 py-2 text-sm shadow-sm placeholder:text-slate-400 focus:border-[#008b8b] focus:outline-none focus:ring-2 focus:ring-[#008b8b]/25"
                                    autocomplete="off"
                                />
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700" for="{{ $p }}_new_good_unit">Ед. изм.</label>
                        <input
                            id="{{ $p }}_new_good_unit"
                            type="text"
                            x-model="newGoodForm.unit"
                            maxlength="32"
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-[#008b8b] focus:outline-none focus:ring-2 focus:ring-[#008b8b]/25"
                            autocomplete="off"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700" for="{{ $p }}_new_good_qty">Количество *</label>
                        <input
                            id="{{ $p }}_new_good_qty"
                            type="text"
                            x-model="newGoodForm.quantity"
                            required
                            inputmode="decimal"
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-[#008b8b] focus:outline-none focus:ring-2 focus:ring-[#008b8b]/25"
                            autocomplete="off"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700" for="{{ $p }}_new_good_purchase">Цена (закуп.)</label>
                        <input
                            id="{{ $p }}_new_good_purchase"
                            type="text"
                            x-model="newGoodForm.unit_price"
                            inputmode="decimal"
                            placeholder="0,00"
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-[#008b8b] focus:outline-none focus:ring-2 focus:ring-[#008b8b]/25"
                            autocomplete="off"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700" for="{{ $p }}_new_good_wholesale">Оптовая цена</label>
                        <input
                            id="{{ $p }}_new_good_wholesale"
                            type="text"
                            x-model="newGoodForm.wholesale_price"
                            inputmode="decimal"
                            placeholder="0,00"
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-[#008b8b] focus:outline-none focus:ring-2 focus:ring-[#008b8b]/25"
                            autocomplete="off"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700" for="{{ $p }}_new_good_sale">Цена (продаж.)</label>
                        <input
                            id="{{ $p }}_new_good_sale"
                            type="text"
                            x-model="newGoodForm.sale_price"
                            inputmode="decimal"
                            placeholder="0,00"
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-[#008b8b] focus:outline-none focus:ring-2 focus:ring-[#008b8b]/25"
                            autocomplete="off"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700" for="{{ $p }}_new_good_oem">ОЭМ</label>
                        <input
                            id="{{ $p }}_new_good_oem"
                            type="text"
                            x-model="newGoodForm.oem"
                            maxlength="120"
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-[#008b8b] focus:outline-none focus:ring-2 focus:ring-[#008b8b]/25"
                            autocomplete="off"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700" for="{{ $p }}_new_good_factory">Заводской №</label>
                        <input
                            id="{{ $p }}_new_good_factory"
                            type="text"
                            x-model="newGoodForm.factory_number"
                            maxlength="120"
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-[#008b8b] focus:outline-none focus:ring-2 focus:ring-[#008b8b]/25"
                            autocomplete="off"
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700" for="{{ $p }}_new_good_min_stock">Мин. остаток</label>
                        <input
                            id="{{ $p }}_new_good_min_stock"
                            type="text"
                            x-model="newGoodForm.min_stock"
                            inputmode="decimal"
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-[#008b8b] focus:outline-none focus:ring-2 focus:ring-[#008b8b]/25"
                            autocomplete="off"
                        />
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/80 px-5 py-3">
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                    @click="closeNewGoodModal()"
                    :disabled="newGoodSaving"
                >
                    Отмена
                </button>
                <button
                    type="button"
                    class="rounded-xl bg-[#008b8b] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#007373] disabled:opacity-60"
                    @click="submitNewGoodQuickStore()"
                    :disabled="newGoodSaving"
                >
                    <span x-show="!newGoodSaving">Сохранить и добавить</span>
                    <span x-show="newGoodSaving">Сохранение…</span>
                </button>
            </div>
        </div>
    </div>
</template>
