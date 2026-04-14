@props([
    'action',
    'filterFrom',
    'filterTo',
])
<form method="GET" action="{{ $action }}" class="flex flex-wrap items-end gap-3">
    <div>
        <label class="mb-1 block text-xs font-semibold text-slate-700">С даты</label>
        <input
            type="date"
            name="from"
            value="{{ $filterFrom }}"
            required
            class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
        />
    </div>
    <div>
        <label class="mb-1 block text-xs font-semibold text-slate-700">По дату</label>
        <input
            type="date"
            name="to"
            value="{{ $filterTo }}"
            required
            class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
        />
    </div>
    <button
        type="submit"
        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
    >
        Сформировать
    </button>
</form>
