<x-superadmin-layout pageTitle="Администраторы">
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold tracking-tight text-slate-900">Администраторы филиалов</h1>
            <a
                href="{{ route('superadmin.admins.create') }}"
                class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500"
            >
                Создать администратора
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200/80 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50/90">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Имя</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Email</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Филиал</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($admins as $admin)
                            <tr class="hover:bg-slate-50/80">
                                <td class="whitespace-nowrap px-5 py-3.5 text-sm font-medium text-slate-900">{{ $admin->name }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-sm text-slate-600">{{ $admin->email }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-sm text-slate-600">{{ $admin->branch?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-5 py-8 text-center text-sm text-slate-500">
                                    Нет администраторов. Создайте филиал и назначьте администратора.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="px-1">{{ $admins->links() }}</div>
    </div>
</x-superadmin-layout>
