@props([
    'salaryFixed' => null,
    'salaryGoods' => null,
    'salaryServices' => null,
    'salaryContractSeparate' => false,
])
<div class="grid gap-4 sm:grid-cols-3">
    <div>
        <label class="mb-1 block text-xs font-semibold text-slate-700">Оклад (фикс)</label>
        <input
            type="text"
            inputmode="decimal"
            name="salary_fixed"
            value="{{ old('salary_fixed', $salaryFixed) }}"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            placeholder="0"
        />
    </div>
    <div>
        <label class="mb-1 block text-xs font-semibold text-slate-700">% от продаж товаров</label>
        <input
            type="text"
            inputmode="decimal"
            name="salary_percent_goods"
            value="{{ old('salary_percent_goods', $salaryGoods) }}"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            placeholder="0"
        />
    </div>
    <div>
        <label class="mb-1 block text-xs font-semibold text-slate-700">% от продаж услуг</label>
        <input
            type="text"
            inputmode="decimal"
            name="salary_percent_services"
            value="{{ old('salary_percent_services', $salaryServices) }}"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            placeholder="0"
        />
    </div>
</div>
<div class="mt-4 rounded-lg border border-slate-200/90 bg-slate-50/80 px-3 py-3">
    <label class="flex cursor-pointer items-center gap-3">
        <input type="hidden" name="salary_contract_separate" value="0" />
        <input
            type="checkbox"
            name="salary_contract_separate"
            value="1"
            class="h-4 w-4 shrink-0 rounded border-slate-400 text-emerald-600 focus:ring-emerald-500/30"
            @checked((string) old('salary_contract_separate', $salaryContractSeparate ? '1' : '0') === '1')
        />
        <span class="text-xs font-semibold text-slate-800">Отдельная зарплата по договору</span>
    </label>
</div>
<p class="mt-2 text-xs text-slate-500">Проценты — от 0 до 100.</p>
