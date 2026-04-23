<x-admin-layout pageTitle="Изменение товара" main-class="px-3 py-6 sm:px-6 lg:px-8">
    @include('admin.partials.cp-brush')
    <div class="cp-root mx-auto w-full max-w-3xl space-y-6">
        @include('admin.partials.status-flash')

        <div>
            <a
                href="{{ route('admin.sale-goods.index') }}"
                class="text-sm font-semibold text-sky-800 decoration-sky-300 underline-offset-2 hover:text-sky-950 hover:underline"
            >← К списку товаров</a>
        </div>

        @include('admin.purchase-receipts.partials.form-document-styles')

        <div
            class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50"
        >
            <div class="overflow-hidden rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90">
                <div class="ob-1c-scope overflow-hidden rounded-[1.5rem] bg-white/95">
                    <div
                        class="border-b border-emerald-200/55 bg-gradient-to-r from-emerald-50/95 via-white to-sky-50/50 px-4 py-3 sm:px-5"
                    >
                        <p class="mb-0.5 text-[10px] font-semibold uppercase tracking-wider text-teal-700/90">Справочник</p>
                        <h2 class="text-[15px] font-bold leading-tight text-slate-800">Товар: {{ $good->name }}</h2>
                        <p class="mt-1 text-xs text-slate-500">Артикул <span class="font-mono font-medium text-slate-700">{{ $good->article_code }}</span></p>
                    </div>
                    @include('admin.sale-goods.partials.form', ['submitLabel' => 'Сохранить'])
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
