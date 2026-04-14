<x-admin-layout pageTitle="Новый контрагент" main-class="px-3 py-6 sm:px-6 lg:px-8">
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

        @include('admin.counterparties.partials.form', ['submitLabel' => 'Записать'])
    </div>
</x-admin-layout>
