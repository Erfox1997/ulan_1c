<x-admin-layout pageTitle="Изменение склада" main-class="px-3 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto w-full max-w-4xl space-y-6">
        @if (session('status'))
            <div
                class="flex items-start gap-3 rounded-2xl border border-emerald-200/90 bg-gradient-to-r from-emerald-50 to-teal-50/80 px-4 py-3 text-sm text-emerald-950 shadow-sm"
                role="status"
            >
                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-700" aria-hidden="true">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <div class="rounded-[1.75rem] bg-gradient-to-br from-sky-100/60 via-white to-emerald-100/50 p-[3px] shadow-[0_12px_40px_-12px_rgba(14,165,233,0.2)] ring-1 ring-sky-200/50">
            <div class="rounded-[1.65rem] bg-gradient-to-b from-white/95 to-slate-50/90 px-3 py-4 sm:px-5 sm:py-6">
                @include('admin.warehouses.partials.form', [
                    'submitLabel' => 'Сохранить',
                    'formTitle' => 'Склад: '.$warehouse->name,
                ])
            </div>
        </div>
    </div>
</x-admin-layout>
