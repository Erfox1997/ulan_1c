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
                <p class="mt-0.5 text-[11px] font-medium text-emerald-100/90">
                    Выберите сотрудника и назначьте роль и/или отметьте разделы. Галочки у сотрудника <span class="font-semibold">добавляются</span> к правам роли; если роль не выбрана — действует <span class="font-semibold">только</span> отмеченный список (без роли и без галочек — полный доступ).
                </p>
            </div>

            <div class="border-b border-slate-100 px-4 py-5 sm:px-6">
                <h2 class="text-sm font-semibold text-slate-900">Доступ сотрудника</h2>
                @if ($employees->isEmpty())
                    <p class="mt-2 text-sm text-slate-600">Сотрудников пока нет — добавьте их в разделе <a href="{{ route('admin.settings.employees.create') }}" class="font-medium text-emerald-700 underline">Сотрудники</a>.</p>
                @else
                    <form method="GET" action="{{ route('admin.settings.responsible') }}" class="mt-3 max-w-md">
                        <label class="mb-1 block text-xs font-semibold text-slate-700">Сотрудник</label>
                        <select
                            name="employee"
                            onchange="this.form.submit()"
                            class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        >
                            <option value="">— Выберите сотрудника —</option>
                            @foreach ($employees as $e)
                                <option value="{{ $e->id }}" @selected($selectedEmployee && (int) $selectedEmployee->id === (int) $e->id)>{{ $e->full_name }}</option>
                            @endforeach
                        </select>
                    </form>

                    @if ($selectedEmployee)
                        @php
                            $eu = $selectedEmployee->user;
                            $individualSelected = $eu->branchUserPermissions->pluck('route_pattern')->all();
                        @endphp
                        <form
                            method="POST"
                            action="{{ route('admin.settings.responsible.employees.access', $selectedEmployee) }}"
                            class="mt-6 space-y-4"
                        >
                            @csrf
                            @method('PATCH')

                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-700">Роль</label>
                                <select
                                    name="branch_role_id"
                                    class="w-full max-w-md rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                >
                                    <option value="">— Нет (полный доступ, если нет индивидуальных разделов ниже) —</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" @selected((int) $eu->branch_role_id === (int) $role->id)>{{ $role->name }}{{ $role->is_full_access ? ' (полный доступ)' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="space-y-2">
                                <p class="text-xs font-semibold text-slate-700">Индивидуальный доступ к разделам</p>
                                <p class="text-xs text-slate-500">Отмеченные пункты суммируются с правами роли (если роль с ограниченным списком). Без роли — только этот список.</p>
                                @include('admin.settings.responsible._permission-fields', [
                                    'catalogItems' => $catalogItems,
                                    'selected' => old('permissions', $individualSelected),
                                    'idPrefix' => 'emp-'.$selectedEmployee->id,
                                ])
                            </div>

                            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                                Сохранить доступ
                            </button>
                        </form>
                    @endif
                @endif
            </div>

            <div class="border-b border-slate-100 px-4 py-5 sm:px-6">
                <h2 class="text-sm font-semibold text-slate-900">Новая роль</h2>
                <form method="POST" action="{{ route('admin.settings.responsible.roles.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-700">Название</label>
                            <input
                                name="name"
                                value="{{ old('name') }}"
                                required
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                placeholder="Например: Кассир"
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
                                    @checked(old('is_full_access'))
                                />
                                Полный доступ ко всем разделам
                            </label>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <p class="text-xs font-semibold text-slate-700">Доступ к разделам (если не отмечен полный доступ)</p>
                        @include('admin.settings.responsible._permission-fields', ['catalogItems' => $catalogItems, 'selected' => old('permissions', []), 'idPrefix' => 'role-new'])
                    </div>

                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                        Создать роль
                    </button>
                </form>
            </div>

            <div class="px-4 py-5 sm:px-6">
                <h2 class="text-sm font-semibold text-slate-900">Роли филиала</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="py-2 pr-4">Название</th>
                                <th class="py-2 pr-4">Режим</th>
                                <th class="py-2 pr-4">Пользователей</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($roles as $role)
                                <tr>
                                    <td class="py-2.5 pr-4 font-medium text-slate-900">{{ $role->name }}</td>
                                    <td class="py-2.5 pr-4 text-slate-600">
                                        @if ($role->is_full_access)
                                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800">Полный доступ</span>
                                        @else
                                            <span class="text-xs">По списку ({{ $role->permissions->count() }})</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 pr-4 tabular-nums text-slate-700">{{ $role->users_count }}</td>
                                    <td class="py-2.5 text-right">
                                        <a href="{{ route('admin.settings.responsible.roles.edit', $role) }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-900">Изменить</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-sm text-slate-500">Пока нет ролей.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($usersWithoutEmployee->isNotEmpty())
                <div class="border-t border-slate-100 px-4 py-5 sm:px-6">
                    <h2 class="text-sm font-semibold text-slate-900">Пользователи без карточки сотрудника</h2>
                    <p class="mt-1 text-xs text-slate-500">Только назначение роли (например, учётная запись из панели суперадмина). Детальные галочки — после <a href="{{ route('admin.settings.employees') }}" class="font-medium text-emerald-700 underline">добавления сотрудника</a>.</p>
                    <div class="mt-4 space-y-4">
                        @foreach ($usersWithoutEmployee as $u)
                            <form
                                method="POST"
                                action="{{ route('admin.settings.responsible.users.role', $u) }}"
                                class="flex flex-wrap items-end gap-3 border-b border-slate-100 pb-4 last:border-0 last:pb-0"
                            >
                                @csrf
                                @method('PATCH')
                                <div class="min-w-[12rem] flex-1">
                                    <p class="text-sm font-medium text-slate-900">{{ $u->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $u->email }}</p>
                                </div>
                                <div class="min-w-[14rem]">
                                    <label class="mb-1 block text-[11px] font-semibold text-slate-600">Роль</label>
                                    <select
                                        name="branch_role_id"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                    >
                                        <option value="">— Полный доступ (без роли) —</option>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}" @selected((int) $u->branch_role_id === (int) $role->id)>{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-800 shadow-sm hover:bg-slate-50">
                                    Сохранить
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
