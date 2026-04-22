{{-- $esfProfile: esfGoodsServicesLinesProfile() --}}
@if (! $esfProfile['has_goods'] && ! $esfProfile['has_services'])
    <span class="text-[11px] text-amber-800" title="Нет строк с привязкой к номенклатуре">нет позиций</span>
@elseif ($esfProfile['mixed'])
    <div class="flex flex-wrap items-center gap-1">
        <span class="inline-flex rounded border border-sky-200 bg-sky-50 px-1.5 py-0.5 text-[10px] font-semibold text-sky-900">Товары</span>
        <span class="inline-flex rounded border border-violet-200 bg-violet-50 px-1.5 py-0.5 text-[10px] font-semibold text-violet-900">Услуги</span>
    </div>
@elseif ($esfProfile['has_goods'])
    <span class="inline-flex rounded border border-sky-200 bg-sky-50 px-2 py-0.5 text-[11px] font-semibold text-sky-900">Товары</span>
@else
    <span class="inline-flex rounded border border-violet-200 bg-violet-50 px-2 py-0.5 text-[11px] font-semibold text-violet-900">Услуги</span>
@endif
