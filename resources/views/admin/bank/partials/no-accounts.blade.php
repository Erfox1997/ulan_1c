@if ($accounts->isEmpty())
    <div class="rounded-xl border border-amber-200/80 bg-amber-50 px-4 py-4 text-sm text-amber-950">
        <p class="font-medium">Нет счетов организации для этого филиала.</p>
        <p class="mt-2 text-amber-900/90">
            Добавьте счета в разделе
            <a href="{{ route('admin.organizations.index') }}" class="font-semibold text-emerald-800 underline hover:text-emerald-700">Данные организации</a>.
        </p>
    </div>
@endif
