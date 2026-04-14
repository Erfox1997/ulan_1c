<x-superadmin-layout :pageTitle="$pageTitle">
    <div class="mx-auto max-w-xl space-y-4">
        <div class="overflow-hidden rounded-xl border border-rose-200/90 bg-white shadow-md ring-1 ring-rose-900/[0.06]">
            <div class="border-b border-rose-100 bg-rose-50/90 px-4 py-3 sm:px-5">
                <h1 class="text-sm font-bold tracking-tight text-rose-950">{{ $pageTitle }}</h1>
                <p class="mt-1 text-[12px] leading-snug text-rose-900/85">
                    Будут очищены <strong>все</strong> таблицы базы, кроме <code class="rounded bg-rose-100/80 px-1 py-0.5 text-[11px]">users</code>
                    (логины и пароли) и <code class="rounded bg-rose-100/80 px-1 py-0.5 text-[11px]">migrations</code> (версия схемы Laravel).
                    У всех пользователей будет сброшена привязка к филиалу.
                </p>
            </div>

            <form method="POST" action="{{ route('superadmin.clear-database.run') }}" class="space-y-4 px-4 py-5 sm:px-5">
                @csrf
                <div>
                    <label for="confirm_text" class="mb-1 block text-xs font-semibold text-slate-700">
                        Для подтверждения введите слово <span class="font-mono text-rose-800">ОЧИСТИТЬ</span>
                    </label>
                    <input
                        id="confirm_text"
                        name="confirm_text"
                        type="text"
                        autocomplete="off"
                        value="{{ old('confirm_text') }}"
                        class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/20"
                        placeholder="ОЧИСТИТЬ"
                    />
                    @error('confirm_text')
                        <p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="submit"
                        class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-700"
                    >
                        Удалить данные
                    </button>
                    <a
                        href="{{ route('superadmin.dashboard') }}"
                        class="text-sm font-medium text-slate-600 underline hover:text-slate-900"
                    >
                        Отмена
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-superadmin-layout>
