@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Employee> $masters */
    /** @var \App\Models\ServiceOrder|null $order */
    $o = $order ?? null;
    $docDate = old('document_date', $o?->document_date?->format('Y-m-d') ?? $defaultDocumentDate);
    $mileageVal = old('mileage_km');
    if ($mileageVal === null && $o?->mileage_km !== null) {
        $mileageVal = (string) $o->mileage_km;
    }
@endphp
<div class="space-y-3 rounded-xl border border-slate-200/90 bg-white p-3 shadow-sm ring-1 ring-slate-900/[0.03] sm:p-4">
    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Заявка</p>
    @if ($masters->isEmpty())
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
            Добавьте в настройках сотрудников с должностью «Мастер» — иначе нельзя указать ответственного.
        </div>
    @endif

    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-[10px] font-bold uppercase text-slate-500">Дата *</label>
            <input
                type="date"
                name="document_date"
                value="{{ $docDate }}"
                required
                class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            />
        </div>
        <div>
            <label class="mb-1 block text-[10px] font-bold uppercase text-slate-500">Срок исполнения *</label>
            <input
                type="date"
                name="deadline_date"
                value="{{ old('deadline_date', $o?->deadline_date?->format('Y-m-d')) }}"
                required
                class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            />
        </div>
    </div>

    <div class="relative">
        <label class="mb-1 block text-[10px] font-bold uppercase text-slate-500">Клиент *</label>
        <input type="hidden" name="counterparty_id" x-bind:value="counterpartyId ?? ''" />
        <div class="flex flex-wrap gap-2">
            <input
                type="search"
                x-model="cpQuery"
                @focus="cpOpen = true; if (cpQuery.trim().length >= 2) searchCp()"
                @input.debounce.300ms="searchCp()"
                @keydown.escape="cpOpen = false"
                autocomplete="off"
                class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            />
            <button
                type="button"
                class="shrink-0 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                @click="clearCp()"
            >
                Сброс
            </button>
            <button
                type="button"
                class="shrink-0 rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-900 hover:bg-emerald-100"
                @click="clientModalOpen = true; quickError = ''"
            >
                Новый клиент
            </button>
        </div>
        <div
            x-cloak
            x-show="cpOpen && (cpLoading || cpItems.length || (cpQuery.trim().length >= 2 && !cpLoading))"
            @click.outside="cpOpen = false"
            class="absolute left-0 right-0 top-full z-[220] mt-1 max-h-56 overflow-y-auto rounded-lg border border-slate-300 bg-white py-0.5 shadow-xl ring-1 ring-slate-900/10"
        >
            <div x-show="cpLoading" class="px-3 py-2 text-xs text-slate-500">Поиск…</div>
            <div
                x-show="!cpLoading && cpQuery.trim().length >= 2 && cpItems.length === 0"
                class="px-3 py-2 text-xs text-slate-500"
            >
                Ничего не найдено
            </div>
            <template x-for="item in cpItems" :key="item.id">
                <button
                    type="button"
                    class="flex w-full flex-col items-start gap-0.5 border-b border-slate-50 px-3 py-2 text-left text-sm hover:bg-emerald-50/80"
                    @click="pickCp(item)"
                >
                    <span class="font-medium text-slate-900" x-text="item.full_name || item.name"></span>
                    <span class="text-[11px] text-slate-500" x-show="item.inn" x-text="'ИНН: ' + item.inn"></span>
                </button>
            </template>
        </div>
    </div>

    <div>
        <label class="mb-1 block text-[10px] font-bold uppercase text-slate-500">ФИО представителя *</label>
        <input
            type="text"
            name="contact_name"
            value="{{ old('contact_name', $o?->contact_name) }}"
            required
            autocomplete="name"
            class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
        />
    </div>

    <div class="space-y-2">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <label class="block text-[10px] font-bold uppercase text-slate-500">Автомобиль *</label>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <button
                    type="button"
                    class="text-xs font-semibold text-emerald-700 hover:underline disabled:opacity-40"
                    :disabled="!counterpartyId"
                    @click="openVehicleModal()"
                >
                    Добавить авто
                </button>
                <button
                    type="button"
                    class="text-xs font-semibold text-sky-800 hover:underline disabled:opacity-40"
                    :disabled="!customerVehicleId || !vehicleHistoryUrlBase"
                    @click="openVehicleHistory()"
                >
                    История машины
                </button>
            </div>
        </div>
        <select
            name="customer_vehicle_id"
            x-model="customerVehicleId"
            required
            class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
        >
            <option value="">Выберите или добавьте автомобиль</option>
            <template x-for="v in vehicles" :key="v.id">
                <option :value="String(v.id)" x-text="vehicleLabel(v)"></option>
            </template>
        </select>
        <p x-show="vehicleLoading" class="text-[11px] text-slate-500">Загрузка списка…</p>
    </div>

    <div>
        <label class="mb-1 block text-[10px] font-bold uppercase text-slate-500">Пробег (км) *</label>
        <input
            name="mileage_km"
            value="{{ $mileageVal }}"
            required
            inputmode="decimal"
            class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
        />
    </div>

    <div>
        <label class="mb-1 block text-[10px] font-bold uppercase text-slate-500">Ответственный мастер *</label>
        <select
            name="lead_master_employee_id"
            required
            class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
        >
            <option value="" disabled @selected(old('lead_master_employee_id', $o?->lead_master_employee_id) === null)>Выберите мастера</option>
            @foreach ($masters as $m)
                <option
                    value="{{ $m->id }}"
                    @selected((string) old('lead_master_employee_id', $o?->lead_master_employee_id) === (string) $m->id)
                >{{ $m->full_name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="svc_req_notes" class="block text-[10px] font-bold uppercase text-slate-500">Комментарий</label>
        <textarea
            id="svc_req_notes"
            name="notes"
            rows="2"
            class="mt-1 w-full resize-y rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm leading-snug text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
        >{{ old('notes', $o?->notes) }}</textarea>
    </div>

    {{-- Модальное окно: новый клиент --}}
    <div
        x-cloak
        x-show="clientModalOpen"
        class="fixed inset-0 z-[300] flex items-end justify-center bg-black/40 p-3 sm:items-center"
        @keydown.escape.window="clientModalOpen = false"
    >
        <div
            class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl border border-slate-200 bg-white p-4 shadow-xl"
            @click.outside="clientModalOpen = false"
        >
            <h3 class="text-sm font-bold text-slate-900">Новый клиент</h3>
            <div class="mt-3 space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700">Правовая форма *</label>
                    <select
                        x-model="quickLegalForm"
                        class="mt-1 w-full rounded-lg border border-slate-200 px-2.5 py-2 text-sm text-slate-900"
                    >
                        <option value="individual">Физ. лицо</option>
                        <option value="ip">ИП</option>
                        <option value="osoo">ОсОО</option>
                        <option value="other">Прочее</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700">Наименование / ФИО *</label>
                    <input
                        type="text"
                        x-model="quickName"
                        class="mt-1 w-full rounded-lg border border-slate-200 px-2.5 py-2 text-sm text-slate-900"
                    />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700">Телефон *</label>
                    <input
                        type="tel"
                        x-model="quickPhone"
                        inputmode="tel"
                        class="mt-1 w-full rounded-lg border border-slate-200 px-2.5 py-2 text-sm text-slate-900"
                    />
                </div>
                <p x-show="quickError" class="text-sm text-red-600" x-text="quickError"></p>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                    @click="clientModalOpen = false"
                >
                    Отмена
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-500 disabled:opacity-50"
                    :disabled="quickSaving"
                    @click="quickSaveClient()"
                >
                    Сохранить
                </button>
            </div>
        </div>
    </div>

    {{-- Модальное окно: автомобиль --}}
    <div
        x-cloak
        x-show="vehicleModalOpen"
        class="fixed inset-0 z-[300] flex items-end justify-center bg-black/40 p-3 sm:items-center"
        @keydown.escape.window="vehicleModalOpen = false"
    >
        <div
            class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl border border-slate-200 bg-white p-4 shadow-xl"
            @click.outside="vehicleModalOpen = false"
        >
            <h3 class="text-sm font-bold text-slate-900">Автомобиль</h3>
            <p class="mt-1 text-xs text-slate-500">Марка, VIN и год сохраняются в карточке; пробег указывается в заявке отдельно.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700">Марка</label>
                    <input type="text" x-model="vBrand" class="mt-1 w-full rounded-lg border border-slate-200 px-2.5 py-2 text-sm" />
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700">VIN</label>
                    <input
                        type="text"
                        x-model="vVin"
                        class="mt-1 w-full rounded-lg border border-slate-200 px-2.5 py-2 font-mono text-sm"
                    />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700">Год выпуска</label>
                    <input
                        type="number"
                        x-model="vYear"
                        min="1950"
                        max="{{ (int) date('Y') + 1 }}"
                        class="mt-1 w-full rounded-lg border border-slate-200 px-2.5 py-2 text-sm"
                    />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700">Объём двигателя</label>
                    <input type="text" x-model="vEngine" class="mt-1 w-full rounded-lg border border-slate-200 px-2.5 py-2 text-sm" />
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700">Гос. номер</label>
                    <input type="text" x-model="vPlate" class="mt-1 w-full rounded-lg border border-slate-200 px-2.5 py-2 text-sm" />
                </div>
                <p x-show="vehicleError" class="sm:col-span-2 text-sm text-red-600" x-text="vehicleError"></p>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                    @click="vehicleModalOpen = false"
                >
                    Отмена
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-500 disabled:opacity-50"
                    :disabled="vehicleSaving"
                    @click="saveVehicle()"
                >
                    Сохранить
                </button>
            </div>
        </div>
    </div>

    {{-- Модальное окно: история визитов по автомобилю --}}
    <div
        x-cloak
        x-show="vehicleHistoryOpen"
        class="fixed inset-0 z-[300] flex items-end justify-center bg-black/40 p-3 sm:items-center"
        @keydown.escape.window="closeVehicleHistory()"
    >
        <div
            class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
            @click.outside="closeVehicleHistory()"
        >
            <div class="shrink-0 border-b border-slate-100 bg-slate-50/90 px-4 py-3">
                <h3 class="text-sm font-bold text-slate-900">История машины</h3>
                <p
                    class="mt-1 text-xs text-slate-600"
                    x-show="vehicleHistoryPayload && vehicleHistoryPayload.vehicle"
                    x-text="vehicleHistoryPayload && vehicleHistoryPayload.vehicle ? vehicleHistoryPayload.vehicle.label : ''"
                ></p>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3">
                <div x-show="vehicleHistoryLoading" class="py-8 text-center text-sm text-slate-500">Загрузка…</div>
                <p x-show="vehicleHistoryError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" x-text="vehicleHistoryError"></p>
                <template x-if="vehicleHistoryPayload && !vehicleHistoryLoading && !vehicleHistoryError">
                    <div class="space-y-4">
                        <template x-if="!vehicleHistoryPayload.visits || vehicleHistoryPayload.visits.length === 0">
                            <p class="text-sm text-slate-600">По этому автомобилю пока нет сохранённых заявок (или все отменены).</p>
                        </template>
                        <template x-for="visit in (vehicleHistoryPayload.visits || [])" :key="visit.service_order_id + '-' + visit.document_date">
                            <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                                <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-100 pb-2">
                                    <div class="text-sm font-semibold text-slate-900">
                                        <span x-text="formatVisitDate(visit.document_date)"></span>
                                        <span class="text-slate-400"> · </span>
                                        Пробег <span class="tabular-nums text-slate-800" x-text="mileageDisplay(visit.mileage_km)"></span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-[11px] font-medium text-slate-500">№ <span class="font-mono" x-text="visit.service_order_id"></span></span>
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700" x-text="visitStatusLabel(visit.status)"></span>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Услуги</p>
                                    <template x-if="visit.services && visit.services.length">
                                        <ul class="mt-1 space-y-1 text-sm text-slate-800">
                                            <template x-for="(svc, si) in visit.services" :key="si">
                                                <li class="flex gap-2">
                                                    <span class="min-w-0 flex-1" x-text="svc.name"></span>
                                                    <span class="shrink-0 tabular-nums text-slate-600" x-text="'× ' + svc.quantity"></span>
                                                </li>
                                            </template>
                                        </ul>
                                    </template>
                                    <template x-if="!visit.services || visit.services.length === 0">
                                        <p class="mt-1 text-xs text-slate-500">Услуги в строках не найдены (учитываются позиции с типом «услуга» или с исполнителем).</p>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
            <div class="shrink-0 border-t border-slate-100 bg-slate-50/90 px-4 py-3 text-right">
                <button
                    type="button"
                    class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                    @click="closeVehicleHistory()"
                >
                    Закрыть
                </button>
            </div>
        </div>
    </div>
</div>
