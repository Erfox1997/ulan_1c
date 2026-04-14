@props([
    'catalogItems',
    /** @var list<string> $selected */
    'selected' => [],
    'idPrefix' => 'p',
])
@php
    $grouped = collect($catalogItems)->groupBy('group');
@endphp
<div class="max-h-[min(70vh,520px)] space-y-5 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50/80 p-4">
    @foreach ($grouped as $group => $items)
        <div>
            <p class="mb-2 text-[11px] font-bold uppercase tracking-wide text-slate-500">{{ $group }}</p>
            <ul class="space-y-2">
                @foreach ($items as $item)
                    <li class="flex gap-2 text-sm leading-snug text-slate-800">
                        <input
                            type="checkbox"
                            name="permissions[]"
                            value="{{ $item['pattern'] }}"
                            id="{{ $idPrefix }}-perm-{{ md5($item['pattern']) }}"
                            class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/30"
                            @checked(in_array($item['pattern'], $selected, true))
                        />
                        <label for="{{ $idPrefix }}-perm-{{ md5($item['pattern']) }}" class="cursor-pointer select-none">{{ $item['label'] }}</label>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
<p class="mt-2 text-xs text-slate-500">Шаблоны совпадают с именами маршрутов Laravel (как в <code class="rounded bg-slate-100 px-1">routeIs</code>): <code class="rounded bg-slate-100 px-1">*</code> — любой суффикс.</p>
