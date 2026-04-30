<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseReceiptRequest;
use App\Models\Organization;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptLine;
use App\Models\Warehouse;
use App\Services\ArticleSequenceService;
use App\Services\OpeningBalanceService;
use App\Support\PurchaseReceiptLineDraft;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class PurchaseReceiptController extends Controller
{
    public function __construct(
        private readonly OpeningBalanceService $openingBalanceService,
        private readonly ArticleSequenceService $articleSequence,
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

        $recentReceipts = PurchaseReceipt::query()
            ->where('branch_id', $branchId)
            ->when($selectedWarehouseId > 0, fn ($q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->with(['warehouse'])
            ->withCount('lines')
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

        return view('admin.purchase-receipts.index', [
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'recentReceipts' => $recentReceipts,
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
                    'good_id' => (string) ($line['good_id'] ?? ''),
                    'markup_percent' => (string) ($line['markup_percent'] ?? ''),
                    'unit' => trim((string) ($line['unit'] ?? '')) ?: 'шт.',
                    'quantity' => (string) ($line['quantity'] ?? ''),
                    'unit_price' => isset($line['unit_price']) ? (string) $line['unit_price'] : '',
                    'sale_price' => isset($line['sale_price']) ? (string) $line['sale_price'] : '',
                ];
            }
            if ($linesForForm === []) {
                $linesForForm = [$this->emptyLine()];
            }
        }

        return view('admin.purchase-receipts.create', [
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'linesForForm' => $linesForForm,
            'defaultDocumentDate' => now()->toDateString(),
        ]);
    }

    public function edit(PurchaseReceipt $purchaseReceipt): View
    {
        $branchId = (int) auth()->user()->branch_id;

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $purchaseReceipt->load(['lines.good', 'warehouse']);

        $selectedWarehouseId = (int) $purchaseReceipt->warehouse_id;

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
                    'good_id' => (string) ($line['good_id'] ?? ''),
                    'markup_percent' => (string) ($line['markup_percent'] ?? ''),
                    'unit' => trim((string) ($line['unit'] ?? '')) ?: 'шт.',
                    'quantity' => (string) ($line['quantity'] ?? ''),
                    'unit_price' => isset($line['unit_price']) ? (string) $line['unit_price'] : '',
                    'sale_price' => isset($line['sale_price']) ? (string) $line['sale_price'] : '',
                ];
            }
            if ($linesForForm === []) {
                $linesForForm = [$this->emptyLine()];
            }
        } else {
            $linesForForm = $purchaseReceipt->lines->map(function ($line) {
                $good = $line->good;

                return [
                    'article_code' => (string) $line->article_code,
                    'name' => (string) $line->name,
                    'barcode' => $good?->barcode ? (string) $good->barcode : '',
                    'good_id' => $line->good_id !== null ? (string) $line->good_id : '',
                    'markup_percent' => self::impliedMarkupPercentHint($line->unit_price, $line->sale_price),
                    'unit' => trim((string) ($line->unit ?? '')) ?: 'шт.',
                    'quantity' => (string) $line->quantity,
                    'unit_price' => $line->unit_price !== null ? (string) $line->unit_price : '',
                    'sale_price' => $line->sale_price !== null ? (string) $line->sale_price : '',
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

        return view('admin.purchase-receipts.edit', [
            'purchaseReceipt' => $purchaseReceipt,
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'linesForForm' => $linesForForm,
            'organizations' => $organizations,
            'defaultPrintOrganizationId' => $organizations->first()?->id,
        ]);
    }

    public function store(StorePurchaseReceiptRequest $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $warehouseId = (int) $request->validated('warehouse_id');
        $supplierName = trim((string) $request->validated('supplier_name', ''));
        $documentDate = (string) $request->validated('document_date');

        $linesInput = $request->input('lines', []);

        DB::transaction(function () use ($branchId, $warehouseId, $supplierName, $documentDate, $linesInput) {
            $lines = $this->assignMissingPurchaseLineArticleCodes($branchId, $linesInput);
            $receipt = PurchaseReceipt::query()->create([
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'supplier_name' => $supplierName,
                'document_date' => $documentDate,
            ]);

            $this->appendLinesToReceipt($receipt, $branchId, $warehouseId, $lines);
        });

        return redirect()
            ->route('admin.purchase-receipts.index', ['warehouse_id' => $warehouseId])
            ->with('status', 'Поступление проведено, остатки обновлены.');
    }

    public function print(Request $request, PurchaseReceipt $purchaseReceipt): View
    {
        $orgId = $this->parseOrganizationQueryId($request);

        return view('admin.purchase-receipts.nakladnaya', array_merge(
            $this->nakladnayaViewData($purchaseReceipt, $orgId),
            ['forPdf' => false]
        ));
    }

    public function pdf(Request $request, PurchaseReceipt $purchaseReceipt)
    {
        $orgId = $this->parseOrganizationQueryId($request);
        $data = array_merge($this->nakladnayaViewData($purchaseReceipt, $orgId), ['forPdf' => true]);
        $filename = 'nakladnaya-'.$purchaseReceipt->id.'-'.$purchaseReceipt->document_date->format('Y-m-d').'.pdf';

        return Pdf::loadView('admin.purchase-receipts.nakladnaya', $data)
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
    private function nakladnayaViewData(PurchaseReceipt $purchaseReceipt, ?int $organizationId = null): array
    {
        $purchaseReceipt->load(['lines.good', 'warehouse', 'branch']);

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

        $lines = $purchaseReceipt->lines;
        $totalSum = '0';
        foreach ($lines as $line) {
            if ($line->line_sum !== null) {
                $totalSum = bcadd($totalSum, (string) $line->line_sum, 2);
            }
        }

        return [
            'purchaseReceipt' => $purchaseReceipt,
            'branch' => $branch,
            'organization' => $organization,
            'printOrganizations' => $printOrganizations,
            'lines' => $lines,
            'totalSum' => $totalSum,
        ];
    }

    public function update(StorePurchaseReceiptRequest $request, PurchaseReceipt $purchaseReceipt): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $warehouseId = (int) $request->validated('warehouse_id');
        $supplierName = trim((string) $request->validated('supplier_name', ''));
        $documentDate = (string) $request->validated('document_date');
        $linesInput = $request->input('lines', []);

        try {
            DB::transaction(function () use ($purchaseReceipt, $branchId, $warehouseId, $supplierName, $documentDate, $linesInput) {
                $lines = $this->assignMissingPurchaseLineArticleCodes($branchId, $linesInput);
                $receipt = PurchaseReceipt::query()
                    ->whereKey($purchaseReceipt->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($warehouseId !== (int) $receipt->warehouse_id) {
                    abort(403);
                }

                $wId = (int) $receipt->warehouse_id;

                $oldLines = $receipt->lines()->orderBy('id')->get();
                foreach ($oldLines as $oldLine) {
                    $this->openingBalanceService->reverseIncomingLine(
                        $wId,
                        (int) $oldLine->good_id,
                        $oldLine->quantity,
                        $oldLine->unit_price
                    );
                }

                PurchaseReceiptLine::query()->where('purchase_receipt_id', $receipt->id)->delete();

                $receipt->update([
                    'supplier_name' => $supplierName,
                    'document_date' => $documentDate,
                ]);

                $this->appendLinesToReceipt($receipt->fresh(), $branchId, $wId, $lines);
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.purchase-receipts.index', ['warehouse_id' => $warehouseId])
            ->with('status', 'Документ обновлён, остатки пересчитаны.');
    }

    public function destroy(PurchaseReceipt $purchaseReceipt): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        if ((int) $purchaseReceipt->branch_id !== $branchId) {
            abort(403);
        }

        $warehouseId = (int) $purchaseReceipt->warehouse_id;

        try {
            DB::transaction(function () use ($purchaseReceipt) {
                $doc = PurchaseReceipt::query()
                    ->whereKey($purchaseReceipt->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $wId = (int) $doc->warehouse_id;

                $oldLines = $doc->lines()->orderBy('id')->get();
                foreach ($oldLines as $oldLine) {
                    $this->openingBalanceService->reverseIncomingLine(
                        $wId,
                        (int) $oldLine->good_id,
                        $oldLine->quantity,
                        $oldLine->unit_price
                    );
                }

                PurchaseReceiptLine::query()->where('purchase_receipt_id', $doc->id)->delete();
                $doc->delete();
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withErrors(['delete' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.purchase-receipts.index', ['warehouse_id' => $warehouseId])
            ->with('status', 'Поступление удалено, остатки пересчитаны.');
    }

    /**
     * Для сохранённых строк без артикула резервируются номера (как в UI «Артикулы»).
     *
     * @param  list<mixed>  $lines
     * @return list<mixed>
     */
    private function assignMissingPurchaseLineArticleCodes(int $branchId, array $lines): array
    {
        $need = 0;
        foreach ($lines as $line) {
            if (! is_array($line) || PurchaseReceiptLineDraft::isGhost($line)) {
                continue;
            }
            if (trim((string) ($line['article_code'] ?? '')) !== '') {
                continue;
            }
            $need++;
        }
        if ($need === 0) {
            return $lines;
        }

        $codes = $this->articleSequence->reserveNextArticleCodes($branchId, $need);
        $i = 0;
        foreach ($lines as $k => $line) {
            if (! is_array($line) || PurchaseReceiptLineDraft::isGhost($line)) {
                continue;
            }
            if (trim((string) ($line['article_code'] ?? '')) !== '') {
                continue;
            }
            $lines[$k]['article_code'] = $codes[$i++];
        }

        return $lines;
    }

    /**
     * @param  list<mixed>  $lines
     */
    private function appendLinesToReceipt(PurchaseReceipt $receipt, int $branchId, int $warehouseId, array $lines): void
    {
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $code = trim((string) ($line['article_code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $good = $this->openingBalanceService->addIncomingLine($branchId, $warehouseId, [
                'article_code' => $code,
                'name' => trim((string) ($line['name'] ?? '')),
                'barcode' => trim((string) ($line['barcode'] ?? '')),
                'category' => '',
                'quantity' => $line['quantity'] ?? '',
                'unit' => trim((string) ($line['unit'] ?? '')) ?: 'шт.',
                'unit_price' => $line['unit_price'] ?? null,
                'sale_price' => $line['sale_price'] ?? null,
            ]);

            if ($good === null) {
                continue;
            }

            $qty = $this->openingBalanceService->parseDecimal($line['quantity'] ?? 0);
            $price = $this->openingBalanceService->parseOptionalMoney($line['unit_price'] ?? null);
            $sale = $this->openingBalanceService->parseOptionalMoney($line['sale_price'] ?? null);
            $lineSum = null;
            if ($qty !== null && $price !== null) {
                $lineSum = bcmul((string) $qty, (string) $price, 2);
            }

            PurchaseReceiptLine::query()->create([
                'purchase_receipt_id' => $receipt->id,
                'good_id' => $good->id,
                'article_code' => $code,
                'name' => $good->name,
                'unit' => $good->unit ?? 'шт.',
                'quantity' => $qty ?? '0',
                'unit_price' => $price,
                'line_sum' => $lineSum,
                'sale_price' => $sale,
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
            'markup_percent' => '',
            'unit' => 'шт.',
            'quantity' => '',
            'unit_price' => '',
            'sale_price' => '',
        ];
    }

    /**
     * Подсказка «наценки» в форме редактирования: из сохранённых закупки и продажи.
     */
    private static function impliedMarkupPercentHint(mixed $unitPrice, mixed $salePrice): string
    {
        if ($unitPrice === null || $salePrice === null || $unitPrice === '' || $salePrice === '') {
            return '';
        }
        $u = (float) str_replace([' ', ','], ['', '.'], (string) $unitPrice);
        $s = (float) str_replace([' ', ','], ['', '.'], (string) $salePrice);
        if ($u <= 0.0 || ! is_finite($u) || ! is_finite($s)) {
            return '';
        }
        $m = ($s / $u - 1) * 100;
        if (! is_finite($m)) {
            return '';
        }

        return (string) round($m, 4);
    }
}
