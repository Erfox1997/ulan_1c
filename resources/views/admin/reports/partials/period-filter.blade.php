@props([
    'action',
    'filterFrom',
    'filterTo',
    /** @var array<string, scalar|null> $preserveQuery */
    'preserveQuery' => [],
    /** Если false — только поля периода (без &lt;form&gt;), для вложения в родительскую форму. */
    'wrapForm' => true,
])
@if ($wrapForm)
    <form method="GET" action="{{ $action }}" class="flex flex-wrap items-end gap-3">
@else
    <div class="flex flex-wrap items-end gap-3">
@endif
    @foreach ($preserveQuery ?? [] as $paramName => $paramValue)
        @if ($paramValue !== null && $paramValue !== '')
            <input type="hidden" name="{{ $paramName }}" value="{{ $paramValue }}" />
        @endif
    @endforeach
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
@if ($wrapForm)
    </form>
@else
    </div>
@endif
