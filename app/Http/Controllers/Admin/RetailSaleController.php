<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RequiresOpenCashShift;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRetailSaleRequest;
use App\Http\Requests\UpdateRetailSaleRequest;
use App\Models\Good;
use App\Models\OrganizationBankAccount;
use App\Models\RetailSale;
use App\Models\RetailSaleLine;
use App\Models\Warehouse;
use App\Services\OpeningBalanceService;
use App\Support\InvoiceNakladnayaFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class RetailSaleController extends Controller
{
    use RequiresOpenCashShift;

    public function __construct(
        private readonly OpeningBalanceService $openingBalanceService
    ) {}

    public function index(): View
    {
        $branchId = (int) auth()->user()->branch_id;

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $selectedWarehouseId = (int) request()->integer('warehouse_id') ?: 0;
        $defaultWarehouseId = $warehouses->firstWhere('is_default')?->id ?? $warehouses->first()?->id;
        if ($selectedWarehouseId === 0 || ! $warehouses->contains('id', $selectedWarehouseId)) {
            $selectedWarehouseId = (int) ($defaultWarehouseId ?? 0);
        }

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

        return view('admin.retail-sales.pos', [
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'paymentAccountsPayload' => $paymentAccountsPayload,
            'defaultAccountId' => $defaultAccountId,
            'goodsSearchUrl' => route('admin.goods.search'),
            'defaultDocumentDate' => now()->toDateString(),
        ]);
    }

    public function history(Request $request): View
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

        $limit = (int) $request->integer('limit') ?: 100;
        if (! in_array($limit, [50, 100, 200, 500], true)) {
            $limit = 100;
        }

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $dateFromNorm = is_string($dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1 ? $dateFrom : null;
        $dateToNorm = is_string($dateTo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1 ? $dateTo : null;

        $sales = RetailSale::query()
            ->where('branch_id', $branchId)
            ->when($selectedWarehouseId > 0, fn ($q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->when($dateFromNorm !== null, fn ($q) => $q->whereDate('document_date', '>=', $dateFromNorm))
            ->when($dateToNorm !== null, fn ($q) => $q->whereDate('document_date', '<=', $dateToNorm))
            ->with(['warehouse', 'organizationBankAccount.organization', 'lines'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return view('admin.retail-sales.history', [
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'limit' => $limit,
            'dateFrom' => $dateFromNorm,
            'dateTo' => $dateToNorm,
            'sales' => $sales,
        ]);
    }

    public function edit(RetailSale $retailSale): View
    {
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

        $retailSale->load(['lines', 'warehouse']);

        $linesForJs = $this->retailCartLinesForJs($retailSale, $branchId, old('lines'));

        return view('admin.retail-sales.edit', [
            'retailSale' => $retailSale,
            'paymentAccountsPayload' => $paymentAccountsPayload,
            'goodsSearchUrl' => route('admin.goods.search'),
            'linesForJs' => $linesForJs,
        ]);
    }

    public function update(UpdateRetailSaleRequest $request, RetailSale $retailSale): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;
        $accountId = (int) $request->validated('organization_bank_account_id');
        $documentDate = (string) $request->validated('document_date');
        $lines = $request->input('lines', []);
        $warehouseId = (int) $retailSale->warehouse_id;

        try {
            DB::transaction(function () use ($retailSale, $branchId, $warehouseId, $accountId, $documentDate, $lines) {
                $sale = RetailSale::query()
                    ->whereKey($retailSale->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $oldLines = $sale->lines()->orderBy('id')->get();
                foreach ($oldLines as $oldLine) {
                    $this->openingBalanceService->reverseOutboundSaleLine(
                        $branchId,
                        $warehouseId,
                        (int) $oldLine->good_id,
                        $oldLine->quantity
                    );
                }

                RetailSaleLine::query()->where('retail_sale_id', $sale->id)->delete();

                $sale->update([
                    'organization_bank_account_id' => $accountId,
                    'document_date' => $documentDate,
                ]);

                $totalAmount = $this->appendLines($sale->fresh(), $branchId, $warehouseId, $lines);
                $sale->update(['total_amount' => $totalAmount]);
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.retail-sales.history', array_filter([
                'warehouse_id' => $warehouseId,
            ], static fn ($v) => $v !== null && $v > 0))
            ->with('status', 'Продажа обновлена, остатки и сумма пересчитаны.');
    }

    public function destroy(Request $request, RetailSale $retailSale): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;
        if ((int) $retailSale->branch_id !== $branchId) {
            abort(403);
        }

        $warehouseId = (int) $retailSale->warehouse_id;

        try {
            DB::transaction(function () use ($retailSale, $branchId) {
                $sale = RetailSale::query()
                    ->whereKey($retailSale->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $wId = (int) $sale->warehouse_id;

                $oldLines = $sale->lines()->orderBy('id')->get();
                foreach ($oldLines as $oldLine) {
                    $this->openingBalanceService->reverseOutboundSaleLine(
                        $branchId,
                        $wId,
                        (int) $oldLine->good_id,
                        $oldLine->quantity
                    );
                }

                RetailSaleLine::query()->where('retail_sale_id', $sale->id)->delete();
                $sale->delete();
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withErrors(['delete' => $e->getMessage()]);
        }

        $historyQuery = array_filter([
            'warehouse_id' => $request->integer('return_warehouse_id') ?: null,
            'limit' => $request->integer('return_limit') ?: null,
            'date_from' => $request->input('return_date_from'),
            'date_to' => $request->input('return_date_to'),
        ], static fn ($v) => $v !== null && $v !== '');

        return redirect()
            ->route('admin.retail-sales.history', $historyQuery)
            ->with('status', 'Продажа удалена, остатки восстановлены.');
    }

    public function store(StoreRetailSaleRequest $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;
        $warehouseId = (int) $request->validated('warehouse_id');
        $accountId = (int) $request->validated('organization_bank_account_id');
        $documentDate = (string) $request->validated('document_date');
        $lines = $request->input('lines', []);

        try {
            $totalAmount = '0';
            DB::transaction(function () use ($branchId, $warehouseId, $accountId, $documentDate, $lines, &$totalAmount) {
                $sale = RetailSale::query()->create([
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'organization_bank_account_id' => $accountId,
                    'document_date' => $documentDate,
                    'user_id' => auth()->id(),
                    'total_amount' => '0.00',
                ]);

                $totalAmount = $this->appendLines($sale, $branchId, $warehouseId, $lines);
                $sale->update(['total_amount' => $totalAmount]);
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.retail-sales.index', ['warehouse_id' => $warehouseId])
            ->with('status', 'Продажа оформлена на сумму '.InvoiceNakladnayaFormatter::formatMoney((float) $totalAmount).' сом.');
    }

    /**
     * @param  list<mixed>|null  $oldLines
     * @return list<array{good_id: int, article_code: string, name: string, quantity: string, unit_price: string, stock_quantity: null, is_service: bool}>
     */
    private function retailCartLinesForJs(RetailSale $retailSale, int $branchId, ?array $oldLines): array
    {
        if (is_array($oldLines) && $oldLines !== []) {
            $out = [];
            foreach ($oldLines as $line) {
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
                $out[] = [
                    'good_id' => (int) $good->id,
                    'article_code' => $code,
                    'name' => (string) $good->name,
                    'quantity' => (string) ($line['quantity'] ?? '1'),
                    'unit_price' => isset($line['unit_price']) && (string) $line['unit_price'] !== ''
                        ? (string) $line['unit_price']
                        : '',
                    'stock_quantity' => null,
                    'is_service' => (bool) $good->is_service,
                ];
            }
            if ($out !== []) {
                return $out;
            }
        }

        $retailSale->loadMissing('lines');
        $goodIds = $retailSale->lines->pluck('good_id')->unique()->filter()->values();
        $serviceFlags = $goodIds->isEmpty()
            ? collect()
            : Good::query()
                ->where('branch_id', $branchId)
                ->whereIn('id', $goodIds)
                ->pluck('is_service', 'id');

        return $retailSale->lines->map(fn ($l) => [
            'good_id' => (int) $l->good_id,
            'article_code' => (string) $l->article_code,
            'name' => (string) $l->name,
            'quantity' => (string) $l->quantity,
            'unit_price' => $l->unit_price !== null ? (string) $l->unit_price : '',
            'stock_quantity' => null,
            'is_service' => (bool) ($serviceFlags[(int) $l->good_id] ?? false),
        ])->values()->all();
    }

    /**
     * @return string Total as decimal string
     */
    private function appendLines(RetailSale $sale, int $branchId, int $warehouseId, array $lines): string
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
}
