<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLegalEntitySaleRequest;
use App\Models\Good;
use App\Models\LegalEntitySale;
use App\Models\LegalEntitySaleLine;
use App\Models\Organization;
use App\Models\ServiceOrder;
use App\Models\Warehouse;
use App\Services\OpeningBalanceService;
use App\Support\InvoiceNakladnayaFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class LegalEntitySaleController extends Controller
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

        $filterGoodId = (int) request()->integer('good_id');
        $filterGood = null;
        if ($filterGoodId > 0) {
            $filterGood = Good::query()
                ->where('branch_id', $branchId)
                ->whereKey($filterGoodId)
                ->first(['id', 'article_code', 'name']);
            if ($filterGood === null) {
                $filterGoodId = 0;
            }
        }

        $recentSales = LegalEntitySale::query()
            ->where('branch_id', $branchId)
            ->when($selectedWarehouseId > 0, fn ($q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->when($filterGoodId > 0, fn ($q) => $q->whereHas('lines', fn ($lq) => $lq->where('good_id', $filterGoodId)))
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

        $filterGoodSummary = $filterGood instanceof Good
            ? trim(
                (($c = trim((string) ($filterGood->article_code ?? ''))) !== '' ? $c.' · ' : '')
                .trim((string) ($filterGood->name ?? ''))
            )
            : '';

        return view('admin.legal-entity-sales.index', [
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'recentSales' => $recentSales,
            'organizations' => $organizations,
            'defaultPrintOrganizationId' => $organizations->first()?->id,
            'filterGoodId' => $filterGoodId,
            'filterGoodSummary' => $filterGoodSummary,
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

        return view('admin.legal-entity-sales.create', [
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'linesForForm' => $linesForForm,
            'defaultDocumentDate' => now()->toDateString(),
        ]);
    }

    public function store(StoreLegalEntitySaleRequest $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $warehouseId = (int) $request->validated('warehouse_id');
        $buyerName = trim((string) $request->validated('buyer_name', ''));
        $buyerPin = preg_replace('/\D+/', '', (string) $request->validated('buyer_pin', ''));
        $counterpartyId = $request->validated('counterparty_id');
        $counterpartyId = $counterpartyId !== null && $counterpartyId !== '' ? (int) $counterpartyId : null;
        $documentDate = (string) $request->validated('document_date');
        $comment = trim((string) $request->validated('comment', ''));
        $comment = $comment !== '' ? $comment : null;
        $issueEsf = $request->boolean('issue_esf');
        $lines = $request->input('lines', []);

        try {
            DB::transaction(function () use ($branchId, $warehouseId, $buyerName, $buyerPin, $counterpartyId, $documentDate, $comment, $issueEsf, $lines) {
                $sale = LegalEntitySale::query()->create([
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'buyer_name' => $buyerName,
                    'buyer_pin' => $buyerPin,
                    'counterparty_id' => $counterpartyId,
                    'document_date' => $documentDate,
                    'comment' => $comment,
                ]);

                $this->appendLines($sale, $branchId, $warehouseId, $lines);

                if ($issueEsf) {
                    $sale->fresh()->load('lines.good')->esfApplyQueueFromFormCheckbox();
                }
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.legal-entity-sales.index', ['warehouse_id' => $warehouseId])
            ->with('status', 'Реализация проведена, остатки уменьшены.');
    }

    public function edit(LegalEntitySale $legalEntitySale): View
    {
        $branchId = (int) auth()->user()->branch_id;

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $legalEntitySale->load(['lines.good', 'warehouse']);

        $selectedWarehouseId = (int) $legalEntitySale->warehouse_id;

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
            $linesForForm = $legalEntitySale->lines->map(function ($line) {
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

        return view('admin.legal-entity-sales.edit', [
            'legalEntitySale' => $legalEntitySale,
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'linesForForm' => $linesForForm,
            'organizations' => $organizations,
            'defaultPrintOrganizationId' => $organizations->first()?->id,
        ]);
    }

    public function print(Request $request, LegalEntitySale $legalEntitySale): View
    {
        $orgId = $this->parseOrganizationQueryId($request);

        return view('admin.legal-entity-sales.nakladnaya', array_merge(
            $this->nakladnayaViewData($legalEntitySale, $orgId),
            ['forPdf' => false]
        ));
    }

    /**
     * Печать в формате заказ-наряда (как у заявок на услуги): материалы и работы отдельно, заголовок «Заказ-наряд №…».
     */
    public function printWorkOrder(Request $request, LegalEntitySale $legalEntitySale): View
    {
        $branchId = (int) auth()->user()->branch_id;
        $orgId = $this->parseOrganizationQueryId($request);

        $legalEntitySale->load(['lines.good', 'warehouse', 'branch', 'counterparty']);

        $printOrganizations = Organization::query()
            ->where('branch_id', $branchId)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $organization = null;
        if ($orgId !== null) {
            $organization = $printOrganizations->firstWhere('id', $orgId);
        }
        if ($organization === null) {
            $organization = $printOrganizations->first();
        }

        $serviceOrder = ServiceOrder::query()
            ->where('branch_id', $branchId)
            ->where('legal_entity_sale_id', $legalEntitySale->id)
            ->with(['counterparty', 'customerVehicle', 'leadMasterEmployee'])
            ->first();

        $materialRows = [];
        $serviceRows = [];
        $totalMaterials = '0.00';
        $totalServices = '0.00';

        foreach ($legalEntitySale->lines as $line) {
            $sum = $this->legalEntitySaleLineSum($line);
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
        $docDate = $legalEntitySale->document_date ?? $legalEntitySale->created_at ?? now();
        $workOrderNumber = $serviceOrder !== null
            ? (int) $serviceOrder->id
            : (int) $legalEntitySale->id;

        return view('admin.legal-entity-sales.work-order-print', [
            'legalEntitySale' => $legalEntitySale,
            'serviceOrder' => $serviceOrder,
            'organization' => $organization,
            'branch' => $legalEntitySale->branch,
            'printOrganizations' => $printOrganizations,
            'documentTitle' => InvoiceNakladnayaFormatter::serviceWorkOrderTitle($docDate, $workOrderNumber),
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

    public function pdf(Request $request, LegalEntitySale $legalEntitySale)
    {
        $orgId = $this->parseOrganizationQueryId($request);
        $data = array_merge($this->nakladnayaViewData($legalEntitySale, $orgId), ['forPdf' => true]);
        $filename = 'realizaciya-'.$legalEntitySale->id.'-'.$legalEntitySale->document_date->format('Y-m-d').'.pdf';

        return Pdf::loadView('admin.legal-entity-sales.nakladnaya', $data)
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
    private function nakladnayaViewData(LegalEntitySale $legalEntitySale, ?int $organizationId = null): array
    {
        $legalEntitySale->load(['lines.good', 'warehouse', 'branch']);

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

        $lines = $legalEntitySale->lines;
        $totalSum = '0';
        foreach ($lines as $line) {
            if ($line->line_sum !== null) {
                $totalSum = bcadd($totalSum, (string) $line->line_sum, 2);
            }
        }

        return [
            'legalEntitySale' => $legalEntitySale,
            'branch' => $branch,
            'organization' => $organization,
            'printOrganizations' => $printOrganizations,
            'lines' => $lines,
            'totalSum' => $totalSum,
        ];
    }

    public function update(StoreLegalEntitySaleRequest $request, LegalEntitySale $legalEntitySale): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $warehouseId = (int) $request->validated('warehouse_id');
        $buyerName = trim((string) $request->validated('buyer_name', ''));
        $buyerPin = preg_replace('/\D+/', '', (string) $request->validated('buyer_pin', ''));
        $counterpartyId = $request->validated('counterparty_id');
        $counterpartyId = $counterpartyId !== null && $counterpartyId !== '' ? (int) $counterpartyId : null;
        $documentDate = (string) $request->validated('document_date');
        $comment = trim((string) $request->validated('comment', ''));
        $comment = $comment !== '' ? $comment : null;
        $lines = $request->input('lines', []);

        try {
            DB::transaction(function () use ($legalEntitySale, $branchId, $warehouseId, $buyerName, $buyerPin, $counterpartyId, $documentDate, $comment, $lines) {
                $sale = LegalEntitySale::query()
                    ->whereKey($legalEntitySale->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($warehouseId !== (int) $sale->warehouse_id) {
                    abort(403);
                }

                $wId = (int) $sale->warehouse_id;
                $keepEsfQueueGoods = (bool) $sale->esf_queue_goods;
                $keepEsfQueueServices = (bool) $sale->esf_queue_services;

                $oldLines = $sale->lines()->orderBy('id')->get();
                foreach ($oldLines as $oldLine) {
                    $this->openingBalanceService->reverseOutboundSaleLine(
                        $branchId,
                        $wId,
                        (int) $oldLine->good_id,
                        $oldLine->quantity
                    );
                }

                LegalEntitySaleLine::query()->where('legal_entity_sale_id', $sale->id)->delete();

                $sale->update([
                    'buyer_name' => $buyerName,
                    'buyer_pin' => $buyerPin,
                    'counterparty_id' => $counterpartyId,
                    'document_date' => $documentDate,
                    'comment' => $comment,
                    'esf_queue_goods' => $keepEsfQueueGoods,
                    'esf_queue_services' => $keepEsfQueueServices,
                    'esf_exchange_code' => null,
                ]);

                $this->appendLines($sale->fresh(), $branchId, $wId, $lines);
                $sale->fresh()->load('lines.good')->esfSyncQueueFlagsToDocumentLines()->save();
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.legal-entity-sales.index', ['warehouse_id' => $warehouseId])
            ->with('status', 'Документ обновлён, остатки пересчитаны.');
    }

    public function destroy(Request $request, LegalEntitySale $legalEntitySale): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        if ((int) $legalEntitySale->branch_id !== $branchId) {
            abort(403);
        }

        $warehouseId = (int) $legalEntitySale->warehouse_id;

        try {
            DB::transaction(function () use ($legalEntitySale, $branchId) {
                $sale = LegalEntitySale::query()
                    ->whereKey($legalEntitySale->id)
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

                LegalEntitySaleLine::query()->where('legal_entity_sale_id', $sale->id)->delete();
                $sale->delete();
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withErrors(['delete' => $e->getMessage()]);
        }

        if ($request->input('return_to') === 'trade-invoices') {
            $q = array_filter([
                'warehouse_id' => $request->filled('return_warehouse_id') ? (int) $request->input('return_warehouse_id') : null,
                'counterparty_id' => $request->filled('return_counterparty_id') ? (int) $request->input('return_counterparty_id') : null,
                'sent' => $request->input('return_sent') === '1' || $request->input('return_sent') === 1 ? '1' : null,
            ], static fn ($v) => $v !== null && $v !== 0 && $v !== '');

            return redirect()
                ->route('admin.trade-invoices.index', $q)
                ->with('status', 'Реализация удалена, остатки восстановлены.');
        }

        return redirect()
            ->route('admin.legal-entity-sales.index', ['warehouse_id' => $warehouseId])
            ->with('status', 'Реализация удалена, остатки восстановлены.');
    }

    /**
     * @param  list<mixed>  $lines
     */
    private function appendLines(LegalEntitySale $sale, int $branchId, int $warehouseId, array $lines): void
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

    private function legalEntitySaleLineSum(LegalEntitySaleLine $line): string
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
}
