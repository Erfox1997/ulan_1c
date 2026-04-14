@props([
    'action',
    'method' => 'POST',
    'employees',
    'submitLabel',
    'cancelRoute',
    'selectedEmployeeId' => null,
    'entryDate' => null,
    'amount' => null,
    'note' => null,
    'amountLabel' => 'Сумма *',
])
<form method="POST" action="{{ $action }}" class="space-y-5 px-4 py-5 sm:px-6">
    @csrf
    @if ($method === 'PUT')
        @method('PUT')
    @endif

    <div>
        <label class="mb-1 block text-xs font-semibold text-slate-700">Сотрудник *</label>
        <select
            name="employee_id"
            required
            class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
        >
            <option value="">— выберите —</option>
            @foreach ($employees as $e)
                <option value="{{ $e->id }}" @selected((string) old('employee_id', $selectedEmployeeId) === (string) $e->id)>{{ $e->full_name }}</option>
            @endforeach
        </select>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-700">Дата *</label>
            <input
                type="date"
                name="entry_date"
                value="{{ old('entry_date', $entryDate) }}"
                required
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            />
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-700">{{ $amountLabel }}</label>
            <input
                type="text"
                name="amount"
                inputmode="decimal"
                value="{{ old('amount', $amount) }}"
                required
                placeholder="0,00"
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            />
        </div>
    </div>

    <div>
        <label class="mb-1 block text-xs font-semibold text-slate-700">Комментарий</label>
        <textarea
            name="note"
            rows="3"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
        >{{ old('note', $note) }}</textarea>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
            {{ $submitLabel }}
        </button>
        <a href="{{ route($cancelRoute) }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50">Отмена</a>
    </div>
</form>
