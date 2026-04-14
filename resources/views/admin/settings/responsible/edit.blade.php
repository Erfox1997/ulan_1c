<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-5xl space-y-6">
        @include('admin.partials.status-flash')

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
            <div
                class="border-b border-emerald-900/10 px-4 py-3 text-white sm:px-5"
                style="background: linear-gradient(120deg, #047857 0%, #0d9488 50%, #0f766e 100%);"
            >
                <h1 class="text-sm font-bold tracking-tight">{{ $pageTitle }}</h1>
                <p class="mt-0.5 text-[11px] font-medium text-emerald-100/90">Изменение роли и прав доступа к разделам меню.</p>
            </div>

            <form method="POST" action="{{ route('admin.settings.responsible.roles.update', $role) }}" class="space-y-6 px-4 py-5 sm:px-6">
                @csrf
                @method('PUT')

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700">Название</label>
                        <input
                            name="name"
                            value="{{ old('name', $role->name) }}"
                            required
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        />
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-800">
                            <input type="hidden" name="is_full_access" value="0" />
                            <input
                                type="checkbox"
                                name="is_full_access"
                                value="1"
                                class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/30"
                                @checked(old('is_full_access', $role->is_full_access))
                            />
                            Полный доступ ко всем разделам
                        </label>
                    </div>
                </div>

                <div class="space-y-2">
                    <p class="text-xs font-semibold text-slate-700">Доступ к разделам (если не отмечен полный доступ)</p>
                    @include('admin.settings.responsible._permission-fields', [
                        'catalogItems' => $catalogItems,
                        'selected' => old('permissions', $selectedPatterns),
                        'idPrefix' => 'role-'.$role->id,
                    ])
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                        Сохранить
                    </button>
                    <a href="{{ route('admin.settings.responsible') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">Назад к списку</a>
                </div>
            </form>

            @if ($canDelete)
                <div class="border-t border-slate-100 px-4 py-4 sm:px-6">
                    <form method="POST" action="{{ route('admin.settings.responsible.roles.destroy', $role) }}" onsubmit="return confirm('Удалить роль «{{ $role->name }}»?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm font-medium text-rose-700 hover:text-rose-900">Удалить роль</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
