<x-admin-layout pageTitle="Счёт на оплату" main-class="px-3 py-5 sm:px-4 lg:px-5">
    <div class="mx-auto max-w-xl">
        <div class="rounded-xl border border-amber-200/90 bg-amber-50 px-5 py-4 text-sm text-amber-950 shadow-sm">
            <p class="font-semibold">Нельзя сформировать объединённый счёт</p>
            <p class="mt-2">{{ $message }}</p>
        </div>
        <p class="mt-6">
            <a href="{{ route('admin.trade-invoices.index') }}" class="text-sm font-medium text-emerald-800 underline hover:text-emerald-700">← К списку</a>
        </p>
    </div>
</x-admin-layout>
