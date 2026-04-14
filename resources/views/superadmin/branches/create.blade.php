<x-superadmin-layout pageTitle="Новый филиал">
    <div class="mx-auto max-w-xl">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
            <form method="POST" action="{{ route('superadmin.branches.store') }}" class="space-y-6">
                @csrf
                <div>
                    <x-input-label for="name" value="Название" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :value="old('name')" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="code" value="Код (необязательно)" />
                    <x-text-input id="code" name="code" type="text" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :value="old('code')" />
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                </div>
                <div class="flex items-center gap-2">
                    <input id="is_active" name="is_active" type="checkbox" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" {{ old('is_active', true) ? 'checked' : '' }}>
                    <label for="is_active" class="text-sm text-slate-600">Активен</label>
                </div>
                <div class="flex flex-wrap gap-3">
                    <x-primary-button class="rounded-lg">Создать</x-primary-button>
                    <a href="{{ route('superadmin.branches.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Отмена</a>
                </div>
            </form>
        </div>
    </div>
</x-superadmin-layout>
