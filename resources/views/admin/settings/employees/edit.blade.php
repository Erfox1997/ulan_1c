<x-admin-layout :pageTitle="$pageTitle" main-class="bg-slate-100/80 px-3 py-4 sm:px-4 lg:px-6">
    <div class="mx-auto max-w-2xl space-y-6">
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
                <p class="mt-0.5 text-[11px] font-medium text-emerald-100/90">Изменение данных и пароля. Пустой пароль — не менять.</p>
            </div>

            <form method="POST" action="{{ route('admin.settings.employees.update', $employee) }}" class="space-y-5 px-4 py-5 sm:px-6">
                @csrf
                @method('PUT')

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">ФИО *</label>
                    <input
                        name="full_name"
                        value="{{ old('full_name', $employee->full_name) }}"
                        required
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">Должность</label>
                    <input
                        name="position"
                        value="{{ old('position', $employee->position) }}"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">Логин (e-mail) *</label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email', $employee->user->email) }}"
                        required
                        autocomplete="username"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                    />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700">Новый пароль</label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            autocomplete="new-password"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700">Повтор пароля</label>
                        <input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            autocomplete="new-password"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        />
                    </div>
                </div>
                <div>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-800 hover:bg-slate-100"
                        onclick="(function(){var c='abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';var s='';for(var i=0;i<12;i++)s+=c[Math.floor(Math.random()*c.length)];document.getElementById('password').value=s;document.getElementById('password_confirmation').value=s;})();"
                    >
                        Сгенерировать пароль
                    </button>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">Роль доступа</label>
                    <select
                        name="branch_role_id"
                        class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                    >
                        <option value="">— Полный доступ —</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected((string) old('branch_role_id', $employee->user->branch_role_id) === (string) $role->id)>{{ $role->name }}{{ $role->is_full_access ? ' (полный доступ)' : '' }}</option>
                        @endforeach
                    </select>
                </div>

                @include('admin.settings.employees._salary-fields', [
                    'salaryFixed' => old('salary_fixed', $employee->salary_fixed),
                    'salaryGoods' => old('salary_percent_goods', $employee->salary_percent_goods),
                    'salaryServices' => old('salary_percent_services', $employee->salary_percent_services),
                ])

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                        Сохранить
                    </button>
                    <a href="{{ route('admin.settings.employees') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50">К списку</a>
                </div>
            </form>

            <div class="border-t border-slate-100 px-4 py-4 sm:px-6">
                <form method="POST" action="{{ route('admin.settings.employees.destroy', $employee) }}" onsubmit="return confirm('Удалить сотрудника и учётную запись?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm font-medium text-rose-700 hover:text-rose-900">Удалить сотрудника</button>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
