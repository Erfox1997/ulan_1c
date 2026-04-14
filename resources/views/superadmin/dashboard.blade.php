<x-superadmin-layout pageTitle="Панель">
    <div class="mx-auto max-w-5xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200/80 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Филиалов</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $branchesCount }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Администраторов филиалов</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $branchAdminsCount }}</p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 text-sm leading-relaxed text-slate-600 shadow-sm">
            <p>Управляйте филиалами и назначайте администраторов через меню слева.</p>
        </div>
    </div>
</x-superadmin-layout>
