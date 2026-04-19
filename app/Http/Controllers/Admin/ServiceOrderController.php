<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RequiresOpenCashShift;
use App\Http\Controllers\Controller;
use App\Http\Requests\FulfillServiceOrderLegalRequest;
use App\Http\Requests\FulfillServiceOrderRetailRequest;
use App\Http\Requests\StoreServiceOrderHeaderRequest;
use App\Http\Requests\StoreServiceOrderLinesRequest;
use App\Models\Counterparty;
use App\Models\Employee;
use App\Models\Good;
use App\Models\LegalEntitySale;
use App\Models\OpeningStockBalance;
use App\Models\LegalEntitySaleLine;
use App\Models\Organization;
use App\Models\OrganizationBankAccount;
use App\Models\RetailSale;
use App\Models\RetailSaleLine;
use App\Models\RetailSalePayment;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderLine;
use App\Models\Warehouse;
use App\Services\OpeningBalanceService;
use App\Support\InvoiceNakladnayaFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ->where('status', ServiceOrder::STATUS_AWAITING_FULFILLMENT)
            ->whereNull('retail_sale_id')
            ->whereNull('legal_entity_sale_id')
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
            'masters' => $masters,
            'defaultDocumentDate' => now()->toDateString(),
            'recentPending' => $recentPending,
        ]);
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
        if (! $serviceOrder->isAwaitingFulfillment()) {
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

        $initialCart = [];
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
            $initialCart[] = [
                'good_id' => (int) $line->good_id,
                'article_code' => (string) $line->article_code,
                'name' => (string) $line->name,
                'quantity' => $this->formatDecimalStringForInput($line->quantity, 2),
                'unit_price' => $this->formatDecimalStringForInput($line->unit_price, 2),
                'is_service' => (bool) ($good?->is_service ?? false),
                'stock_quantity' => $stockQty,
                'performer_employee_id' => $line->performer_employee_id !== null ? (string) $line->performer_employee_id : '',
            ];
        }

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
        ]);
    }

    public function storeLines(StoreServiceOrderLinesRequest $request, ServiceOrder $serviceOrder): RedirectResponse
    {
        if (! $serviceOrder->isAwaitingFulfillment()) {
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
            DB::transaction(function () use ($serviceOrder, $cartLines, $total) {
                $serviceOrder->lines()->delete();
                foreach ($cartLines as $line) {
                    ServiceOrderLine::query()->create([
                        'service_order_id' => $serviceOrder->id,
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
                $serviceOrder->update(['total_amount' => $total]);
            });
        } catch (\Throwable) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['lines' => 'Не удалось сохранить позиции.']);
        }

        return redirect()
            ->route('admin.service-sales.sell')
            ->with('status', 'Заявка №'.$serviceOrder->id.' сохранена.');
    }

    public function editRequest(Request $request, ServiceOrder $serviceOrder): View|RedirectResponse
    {
        if (! $serviceOrder->isAwaitingFulfillment()) {
            return redirect()
                ->route('admin.service-sales.requests')
                ->withErrors(['fulfill' => 'Редактировать можно только заявку, которая ещё не оформлена.']);
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
        ]);
    }

    public function updateHeader(StoreServiceOrderHeaderRequest $request, ServiceOrder $serviceOrder): RedirectResponse
    {
        if (! $serviceOrder->isAwaitingFulfillment()) {
            return redirect()
                ->route('admin.service-sales.requests')
                ->withErrors(['fulfill' => 'Редактировать можно только заявку, которая ещё не оформлена.']);
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

    public function requestsIndex(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;

        $status = $request->query('status');
        $status = is_string($status) && $status !== '' ? $status : 'awaiting';

        $q = ServiceOrder::query()
            ->where('branch_id', $branchId)
            ->with(['lines', 'warehouse', 'user', 'retailSale', 'legalEntitySale']);

        if ($status === 'awaiting') {
            $q->where('status', ServiceOrder::STATUS_AWAITING_FULFILLMENT)
                ->whereNull('retail_sale_id')
                ->whereNull('legal_entity_sale_id');
        } elseif ($status === 'fulfilled') {
            $q->where('status', ServiceOrder::STATUS_FULFILLED);
        } elseif ($status === 'all') {
            // без фильтра по статусу
        } else {
            $q->where('status', ServiceOrder::STATUS_AWAITING_FULFILLMENT)
                ->whereNull('retail_sale_id')
                ->whereNull('legal_entity_sale_id');
        }

        $orders = $q->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return view('admin.service-sales.requests', [
            'orders' => $orders,
            'statusFilter' => $status,
        ]);
    }

    public function printWorkOrder(ServiceOrder $serviceOrder): View
    {
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
                    'issue_esf' => false,
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
}
