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

        @if ($employees->isEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                Нет сотрудников. Добавьте их в
                <a href="{{ route('admin.settings.employees') }}" class="font-medium underline">«Настройки → Сотрудники»</a>.
            </div>
            <p class="text-sm"><a href="{{ route('admin.payroll.penalties.index') }}" class="text-emerald-700 hover:text-emerald-900">← Назад к списку</a></p>
        @else
            <div class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-md ring-1 ring-slate-900/[0.04]">
                <div
                    class="border-b border-emerald-900/10 px-4 py-3 text-white sm:px-5"
                    style="background: linear-gradient(120deg, #047857 0%, #0d9488 50%, #0f766e 100%);"
                >
                    <h1 class="text-sm font-bold tracking-tight">{{ $pageTitle }}</h1>
                    <p class="mt-0.5 text-[11px] font-medium text-emerald-100/90">Укажите сумму удержания (положительное число).</p>
                </div>

                @include('admin.payroll.partials.employee-amount-form', [
                    'action' => route('admin.payroll.penalties.store'),
                    'method' => 'POST',
                    'employees' => $employees,
                    'submitLabel' => 'Сохранить',
                    'cancelRoute' => 'admin.payroll.penalties.index',
                    'selectedEmployeeId' => old('employee_id'),
                    'entryDate' => old('entry_date'),
                    'amount' => old('amount'),
                    'note' => old('note'),
                    'amountLabel' => 'Сумма штрафа *',
                ])
            </div>
        @endif
    </div>
</x-admin-layout>
