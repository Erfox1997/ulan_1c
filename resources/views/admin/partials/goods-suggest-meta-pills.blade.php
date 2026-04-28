{{-- Ост / цена / опт в подсказке поиска товара — жирные подписи для читаемости. --}}
<div
    class="mt-0.5 flex flex-wrap gap-1.5"
    x-show="goodsSuggestMetaParts(item).length > 0"
>
    <template x-for="(part, pi) in goodsSuggestMetaParts(item)" :key="pi">
        <span
            class="inline-flex items-baseline gap-0.5 rounded-md border px-1.5 py-0.5 text-[10px] leading-tight shadow-sm ring-1"
            :class="part.danger
                ? 'border-red-200/85 bg-red-50/85 ring-red-100/60'
                : 'border-slate-200/80 bg-emerald-50/60 ring-emerald-100/50'"
        >
            <span
                class="font-bold"
                :class="part.danger ? 'text-red-800' : 'text-teal-900'"
                x-text="part.label + ':'"
            ></span>
            <span
                class="font-semibold tabular-nums"
                :class="part.danger ? 'text-red-900' : 'text-slate-900'"
                x-text="part.value"
            ></span>
        </span>
    </template>
</div>
