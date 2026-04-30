<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RequiresOpenCashShift;
use App\Http\Controllers\Controller;
use App\Http\Requests\FulfillServiceOrderLegalRequest;
use App\Http\Requests\FulfillServiceOrderRetailRequest;
use App\Http\Requests\StoreServiceOrderHeaderRequest;
use App\Http\Requests\StoreServiceOrderLinesRequest;
use App\Models\Counterparty;
use App\Models\CustomerVehicle;
use App\Models\Employee;
use App\Models\Good;
use App\Models\LegalEntitySale;
use App\Models\LegalEntitySaleLine;
use App\Models\OpeningStockBalance;
use App\Models\Organization;
use App\Models\OrganizationBankAccount;
use App\Models\RetailSale;
use App\Models\RetailSaleLine;
use App\Models\RetailSalePayment;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderLine;
use App\Models\Warehouse;
use App\Services\BranchAccessService;
use App\Services\OpeningBalanceService;
use App\Support\InvoiceNakladnayaFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class ServiceOrderController extends Controller
{
    use RequiresOpenCashShift;

    public function __construct(
        private readonly OpeningBalanceService $openingBalanceService
    ) {}

    public function sell(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $selectedWarehouseId = (int) $request->integer('warehouse_id') ?: 0;
        $defaultWarehouseId = $warehouses->firstWhere('is_default')?->id ?? $warehouses->first()?->id;
        if ($selectedWarehouseId === 0 || ! $warehouses->contains('id', $selectedWarehouseId)) {
            $selectedWarehouseId = (int) ($defaultWarehouseId ?? 0);
        }

        $recentPending = ServiceOrder::query()
            ->where('branch_id', $branchId)
            ->awaitingFulfillmentQueue()
            ->with(['warehouse'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        $masters = Employee::query()
            ->where('branch_id', $branchId)
            ->masters()
            ->orderBy('full_name')
            ->get(['id', 'full_name']);

        return view('admin.service-sales.sell', [
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'counterpartySearchUrl' => route('admin.counterparties.search'),
            'counterpartyQuickUrl' => route('admin.counterparties.quick-store'),
            'customerVehiclesIndexUrl' => route('admin.customer-vehicles.index'),
            'customerVehiclesStoreUrl' => route('admin.customer-vehicles.store'),
            'vehicleHistoryUrlBase' => $this->sellVehicleHistoryUrlBase(),
            'masters' => $masters,
            'defaultDocumentDate' => now()->toDateString(),
            'recentPending' => $recentPending,
        ]);
    }

    /**
     * JSON: история заявок по автомобилю (пробег и услуги по визитам).
     */
    public function sellVehicleHistoryJson(CustomerVehicle $customerVehicle): JsonResponse
    {
        return response()->json($this->vehicleHistoryPayloadArray($customerVehicle));
    }

    /**
     * Страница поиска автомобилей по марке / госномеру / клиенту и просмотр истории обслуживания.
     */
    public function vehicleHistoryIndex(): View
    {
        $placeholderId = 887766554433;
        // Относительные URL — чтобы fetch всегда шёл на тот же origin, что открыта страница (избежать блокировки из‑за несовпадения localhost / 127.0.0.1 с APP_URL).
        $fullJsonUrl = route('admin.vehicle-history.json', ['customerVehicle' => $placeholderId], false);
        $vehicleHistoryJsonBase = Str::beforeLast($fullJsonUrl, '/'.$placeholderId);

        return view('admin.vehicle-history.index', [
            'vehicleHistoryJsonBase' => $vehicleHistoryJsonBase,
            'vehicleSearchUrl' => route('admin.vehicle-history.search', [], false),
        ]);
    }

    /**
     * Подсказки для строки поиска (не менее 2 символов).
     */
    public function vehicleHistorySearchJson(Request $request): JsonResponse
    {
        $qRaw = trim((string) $request->query('q', ''));
        if (Str::length($qRaw) < 2) {
            return response()->json(['vehicles' => []]);
        }

        $branchId = (int) auth()->user()->branch_id;
        $vehicles = $this->vehiclesMatchingHistorySearchQuery($qRaw, $branchId, 40);

        $out = $vehicles->map(function (CustomerVehicle $v) {
            $cp = $v->counterparty;
            $clientLabel = $cp ? trim((string) ($cp->full_name ?: $cp->name)) : '—';

            return [
                'id' => $v->id,
                'label' => $v->label(),
                'client_label' => $clientLabel,
            ];
        })->values()->all();

        return response()->json(['vehicles' => $out]);
    }

    /**
     * @return Collection<int, CustomerVehicle>
     */
    private function vehiclesMatchingHistorySearchQuery(string $qRaw, int $branchId, int $limit)
    {
        return CustomerVehicle::query()
            ->where('branch_id', $branchId)
            ->with(['counterparty:id,name,full_name'])
            ->where(function ($sub) use ($qRaw) {
                $pat = '%'.$qRaw.'%';
                $sub->where('vehicle_brand', 'like', $pat)
                    ->orWhere('plate_number', 'like', $pat)
                    ->orWhere('vin', 'like', $pat)
                    ->orWhereHas('counterparty', function ($cp) use ($pat) {
                        $cp->where('name', 'like', $pat)
                            ->orWhere('full_name', 'like', $pat);
                    });
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{vehicle: array<string, mixed>, visits: list<array<string, mixed>>}
     */
    private function vehicleHistoryPayloadArray(CustomerVehicle $customerVehicle): array
    {
        $branchId = (int) auth()->user()->branch_id;

        $orders = ServiceOrder::query()
            ->where('branch_id', $branchId)
            ->where('customer_vehicle_id', $customerVehicle->id)
            ->where('status', '!=', ServiceOrder::STATUS_CANCELLED)
            ->with([
                'lines' => fn ($q) => $q->orderBy('id')->with([
                    'good' => fn ($gq) => $gq->select('id', 'is_service'),
                ]),
            ])
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->limit(150)
            ->get();

        $visits = $orders->map(function (ServiceOrder $order) {
            $serviceLines = $order->lines->filter(function (ServiceOrderLine $line) {
                $g = $line->good;
                if ($g !== null && $g->is_service) {
                    return true;
                }
                if ($line->performer_employee_id !== null && (int) $line->performer_employee_id > 0) {
                    return true;
                }

                return false;
            });

            return [
                'service_order_id' => $order->id,
                'document_date' => $order->document_date?->format('Y-m-d'),
                'mileage_km' => $order->mileage_km !== null ? (string) $order->mileage_km : null,
                'status' => $order->status,
                'services' => $serviceLines->values()->map(fn (ServiceOrderLine $line) => [
                    'name' => (string) $line->name,
                    'quantity' => (string) $line->quantity,
                ])->all(),
            ];
        })->values()->all();

        return [
            'vehicle' => [
                'id' => $customerVehicle->id,
                'label' => $customerVehicle->label(),
                'vehicle_brand' => $customerVehicle->vehicle_brand,
                'vin' => $customerVehicle->vin,
                'vehicle_year' => $customerVehicle->vehicle_year,
                'plate_number' => $customerVehicle->plate_number,
            ],
            'visits' => $visits,
        ];
    }

    public function storeHeader(StoreServiceOrderHeaderRequest $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $warehouseId = (int) $request->validated('warehouse_id');
        $notes = $request->validated('notes');
        $notes = $notes !== null && trim((string) $notes) !== '' ? trim((string) $notes) : null;
        $documentDate = (string) $request->validated('document_date');
        $counterpartyId = (int) $request->validated('counterparty_id');
        $customerVehicleId = (int) $request->validated('customer_vehicle_id');
        $mileageKm = $this->openingBalanceService->parseDecimal($request->validated('mileage_km'));
        $leadMasterId = (int) $request->validated('lead_master_employee_id');
        $deadlineDate = (string) $request->validated('deadline_date');
        $contactName = trim((string) $request->validated('contact_name'));

        try {
            $order = DB::transaction(function () use (
                $branchId,
                $warehouseId,
                $documentDate,
                $notes,
                $counterpartyId,
                $contactName,
                $customerVehicleId,
                $mileageKm,
                $leadMasterId,
                $deadlineDate
            ) {
                return ServiceOrder::query()->create([
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'counterparty_id' => $counterpartyId,
                    'contact_name' => $contactName,
                    'customer_vehicle_id' => $customerVehicleId,
                    'mileage_km' => $mileageKm,
                    'lead_master_employee_id' => $leadMasterId,
                    'deadline_date' => $deadlineDate,
                    'user_id' => auth()->id(),
                    'status' => ServiceOrder::STATUS_AWAITING_FULFILLMENT,
                    'document_date' => $documentDate,
                    'total_amount' => '0.00',
                    'organization_bank_account_id' => null,
                    'notes' => $notes,
                ]);
            });
        } catch (\Throwable) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['header' => 'Не удалось создать заявку.']);
        }

        return redirect()
            ->route('admin.service-sales.sell.lines', $order)
            ->with('status', 'Укажите запчасти и услуги.');
    }

    public function sellLines(Request $request, ServiceOrder $serviceOrder): View|RedirectResponse
    {
        if (! $this->serviceOrderAllowsRequestEditing($serviceOrder)) {
            return redirect()
                ->route('admin.service-sales.requests')
                ->withErrors(['fulfill' => 'Заявка недоступна для редактирования позиций.']);
        }

        $branchId = (int) auth()->user()->branch_id;

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $warehouseId = (int) $serviceOrder->warehouse_id;
        $queryWarehouseId = (int) $request->integer('warehouse_id');
        if ($queryWarehouseId > 0 && $warehouses->contains('id', $queryWarehouseId)) {
            $warehouseId = $queryWarehouseId;
        } elseif ($warehouseId === 0 || ! $warehouses->contains('id', $warehouseId)) {
            $warehouseId = (int) ($warehouses->firstWhere('is_default')?->id ?? $warehouses->first()?->id ?? 0);
        }

        $serviceOrder->load(['lines.good']);
        $serviceOrder->loadMissing(['counterparty', 'customerVehicle']);

        $oldLines = $request->old('lines');
        $initialCart = is_array($oldLines) && $oldLines !== []
            ? $this->serviceOrderInitialCartFromOldInput($branchId, $warehouseId, $oldLines)
            : [];
        if ($initialCart === []) {
            foreach ($serviceOrder->lines as $line) {
                $good = $line->good ?? Good::query()->where('branch_id', $branchId)->find($line->good_id);
                $stockQty = null;
                if ($good && ! $good->is_service && $warehouseId > 0) {
                    $bal = OpeningStockBalance::query()
                        ->where('warehouse_id', $warehouseId)
                        ->where('good_id', $good->id)
                        ->first();
                    $stockQty = $bal !== null ? (float) $bal->quantity : 0.0;
                }
                $hasPerformer = $line->performer_employee_id !== null && (int) $line->performer_employee_id > 0;
                $initialCart[] = [
                    'line_id' => (int) $line->id,
                    'good_id' => (int) $line->good_id,
                    'article_code' => (string) $line->article_code,
                    'name' => (string) $line->name,
                    'quantity' => $this->formatDecimalStringForInput($line->quantity, 2),
                    'unit_price' => $this->formatDecimalStringForInput($line->unit_price, 2),
                    'is_service' => (bool) ($good?->is_service ?? false) || $hasPerformer,
                    'stock_quantity' => $stockQty,
                    'performer_employee_id' => $line->performer_employee_id !== null ? (string) $line->performer_employee_id : '',
                ];
            }
        }

        $linesFromRequests = $request->route()->named('admin.service-sales.requests.lines');

        return view('admin.service-sales.sell-lines', [
            'serviceOrder' => $serviceOrder,
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $warehouseId,
            'goodsSearchUrl' => route('admin.goods.search'),
            'masters' => Employee::query()
                ->where('branch_id', $branchId)
                ->masters()
                ->orderBy('full_name')
                ->get(['id', 'full_name']),
            'initialCart' => $initialCart,
            'linesIndexRoute' => $linesFromRequests ? 'admin.service-sales.requests.lines' : 'admin.service-sales.sell.lines',
            'linesStoreRoute' => $linesFromRequests ? 'admin.service-sales.requests.lines.store' : 'admin.service-sales.sell.lines.store',
            'linesFromRequests' => $linesFromRequests,
        ]);
    }

    public function storeLines(StoreServiceOrderLinesRequest $request, ServiceOrder $serviceOrder): RedirectResponse
    {
        if (! $this->serviceOrderAllowsRequestEditing($serviceOrder)) {
            return redirect()
                ->route('admin.service-sales.requests')
                ->withErrors(['fulfill' => 'Заявка недоступна.']);
        }

        $branchId = (int) auth()->user()->branch_id;
        $warehouseId = (int) $serviceOrder->warehouse_id;
        $lines = $request->input('lines', []);

        $cartLines = $this->hydrateLinesFromRetailInput($branchId, $lines);
        if ($cartLines === []) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['lines' => 'Не удалось разобрать позиции заявки.']);
        }

        $total = $this->cartTotal($cartLines);
        if (bccomp($total, '0', 2) !== 1) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['lines' => 'Укажите цены — сумма заявки должна быть больше нуля.']);
        }

        try {
            DB::transaction(function () use ($serviceOrder, $cartLines, $total, $branchId, $warehouseId) {
                /** @var ServiceOrder $locked */
                $locked = ServiceOrder::query()
                    ->whereKey($serviceOrder->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->status === ServiceOrder::STATUS_FULFILLED && $locked->retail_sale_id !== null) {
                    $retailSale = RetailSale::query()
                        ->whereKey($locked->retail_sale_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    foreach ($retailSale->lines()->orderBy('id')->get() as $oldLine) {
                        $this->openingBalanceService->reverseOutboundSaleLine(
                            $branchId,
                            $warehouseId,
                            (int) $oldLine->good_id,
                            $oldLine->quantity
                        );
                    }
                    RetailSaleLine::query()->where('retail_sale_id', $retailSale->id)->delete();
                }

                if ($locked->status === ServiceOrder::STATUS_FULFILLED && $locked->legal_entity_sale_id !== null) {
                    $legalSale = LegalEntitySale::query()
                        ->whereKey($locked->legal_entity_sale_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    foreach ($legalSale->lines()->orderBy('id')->get() as $oldLine) {
                        $this->openingBalanceService->reverseOutboundSaleLine(
                            $branchId,
                            $warehouseId,
                            (int) $oldLine->good_id,
                            $oldLine->quantity
                        );
                    }
                    LegalEntitySaleLine::query()->where('legal_entity_sale_id', $legalSale->id)->delete();
                }

                $locked->lines()->delete();
                foreach ($cartLines as $line) {
                    ServiceOrderLine::query()->create([
                        'service_order_id' => $locked->id,
                        'good_id' => $line['good_id'],
                        'performer_employee_id' => $line['performer_employee_id'],
                        'article_code' => $line['article_code'],
                        'name' => $line['name'],
                        'unit' => $line['unit'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'line_sum' => $line['line_sum'],
                    ]);
                }
                $locked->update(['total_amount' => $total]);

                $syncLines = [];
                foreach ($cartLines as $line) {
                    $syncLines[] = [
                        'article_code' => (string) $line['article_code'],
                        'quantity' => (string) $line['quantity'],
                        'unit_price' => $line['unit_price'] !== null && trim((string) $line['unit_price']) !== ''
                            ? (string) $line['unit_price']
                            : '',
                    ];
                }

                if ($locked->status === ServiceOrder::STATUS_FULFILLED && $locked->retail_sale_id !== null) {
                    $retailSale = RetailSale::query()
                        ->whereKey($locked->retail_sale_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $saleTotal = $this->appendRetailLines($retailSale, $branchId, $warehouseId, $syncLines);
                    RetailSalePayment::query()->where('retail_sale_id', $retailSale->id)->delete();
                    $accountId = (int) $retailSale->organization_bank_account_id;
                    RetailSalePayment::query()->create([
                        'retail_sale_id' => $retailSale->id,
                        'organization_bank_account_id' => $accountId,
                        'amount' => $saleTotal,
                        'recorded_by_user_id' => auth()->id(),
                    ]);
                    $retailSale->update([
                        'total_amount' => $saleTotal,
                        'debt_amount' => '0.00',
                        'debtor_name' => null,
                        'debtor_phone' => null,
                        'debtor_comment' => null,
                    ]);
                }

                if ($locked->status === ServiceOrder::STATUS_FULFILLED && $locked->legal_entity_sale_id !== null) {
                    $legalSale = LegalEntitySale::query()
                        ->whereKey($locked->legal_entity_sale_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $this->appendLegalLines($legalSale, $branchId, $warehouseId, $syncLines);
                    $legalSale->fresh()->load('lines.good')->esfSyncQueueFlagsToDocumentLines()->save();
                }
            });
        } catch (\Throwable) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['lines' => 'Не удалось сохранить позиции.']);
        }

        $fromRequests = $request->route()->named('admin.service-sales.requests.lines.store');
        if ($fromRequests) {
            $requestsTab = $serviceOrder->status === ServiceOrder::STATUS_FULFILLED ? 'fulfilled' : 'awaiting';

            return redirect()
                ->route('admin.service-sales.requests', ['status' => $requestsTab])
                ->with('status', 'Позиции заявки №'.$serviceOrder->id.' сохранены.');
        }

        return redirect()
            ->route('admin.service-sales.sell')
            ->with('status', 'Заявка №'.$serviceOrder->id.' сохранена.');
    }

    public function editRequest(Request $request, ServiceOrder $serviceOrder): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        if (! $this->serviceOrderAllowsRequestEditing($serviceOrder)) {
            return redirect()
                ->route('admin.service-sales.requests')
                ->withErrors(['fulfill' => 'Редактирование заявки недоступно.']);
        }

        $branchId = (int) auth()->user()->branch_id;

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $serviceOrder->loadMissing(['counterparty', 'customerVehicle']);

        $queryWarehouseId = (int) $request->integer('warehouse_id');
        $warehouseId = (int) $serviceOrder->warehouse_id;
        if ($queryWarehouseId > 0 && $warehouses->contains('id', $queryWarehouseId)) {
            $warehouseId = $queryWarehouseId;
        } elseif ($warehouseId === 0 || ! $warehouses->contains('id', $warehouseId)) {
            $warehouseId = (int) ($warehouses->firstWhere('is_default')?->id ?? $warehouses->first()?->id ?? 0);
        }

        $masters = Employee::query()
            ->where('branch_id', $branchId)
            ->masters()
            ->orderBy('full_name')
            ->get(['id', 'full_name']);

        $initialCounterparty = null;
        if ($serviceOrder->counterparty_id) {
            $cp = $serviceOrder->counterparty;
            $initialCounterparty = [
                'id' => (int) $serviceOrder->counterparty_id,
                'label' => $cp ? (string) ($cp->full_name ?: $cp->name) : '',
            ];
        }

        return view('admin.service-sales.edit-request', [
            'serviceOrder' => $serviceOrder,
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $warehouseId,
            'counterpartySearchUrl' => route('admin.counterparties.search'),
            'customerVehiclesIndexUrl' => route('admin.customer-vehicles.index'),
            'customerVehiclesStoreUrl' => route('admin.customer-vehicles.store'),
            'counterpartyQuickUrl' => route('admin.counterparties.quick-store'),
            'masters' => $masters,
            'initialCounterparty' => $initialCounterparty,
            'defaultDocumentDate' => $serviceOrder->document_date?->toDateString() ?? now()->toDateString(),
            'vehicleHistoryUrlBase' => $this->sellVehicleHistoryUrlBase(),
        ]);
    }

    public function updateHeader(StoreServiceOrderHeaderRequest $request, ServiceOrder $serviceOrder): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        if (! $this->serviceOrderAllowsRequestEditing($serviceOrder)) {
            return redirect()
                ->route('admin.service-sales.requests')
                ->withErrors(['fulfill' => 'Редактирование заявки недоступно.']);
        }

        $notes = $request->validated('notes');
        $notes = $notes !== null && trim((string) $notes) !== '' ? trim((string) $notes) : null;
        $documentDate = (string) $request->validated('document_date');
        $counterpartyId = (int) $request->validated('counterparty_id');
        $customerVehicleId = (int) $request->validated('customer_vehicle_id');
        $mileageKm = $this->openingBalanceService->parseDecimal($request->validated('mileage_km'));
        $leadMasterId = (int) $request->validated('lead_master_employee_id');
        $deadlineDate = (string) $request->validated('deadline_date');
        $warehouseId = (int) $request->validated('warehouse_id');
        $contactName = trim((string) $request->validated('contact_name'));

        if ($serviceOrder->status === ServiceOrder::STATUS_FULFILLED
            && (int) $serviceOrder->warehouse_id !== $warehouseId) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['header' => 'У оформленной заявки нельзя менять склад (продажа уже проведена с текущего склада).']);
        }

        try {
            $serviceOrder->update([
                'warehouse_id' => $warehouseId,
                'counterparty_id' => $counterpartyId,
                'contact_name' => $contactName,
                'customer_vehicle_id' => $customerVehicleId,
                'mileage_km' => $mileageKm,
                'lead_master_employee_id' => $leadMasterId,
                'deadline_date' => $deadlineDate,
                'document_date' => $documentDate,
                'notes' => $notes,
            ]);

            if ($serviceOrder->status === ServiceOrder::STATUS_FULFILLED && $serviceOrder->retail_sale_id !== null) {
                RetailSale::query()->whereKey($serviceOrder->retail_sale_id)->update(['document_date' => $documentDate]);
            }
            if ($serviceOrder->status === ServiceOrder::STATUS_FULFILLED && $serviceOrder->legal_entity_sale_id !== null) {
                LegalEntitySale::query()->whereKey($serviceOrder->legal_entity_sale_id)->update(['document_date' => $documentDate]);
            }
        } catch (\Throwable) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['header' => 'Не удалось сохранить изменения.']);
        }

        return redirect()
            ->route('admin.service-sales.sell.lines', $serviceOrder)
            ->with('status', 'Шапка заявки сохранена. Проверьте позиции.');
    }

    public function requestsIndex(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;

        $status = $request->query('status');
        $status = is_string($status) && $status !== '' ? $status : 'awaiting';

        $searchQuery = trim((string) $request->query('q', ''));

        $q = ServiceOrder::query()
            ->where('branch_id', $branchId)
            ->with(['warehouse', 'user', 'counterparty', 'customerVehicle']);

        if ($status === 'awaiting') {
            $q->awaitingFulfillmentQueue();
        } elseif ($status === 'fulfilled') {
            $q->where('status', ServiceOrder::STATUS_FULFILLED);
        } elseif ($status === 'all') {
            // без фильтра по статусу
        } else {
            $q->awaitingFulfillmentQueue();
        }

        if ($searchQuery !== '') {
            $this->applyRequestsIndexSearch($q, $searchQuery);
        }

        $orders = $q->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return view('admin.service-sales.requests', [
            'orders' => $orders,
            'statusFilter' => $status,
            'searchQuery' => $searchQuery,
        ]);
    }

    /**
     * Поиск по списку заявок: №, контакт, контрагент, марка/гос№/VIN авто.
     */
    private function applyRequestsIndexSearch(Builder $query, string $search): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $escaped = '%'.addcslashes($search, '%_\\').'%';
        $idCastSql = in_array(DB::connection()->getDriverName(), ['sqlite', 'pgsql'], true)
            ? 'CAST(service_orders.id AS TEXT)'
            : 'CAST(service_orders.id AS CHAR)';
        $digitsOnly = preg_replace('/\D/u', '', $search) ?? '';
        $idForLike = $digitsOnly !== '' ? '%'.addcslashes($digitsOnly, '%_\\').'%' : '%';
        $stripHash = ltrim($search, '#');

        $query->where(function (Builder $w) use ($escaped, $idCastSql, $idForLike, $stripHash, $digitsOnly) {
            if ($stripHash !== '' && ctype_digit($stripHash)) {
                $w->where(function (Builder $w2) use ($stripHash, $idCastSql, $idForLike, $digitsOnly) {
                    $w2->where('service_orders.id', (int) $stripHash);
                    if ($digitsOnly !== '') {
                        $w2->orWhereRaw("{$idCastSql} LIKE ?", [$idForLike]);
                    }
                });
            }
            $w->orWhere('service_orders.contact_name', 'like', $escaped);
            $w->orWhereHas('counterparty', function (Builder $cq) use ($escaped) {
                $cq->where('counterparties.name', 'like', $escaped)
                    ->orWhere('counterparties.full_name', 'like', $escaped);
            });
            $w->orWhereHas('customerVehicle', function (Builder $vq) use ($escaped) {
                $vq->where('customer_vehicles.vehicle_brand', 'like', $escaped)
                    ->orWhere('customer_vehicles.plate_number', 'like', $escaped)
                    ->orWhere('customer_vehicles.vin', 'like', $escaped);
            });
        });
    }

    public function printWorkOrder(ServiceOrder $serviceOrder): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;

        $serviceOrder->load([
            'lines.good',
            'counterparty',
            'customerVehicle',
            'leadMasterEmployee',
            'warehouse',
            'branch',
        ]);

        $organization = Organization::query()
            ->where('branch_id', $branchId)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        $materialRows = [];
        $serviceRows = [];
        $totalMaterials = '0.00';
        $totalServices = '0.00';

        foreach ($serviceOrder->lines as $line) {
            $sum = $this->serviceOrderLineSum($line);
            $isService = (bool) ($line->good?->is_service);
            $row = [
                'name' => (string) $line->name,
                'quantity' => $line->quantity,
                'unit' => (string) ($line->unit ?? 'шт.'),
                'unit_price' => $line->unit_price,
                'line_sum' => $sum,
            ];
            if ($isService) {
                $serviceRows[] = $row;
                $totalServices = bcadd($totalServices, $sum, 2);
            } else {
                $materialRows[] = $row;
                $totalMaterials = bcadd($totalMaterials, $sum, 2);
            }
        }

        $grandTotal = bcadd($totalMaterials, $totalServices, 2);

        $docDate = $serviceOrder->document_date ?? $serviceOrder->created_at ?? now();

        return view('admin.service-sales.work-order-print', [
            'serviceOrder' => $serviceOrder,
            'organization' => $organization,
            'branch' => $serviceOrder->branch,
            'documentTitle' => InvoiceNakladnayaFormatter::serviceWorkOrderTitle($docDate, (int) $serviceOrder->id),
            'materialRows' => $materialRows,
            'serviceRows' => $serviceRows,
            'totalMaterials' => $totalMaterials,
            'totalServices' => $totalServices,
            'grandTotal' => $grandTotal,
            'amountWordsMaterials' => InvoiceNakladnayaFormatter::amountInWordsKgs((float) $totalMaterials),
            'amountWordsServices' => InvoiceNakladnayaFormatter::amountInWordsKgs((float) $totalServices),
            'amountWordsGrand' => InvoiceNakladnayaFormatter::amountInWordsKgs((float) $grandTotal),
            'forPdf' => false,
        ]);
    }

    /**
     * Сумма строки заявки (с учётом line_sum в БД).
     */
    private function serviceOrderLineSum(ServiceOrderLine $line): string
    {
        if ($line->line_sum !== null && trim((string) $line->line_sum) !== '') {
            return number_format((float) $line->line_sum, 2, '.', '');
        }

        $q = (float) $line->quantity;
        $p = (float) $line->unit_price;
        if (! is_finite($q) || ! is_finite($p)) {
            return '0.00';
        }

        return number_format(round($q * $p, 2), 2, '.', '');
    }

    public function destroy(ServiceOrder $serviceOrder): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        if (! $serviceOrder->isAwaitingFulfillment()) {
            return redirect()
                ->route('admin.service-sales.requests')
                ->withErrors(['fulfill' => 'Удалить можно только заявку, которая ещё не оформлена.']);
        }

        try {
            DB::transaction(function () use ($serviceOrder): void {
                $serviceOrder->delete();
            });
        } catch (\Throwable) {
            return redirect()
                ->route('admin.service-sales.requests')
                ->withErrors(['fulfill' => 'Не удалось удалить заявку.']);
        }

        return redirect()
            ->route('admin.service-sales.requests')
            ->with('status', 'Заявка удалена.');
    }

    public function fulfillForm(ServiceOrder $serviceOrder): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        if (! $serviceOrder->isAwaitingFulfillment()) {
            return redirect()
                ->route('admin.service-sales.requests')
                ->withErrors(['fulfill' => 'Эта заявка уже оформлена или недоступна.']);
        }

        $branchId = (int) auth()->user()->branch_id;

        $paymentAccounts = OrganizationBankAccount::query()
            ->whereHas('organization', fn ($q) => $q->where('branch_id', $branchId))
            ->with('organization:id,name,sort_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->sortBy(function (OrganizationBankAccount $a) {
                $org = $a->organization;

                return [
                    $org?->sort_order ?? 0,
                    $org?->name ?? '',
                    $a->sort_order,
                    $a->id,
                ];
            })
            ->values();

        $paymentAccountsPayload = $paymentAccounts->map(fn (OrganizationBankAccount $a) => [
            'id' => $a->id,
            'label' => $a->labelWithoutAccountNumber(),
            'organization' => $a->organization?->name ?? '—',
            'type' => $a->account_type,
        ])->all();

        $defaultAccountId = $paymentAccounts->firstWhere('is_default', true)?->id
            ?? $paymentAccounts->firstWhere('account_type', OrganizationBankAccount::TYPE_CASH)?->id
            ?? $paymentAccounts->first()?->id;

        $serviceOrder->load(['lines', 'warehouse', 'counterparty']);

        $legalCounterpartyPrefill = null;
        $oldCid = request()->old('counterparty_id');
        if ($oldCid !== null && $oldCid !== '') {
            $cp = Counterparty::query()
                ->where('branch_id', $branchId)
                ->where('kind', Counterparty::KIND_BUYER)
                ->find((int) $oldCid);
            if ($cp !== null) {
                $legalCounterpartyPrefill = [
                    'id' => $cp->id,
                    'label' => (string) ($cp->full_name ?: $cp->name),
                ];
            }
        } elseif ($serviceOrder->counterparty_id) {
            $cp = $serviceOrder->counterparty;
            if ($cp !== null && in_array($cp->legal_form, [
                Counterparty::LEGAL_IP,
                Counterparty::LEGAL_OSOO,
                Counterparty::LEGAL_OTHER,
            ], true)) {
                $legalCounterpartyPrefill = [
                    'id' => (int) $cp->id,
                    'label' => (string) ($cp->full_name ?: $cp->name),
                ];
            }
        }

        return view('admin.service-sales.fulfill', [
            'serviceOrder' => $serviceOrder,
            'paymentAccountsPayload' => $paymentAccountsPayload,
            'defaultAccountId' => $defaultAccountId,
            'defaultDocumentDate' => now()->toDateString(),
            'legalCounterpartyPrefill' => $legalCounterpartyPrefill,
            'fulfillLinesPayload' => $this->fulfillLinesPayloadForView($serviceOrder),
        ]);
    }

    /**
     * Данные позиций для формы оформления (с учётом old() после ошибки валидации).
     *
     * @return list<array{line_id: int, name: string, unit: string, quantity: string, unit_price: string}>
     */
    private function fulfillLinesPayloadForView(ServiceOrder $serviceOrder): array
    {
        $linesById = $serviceOrder->lines->keyBy('id');

        $oldLines = request()->old('lines');
        if (is_array($oldLines) && $oldLines !== []) {
            $merged = [];
            foreach ($oldLines as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $lid = (int) ($row['line_id'] ?? 0);
                $line = $linesById->get($lid);
                if ($line === null) {
                    continue;
                }
                $merged[] = [
                    'line_id' => $line->id,
                    'name' => (string) $line->name,
                    'unit' => (string) ($line->unit ?? 'шт.'),
                    'quantity' => $this->formatDecimalStringForInput($row['quantity'] ?? $line->quantity, 2),
                    'unit_price' => $this->formatDecimalStringForInput($row['unit_price'] ?? $line->unit_price, 2),
                ];
            }
            if ($merged !== []) {
                return $merged;
            }
        }

        return $serviceOrder->lines->map(function (ServiceOrderLine $line) {
            return [
                'line_id' => $line->id,
                'name' => (string) $line->name,
                'unit' => (string) ($line->unit ?? 'шт.'),
                'quantity' => $this->formatDecimalStringForInput($line->quantity, 2),
                'unit_price' => $this->formatDecimalStringForInput($line->unit_price, 2),
            ];
        })->values()->all();
    }

    /**
     * Позиции заявки из session old() после ошибки валидации — сохраняет мастеров, кол-во и цены из отправленной формы.
     *
     * @param  list<mixed>  $oldLines
     * @return list<array{line_id: int|null, good_id: int, article_code: string, name: string, quantity: string, unit_price: string, is_service: bool, stock_quantity: float|null, performer_employee_id: string}>
     */
    private function serviceOrderInitialCartFromOldInput(int $branchId, int $warehouseId, array $oldLines): array
    {
        $out = [];
        foreach ($oldLines as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = trim((string) ($row['article_code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $good = Good::query()
                ->where('branch_id', $branchId)
                ->where('article_code', $code)
                ->first();
            if ($good === null) {
                continue;
            }
            $stockQty = null;
            if (! $good->is_service && $warehouseId > 0) {
                $bal = OpeningStockBalance::query()
                    ->where('warehouse_id', $warehouseId)
                    ->where('good_id', $good->id)
                    ->first();
                $stockQty = $bal !== null ? (float) $bal->quantity : 0.0;
            }
            $performerRaw = $row['performer_employee_id'] ?? null;
            $performer = $performerRaw !== null && $performerRaw !== '' ? (string) $performerRaw : '';

            $out[] = [
                'line_id' => null,
                'good_id' => (int) $good->id,
                'article_code' => $code,
                'name' => (string) $good->name,
                'quantity' => $this->formatDecimalStringForInput($row['quantity'] ?? null, 2),
                'unit_price' => $this->formatDecimalStringForInput($row['unit_price'] ?? null, 2),
                'is_service' => (bool) $good->is_service || $performer !== '',
                'stock_quantity' => $stockQty,
                'performer_employee_id' => $performer,
            ];
        }

        if ($out === []) {
            return [];
        }
        // Пустая строка количества — подставим 1, чтобы ячейка не оставалась пустой.
        foreach ($out as $i => $line) {
            if (trim($line['quantity'] ?? '') === '') {
                $out[$i]['quantity'] = '1';
            }
        }

        return $out;
    }

    /**
     * Отображение числа в полях оформления (2 знака после запятой).
     */
    private function formatDecimalStringForInput(mixed $value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $n = is_numeric($value) ? (float) $value : (float) str_replace(',', '.', preg_replace('/\s+/', '', (string) $value));
        if (! is_finite($n)) {
            return '';
        }

        return number_format($n, $decimals, '.', '');
    }

    public function fulfillRetail(FulfillServiceOrderRetailRequest $request, ServiceOrder $serviceOrder): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        if (! $serviceOrder->isAwaitingFulfillment()) {
            return redirect()
                ->route('admin.service-sales.requests')
                ->withErrors(['fulfill' => 'Эта заявка уже оформлена.']);
        }

        $serviceOrder->loadMissing('counterparty');
        $lf = $serviceOrder->counterparty?->legal_form;
        if ($lf !== null && $lf !== Counterparty::LEGAL_INDIVIDUAL) {
            return redirect()
                ->back()
                ->withErrors(['fulfill' => 'Для этого покупателя используйте оформление «реализация юрлицу».']);
        }

        $branchId = (int) auth()->user()->branch_id;
        $warehouseId = (int) $serviceOrder->warehouse_id;
        if ($warehouseId <= 0) {
            return redirect()
                ->back()
                ->withErrors(['fulfill' => 'У заявки не указан склад.']);
        }

        $accountId = (int) $request->validated('organization_bank_account_id');
        $documentDate = (string) $request->validated('document_date');

        $serviceOrder->load('lines');
        $lines = $this->validatedFulfillLinesInput($request, $serviceOrder);

        try {
            DB::transaction(function () use ($serviceOrder, $branchId, $warehouseId, $accountId, $documentDate, $lines) {
                $sale = RetailSale::query()->create([
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'organization_bank_account_id' => $accountId,
                    'document_date' => $documentDate,
                    'user_id' => auth()->id(),
                    'total_amount' => '0.00',
                    'debt_amount' => '0.00',
                ]);

                $total = $this->appendRetailLines($sale, $branchId, $warehouseId, $lines);
                $sale->update(['total_amount' => $total]);
                RetailSalePayment::query()->create([
                    'retail_sale_id' => $sale->id,
                    'organization_bank_account_id' => $accountId,
                    'amount' => $total,
                    'recorded_by_user_id' => auth()->id(),
                ]);

                $serviceOrder->update([
                    'retail_sale_id' => $sale->id,
                    'status' => ServiceOrder::STATUS_FULFILLED,
                    'organization_bank_account_id' => $accountId,
                    'document_date' => $documentDate,
                ]);
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['fulfill' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.service-sales.requests', ['status' => 'fulfilled'])
            ->with('status', 'Заявка оформлена как продажа физлицу.');
    }

    public function fulfillLegal(FulfillServiceOrderLegalRequest $request, ServiceOrder $serviceOrder): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        if (! $serviceOrder->isAwaitingFulfillment()) {
            return redirect()
                ->route('admin.service-sales.requests')
                ->withErrors(['fulfill' => 'Эта заявка уже оформлена.']);
        }

        $serviceOrder->loadMissing('counterparty');
        $lf = $serviceOrder->counterparty?->legal_form;
        if ($lf === Counterparty::LEGAL_INDIVIDUAL) {
            return redirect()
                ->back()
                ->withErrors(['fulfill' => 'Для физлица используйте розничную продажу.']);
        }

        $branchId = (int) auth()->user()->branch_id;
        $warehouseId = (int) $serviceOrder->warehouse_id;
        if ($warehouseId <= 0) {
            return redirect()
                ->back()
                ->withErrors(['fulfill' => 'У заявки не указан склад.']);
        }

        $counterpartyId = (int) $request->validated('counterparty_id');
        $counterparty = Counterparty::query()
            ->where('branch_id', $branchId)
            ->where('kind', Counterparty::KIND_BUYER)
            ->whereKey($counterpartyId)
            ->firstOrFail();

        $buyerName = trim((string) ($counterparty->full_name ?: $counterparty->name));
        $buyerPin = preg_replace('/\D+/', '', (string) ($counterparty->inn ?? ''));

        $documentDate = (string) $request->validated('document_date');

        $serviceOrder->load('lines');
        $lines = $this->validatedFulfillLinesInput($request, $serviceOrder);

        try {
            DB::transaction(function () use (
                $serviceOrder,
                $branchId,
                $warehouseId,
                $buyerName,
                $buyerPin,
                $counterpartyId,
                $documentDate,
                $lines
            ) {
                $sale = LegalEntitySale::query()->create([
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'buyer_name' => $buyerName,
                    'buyer_pin' => $buyerPin,
                    'counterparty_id' => $counterpartyId,
                    'document_date' => $documentDate,
                    'esf_queue_goods' => false,
                    'esf_queue_services' => false,
                ]);

                $this->appendLegalLines($sale, $branchId, $warehouseId, $lines);

                $serviceOrder->update([
                    'legal_entity_sale_id' => $sale->id,
                    'status' => ServiceOrder::STATUS_FULFILLED,
                    'document_date' => $documentDate,
                ]);
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['fulfill' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.service-sales.requests', ['status' => 'fulfilled'])
            ->with('status', 'Заявка оформлена как реализация юрлицу.');
    }

    /**
     * @param  list<mixed>  $lines
     * @return list<array{good_id: int, article_code: string, name: string, unit: string, quantity: string, unit_price: ?string, line_sum: ?string, performer_employee_id: ?int}>
     */
    private function hydrateLinesFromRetailInput(int $branchId, array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $code = trim((string) ($line['article_code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $good = Good::query()
                ->where('branch_id', $branchId)
                ->where('article_code', $code)
                ->first();

            if ($good === null) {
                continue;
            }

            $qty = $this->openingBalanceService->parseDecimal($line['quantity'] ?? 0);
            if ($qty === null || (float) $qty <= 0) {
                continue;
            }

            $price = $this->openingBalanceService->parseOptionalMoney($line['unit_price'] ?? null);
            if ($price === null && $good->sale_price !== null) {
                $price = (string) $good->sale_price;
            }
            $lineSum = null;
            if ($price !== null) {
                $lineSum = bcmul((string) $qty, (string) $price, 2);
            }

            $performerRaw = $line['performer_employee_id'] ?? null;
            $performerId = null;
            if ($good->is_service && $performerRaw !== null && $performerRaw !== '') {
                $performerId = (int) $performerRaw;
            }

            $out[] = [
                'good_id' => (int) $good->id,
                'article_code' => (string) $good->article_code,
                'name' => (string) $good->name,
                'unit' => (string) ($good->unit ?? 'шт.'),
                'quantity' => (string) $qty,
                'unit_price' => $price,
                'line_sum' => $lineSum,
                'performer_employee_id' => $performerId,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{line_sum: ?string}>  $lines
     */
    private function cartTotal(array $lines): string
    {
        $t = '0';
        foreach ($lines as $line) {
            if (! empty($line['line_sum'])) {
                $t = bcadd($t, (string) $line['line_sum'], 2);
            }
        }

        return $t;
    }

    /**
     * Позиции при оформлении: можно изменить количество и цену, исключить часть строк (не уйдёт в продажу).
     *
     * @return list<array{article_code: string, quantity: string, unit_price: string}>
     */
    private function validatedFulfillLinesInput(Request $request, ServiceOrder $serviceOrder): array
    {
        $linesInput = $request->input('lines');
        if (! is_array($linesInput) || $linesInput === []) {
            throw ValidationException::withMessages([
                'lines' => 'Добавьте хотя бы одну позицию.',
            ]);
        }

        $orderLinesById = $serviceOrder->lines->keyBy('id');
        $seenIds = [];
        $out = [];
        $total = '0';

        foreach ($linesInput as $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages([
                    'lines' => 'Некорректные данные по позициям.',
                ]);
            }
            $lid = (int) ($row['line_id'] ?? 0);
            if ($lid <= 0 || isset($seenIds[$lid])) {
                throw ValidationException::withMessages([
                    'lines' => 'Некорректные данные по позициям.',
                ]);
            }
            $line = $orderLinesById->get($lid);
            if ($line === null) {
                throw ValidationException::withMessages([
                    'lines' => 'Позиция не относится к этой заявке.',
                ]);
            }
            $seenIds[$lid] = true;

            $qty = $this->openingBalanceService->parseDecimal($row['quantity'] ?? 0);
            if ($qty === null || bccomp((string) $qty, '0', 4) !== 1) {
                throw ValidationException::withMessages([
                    'lines' => 'Укажите количество больше нуля по позиции «'.$line->name.'».',
                ]);
            }

            $price = $this->openingBalanceService->parseOptionalMoney($row['unit_price'] ?? null);
            if ($price === null) {
                throw ValidationException::withMessages([
                    'lines' => 'Укажите цену по позиции «'.$line->name.'».',
                ]);
            }

            $lineSum = bcmul((string) $qty, (string) $price, 2);
            $total = bcadd($total, $lineSum, 2);

            $out[] = [
                'article_code' => (string) $line->article_code,
                'quantity' => (string) $qty,
                'unit_price' => (string) $price,
            ];
        }

        if (bccomp($total, '0', 2) !== 1) {
            throw ValidationException::withMessages([
                'lines' => 'Итог по позициям должен быть больше нуля.',
            ]);
        }

        return $out;
    }

    /**
     * @param  list<array{article_code: string, quantity: string, unit_price: string}>  $lines
     */
    private function appendRetailLines(RetailSale $sale, int $branchId, int $warehouseId, array $lines): string
    {
        $total = '0';
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $code = trim((string) ($line['article_code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $good = Good::query()
                ->where('branch_id', $branchId)
                ->where('article_code', $code)
                ->first();

            if ($good === null) {
                continue;
            }

            $qty = $this->openingBalanceService->parseDecimal($line['quantity'] ?? 0);
            if ($qty === null) {
                continue;
            }

            $this->openingBalanceService->applyOutboundSaleLine($warehouseId, (int) $good->id, $qty);

            $price = $this->openingBalanceService->parseOptionalMoney($line['unit_price'] ?? null);
            $lineSum = null;
            if ($qty !== null && $price !== null) {
                $lineSum = bcmul((string) $qty, (string) $price, 2);
                $total = bcadd($total, $lineSum, 2);
            }

            RetailSaleLine::query()->create([
                'retail_sale_id' => $sale->id,
                'good_id' => $good->id,
                'article_code' => $code,
                'name' => $good->name,
                'unit' => $good->unit ?? 'шт.',
                'quantity' => $qty ?? '0',
                'unit_price' => $price,
                'line_sum' => $lineSum,
            ]);
        }

        return $total;
    }

    /**
     * @param  list<array{article_code: string, quantity: string, unit_price: string}>  $lines
     */
    private function appendLegalLines(LegalEntitySale $sale, int $branchId, int $warehouseId, array $lines): void
    {
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $code = trim((string) ($line['article_code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $good = Good::query()
                ->where('branch_id', $branchId)
                ->where('article_code', $code)
                ->first();

            if ($good === null) {
                continue;
            }

            $qty = $this->openingBalanceService->parseDecimal($line['quantity'] ?? 0);
            if ($qty === null) {
                continue;
            }

            $this->openingBalanceService->applyOutboundSaleLine($warehouseId, (int) $good->id, $qty);

            $price = $this->openingBalanceService->parseOptionalMoney($line['unit_price'] ?? null);
            $lineSum = null;
            if ($qty !== null && $price !== null) {
                $lineSum = bcmul((string) $qty, (string) $price, 2);
            }

            LegalEntitySaleLine::query()->create([
                'legal_entity_sale_id' => $sale->id,
                'good_id' => $good->id,
                'article_code' => $code,
                'name' => $good->name,
                'unit' => $good->unit ?? 'шт.',
                'quantity' => $qty ?? '0',
                'unit_price' => $price,
                'line_sum' => $lineSum,
            ]);
        }
    }

    /** Базовый URL без ID автомобиля для JSON истории визитов на странице заявки. */
    private function sellVehicleHistoryUrlBase(): string
    {
        $placeholderId = 887766554433;
        $full = route('admin.service-sales.sell.vehicle-history', ['customerVehicle' => $placeholderId]);

        return Str::beforeLast($full, '/'.$placeholderId);
    }

    /** Черновик заявки и оформленная заявка (без отменённых); оформленные правятся только при отдельном праве. */
    private function serviceOrderAllowsRequestEditing(ServiceOrder $serviceOrder): bool
    {
        if ($serviceOrder->status === ServiceOrder::STATUS_CANCELLED) {
            return false;
        }

        if ($serviceOrder->isAwaitingFulfillment()) {
            return true;
        }

        if ($serviceOrder->status !== ServiceOrder::STATUS_FULFILLED) {
            return false;
        }

        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        return app(BranchAccessService::class)->userMayAccessRoute(
            $user,
            'admin.service-sales.requests.edit-fulfilled',
            request()
        );
    }
}
