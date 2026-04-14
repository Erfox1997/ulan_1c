<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseReturnRequest;
use App\Models\Good;
use App\Models\Organization;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnLine;
use App\Models\Warehouse;
use App\Services\OpeningBalanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class PurchaseReturnController extends Controller
{
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
        $defaultId = $warehouses->firstWhere('is_default')?->id ?? $warehouses->first()?->id;

        if ($selectedWarehouseId === 0 || ! $warehouses->contains('id', $selectedWarehouseId)) {
            $selectedWarehouseId = (int) ($defaultId ?? 0);
        }

        $recentReturns = PurchaseReturn::query()
            ->where('branch_id', $branchId)
            ->when($selectedWarehouseId > 0, fn ($q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->with(['warehouse', 'lines'])
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $organizations = Organization::query()
            ->where('branch_id', $branchId)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.purchase-returns.index', [
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'recentReturns' => $recentReturns,
            'organizations' => $organizations,
            'defaultPrintOrganizationId' => $organizations->first()?->id,
        ]);
    }

    public function create(): View
    {
        $branchId = (int) auth()->user()->branch_id;

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $selectedWarehouseId = (int) old('warehouse_id', request()->integer('warehouse_id') ?: 0);
        $defaultId = $warehouses->firstWhere('is_default')?->id ?? $warehouses->first()?->id;

        if ($selectedWarehouseId === 0 || ! $warehouses->contains('id', $selectedWarehouseId)) {
            $selectedWarehouseId = (int) ($defaultId ?? 0);
        }

        $linesForForm = [$this->emptyLine()];
        $oldLines = old('lines');
        if (is_array($oldLines) && $oldLines !== []) {
            $linesForForm = [];
            foreach ($oldLines as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $linesForForm[] = [
                    'article_code' => (string) ($line['article_code'] ?? ''),
                    'name' => (string) ($line['name'] ?? ''),
                    'barcode' => (string) ($line['barcode'] ?? ''),
                    'category' => (string) ($line['category'] ?? ''),
                    'unit' => trim((string) ($line['unit'] ?? '')) ?: 'шт.',
                    'quantity' => (string) ($line['quantity'] ?? ''),
                    'unit_price' => isset($line['unit_price']) ? (string) $line['unit_price'] : '',
                ];
            }
            if ($linesForForm === []) {
                $linesForForm = [$this->emptyLine()];
            }
        }

        return view('admin.purchase-returns.create', [
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'linesForForm' => $linesForForm,
            'defaultDocumentDate' => now()->toDateString(),
        ]);
    }

    public function store(StorePurchaseReturnRequest $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $warehouseId = (int) $request->validated('warehouse_id');
        $supplierName = trim((string) $request->validated('supplier_name', ''));
        $documentDate = (string) $request->validated('document_date');
        $lines = $request->input('lines', []);

        try {
            DB::transaction(function () use ($branchId, $warehouseId, $supplierName, $documentDate, $lines) {
                $ret = PurchaseReturn::query()->create([
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'supplier_name' => $supplierName,
                    'document_date' => $documentDate,
                ]);

                $this->appendLines($ret, $branchId, $warehouseId, $lines);
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.purchase-returns.index', ['warehouse_id' => $warehouseId])
            ->with('status', 'Возврат проведён, остатки на складе уменьшены.');
    }

    public function edit(PurchaseReturn $purchaseReturn): View
    {
        $branchId = (int) auth()->user()->branch_id;

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $purchaseReturn->load(['lines.good', 'warehouse']);

        $selectedWarehouseId = (int) $purchaseReturn->warehouse_id;

        $linesForForm = [$this->emptyLine()];
        $oldLines = old('lines');
        if (is_array($oldLines) && $oldLines !== []) {
            $linesForForm = [];
            foreach ($oldLines as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $linesForForm[] = [
                    'article_code' => (string) ($line['article_code'] ?? ''),
                    'name' => (string) ($line['name'] ?? ''),
                    'barcode' => (string) ($line['barcode'] ?? ''),
                    'category' => (string) ($line['category'] ?? ''),
                    'unit' => trim((string) ($line['unit'] ?? '')) ?: 'шт.',
                    'quantity' => (string) ($line['quantity'] ?? ''),
                    'unit_price' => isset($line['unit_price']) ? (string) $line['unit_price'] : '',
                ];
            }
            if ($linesForForm === []) {
                $linesForForm = [$this->emptyLine()];
            }
        } else {
            $linesForForm = $purchaseReturn->lines->map(function ($line) {
                $good = $line->good;

                return [
                    'article_code' => (string) $line->article_code,
                    'name' => (string) $line->name,
                    'barcode' => $good?->barcode ? (string) $good->barcode : '',
                    'category' => $good?->category ? (string) $good->category : '',
                    'unit' => trim((string) ($line->unit ?? '')) ?: 'шт.',
                    'quantity' => (string) $line->quantity,
                    'unit_price' => $line->unit_price !== null ? (string) $line->unit_price : '',
                ];
            })->values()->all();
            if ($linesForForm === []) {
                $linesForForm = [$this->emptyLine()];
            }
        }

        $organizations = Organization::query()
            ->where('branch_id', $branchId)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.purchase-returns.edit', [
            'purchaseReturn' => $purchaseReturn,
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'linesForForm' => $linesForForm,
            'organizations' => $organizations,
            'defaultPrintOrganizationId' => $organizations->first()?->id,
        ]);
    }

    public function print(Request $request, PurchaseReturn $purchaseReturn): View
    {
        $orgId = $this->parseOrganizationQueryId($request);

        return view('admin.purchase-returns.nakladnaya', array_merge(
            $this->nakladnayaViewData($purchaseReturn, $orgId),
            ['forPdf' => false]
        ));
    }

    public function pdf(Request $request, PurchaseReturn $purchaseReturn)
    {
        $orgId = $this->parseOrganizationQueryId($request);
        $data = array_merge($this->nakladnayaViewData($purchaseReturn, $orgId), ['forPdf' => true]);
        $filename = 'vozvrat-postavschiku-'.$purchaseReturn->id.'-'.$purchaseReturn->document_date->format('Y-m-d').'.pdf';

        return Pdf::loadView('admin.purchase-returns.nakladnaya', $data)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    private function parseOrganizationQueryId(Request $request): ?int
    {
        $raw = $request->query('organization_id');
        if ($raw === null || $raw === '') {
            return null;
        }
        $id = (int) $raw;

        return $id > 0 ? $id : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function nakladnayaViewData(PurchaseReturn $purchaseReturn, ?int $organizationId = null): array
    {
        $purchaseReturn->load(['lines.good', 'warehouse', 'branch']);

        $branch = auth()->user()->branch;
        if ($branch === null) {
            abort(403);
        }

        $printOrganizations = Organization::query()
            ->where('branch_id', $branch->id)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $organization = null;
        if ($organizationId !== null) {
            $organization = $printOrganizations->firstWhere('id', $organizationId);
        }
        if ($organization === null) {
            $organization = $printOrganizations->first();
        }

        $lines = $purchaseReturn->lines;
        $totalSum = '0';
        foreach ($lines as $line) {
            if ($line->line_sum !== null) {
                $totalSum = bcadd($totalSum, (string) $line->line_sum, 2);
            }
        }

        return [
            'purchaseReturn' => $purchaseReturn,
            'branch' => $branch,
            'organization' => $organization,
            'printOrganizations' => $printOrganizations,
            'lines' => $lines,
            'totalSum' => $totalSum,
        ];
    }

    public function update(StorePurchaseReturnRequest $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $warehouseId = (int) $request->validated('warehouse_id');
        $supplierName = trim((string) $request->validated('supplier_name', ''));
        $documentDate = (string) $request->validated('document_date');
        $lines = $request->input('lines', []);

        try {
            DB::transaction(function () use ($purchaseReturn, $branchId, $warehouseId, $supplierName, $documentDate, $lines) {
                $ret = PurchaseReturn::query()
                    ->whereKey($purchaseReturn->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($warehouseId !== (int) $ret->warehouse_id) {
                    abort(403);
                }

                $wId = (int) $ret->warehouse_id;

                $oldLines = $ret->lines()->orderBy('id')->get();
                foreach ($oldLines as $oldLine) {
                    $this->openingBalanceService->reverseOutboundSaleLine(
                        $branchId,
                        $wId,
                        (int) $oldLine->good_id,
                        $oldLine->quantity
                    );
                }

                PurchaseReturnLine::query()->where('purchase_return_id', $ret->id)->delete();

                $ret->update([
                    'supplier_name' => $supplierName,
                    'document_date' => $documentDate,
                ]);

                $this->appendLines($ret->fresh(), $branchId, $wId, $lines);
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.purchase-returns.index', ['warehouse_id' => $warehouseId])
            ->with('status', 'Документ обновлён, остатки пересчитаны.');
    }

    public function destroy(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        if ((int) $purchaseReturn->branch_id !== $branchId) {
            abort(403);
        }

        $warehouseId = (int) $purchaseReturn->warehouse_id;

        try {
            DB::transaction(function () use ($purchaseReturn, $branchId) {
                $ret = PurchaseReturn::query()
                    ->whereKey($purchaseReturn->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $wId = (int) $ret->warehouse_id;

                $oldLines = $ret->lines()->orderBy('id')->get();
                foreach ($oldLines as $oldLine) {
                    $this->openingBalanceService->reverseOutboundSaleLine(
                        $branchId,
                        $wId,
                        (int) $oldLine->good_id,
                        $oldLine->quantity
                    );
                }

                PurchaseReturnLine::query()->where('purchase_return_id', $ret->id)->delete();
                $ret->delete();
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withErrors(['delete' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.purchase-returns.index', ['warehouse_id' => $warehouseId])
            ->with('status', 'Возврат поставщику удалён, остатки пересчитаны.');
    }

    /**
     * @param  list<mixed>  $lines
     */
    private function appendLines(PurchaseReturn $ret, int $branchId, int $warehouseId, array $lines): void
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

            PurchaseReturnLine::query()->create([
                'purchase_return_id' => $ret->id,
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

    /**
     * @return array<string, string>
     */
    private function emptyLine(): array
    {
        return [
            'article_code' => '',
            'name' => '',
            'barcode' => '',
            'category' => '',
            'unit' => 'шт.',
            'quantity' => '',
            'unit_price' => '',
        ];
    }
}
