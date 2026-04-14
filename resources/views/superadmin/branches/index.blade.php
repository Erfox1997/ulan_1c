<x-superadmin-layout pageTitle="Филиалы">
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold tracking-tight text-slate-900">Филиалы</h1>
            <a
                href="{{ route('superadmin.branches.create') }}"
                class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500"
            >
                Добавить
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
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Название</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Код</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Активен</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($branches as $branch)
                            <tr class="hover:bg-slate-50/80">
                                <td class="whitespace-nowrap px-5 py-3.5 text-sm font-medium text-slate-900">{{ $branch->name }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-sm text-slate-600">{{ $branch->code ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-sm text-slate-600">{{ $branch->is_active ? 'Да' : 'Нет' }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-right text-sm">
                                    <a href="{{ route('superadmin.branches.edit', $branch) }}" class="font-medium text-indigo-600 hover:text-indigo-500">Изменить</a>
                                    <span class="mx-2 text-slate-300">|</span>
                                    <form action="{{ route('superadmin.branches.destroy', $branch) }}" method="POST" class="inline" onsubmit="return confirm('Удалить филиал?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="font-medium text-red-600 hover:text-red-500">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-8 text-center text-sm text-slate-500">Нет филиалов.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="px-1">{{ $branches->links() }}</div>
    </div>
</x-superadmin-layout>
