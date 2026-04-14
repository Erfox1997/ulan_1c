<x-superadmin-layout pageTitle="Новый администратор">
    <div class="mx-auto max-w-xl">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
            <form method="POST" action="{{ route('superadmin.admins.store') }}" class="space-y-6">
                @csrf
                <div>
                    <x-input-label for="branch_id" value="Филиал" />
                    <select
                        id="branch_id"
                        name="branch_id"
                        class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required
                    >
                        <option value="">— выберите —</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected(old('branch_id') == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="name" value="Имя" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :value="old('name')" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :value="old('email')" required />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="password" value="Пароль" />
                    <div class="mt-1 flex gap-2">
                        <x-text-input
                            id="password"
                            name="password"
                            type="password"
                            class="min-w-0 flex-1 rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                            autocomplete="new-password"
                        />
                        <button
                            type="button"
                            id="generate-password-btn"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 shadow-sm transition hover:border-emerald-400 hover:bg-emerald-50 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
                            title="Сгенерировать пароль"
                            aria-label="Сгенерировать пароль"
                        >
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                            </svg>
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="password_confirmation" value="Подтверждение пароля" />
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required autocomplete="new-password" />
                </div>
                <div class="flex flex-wrap gap-3">
                    <x-primary-button class="rounded-lg">Создать</x-primary-button>
                    <a href="{{ route('superadmin.admins.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Отмена</a>
                </div>
            </form>
        </div>
    </div>
    <script>
        (function () {
            const btn = document.getElementById('generate-password-btn');
            const pwd = document.getElementById('password');
            const confirm = document.getElementById('password_confirmation');
            if (!btn || !pwd || !confirm) return;

            function randomChar(set) {
                const buf = new Uint8Array(1);
                crypto.getRandomValues(buf);
                return set[buf[0] % set.length];
            }

            function shuffle(str) {
                const a = str.split('');
                for (let i = a.length - 1; i > 0; i--) {
                    const j = crypto.getRandomValues(new Uint8Array(1))[0] % (i + 1);
                    [a[i], a[j]] = [a[j], a[i]];
                }
                return a.join('');
            }

            function generatePassword() {
                const lower = 'abcdefghjkmnpqrstuvwxyz';
                const upper = 'ABCDEFGHJKMNPQRSTUVWXYZ';
                const digits = '23456789';
                const symbols = '!@#$%&*';
                const all = lower + upper + digits + symbols;
                let core = randomChar(lower) + randomChar(upper) + randomChar(digits) + randomChar(symbols);
                const buf = new Uint8Array(12);
                crypto.getRandomValues(buf);
                for (let i = 0; i < 12; i++) {
                    core += all[buf[i] % all.length];
                }
                return shuffle(core);
            }

            btn.addEventListener('click', function () {
                const value = generatePassword();
                pwd.value = value;
                confirm.value = value;
                pwd.type = 'text';
                confirm.type = 'text';
                pwd.focus();
            });
        })();
    </script>
</x-superadmin-layout>
