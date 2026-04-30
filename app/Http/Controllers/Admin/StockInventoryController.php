<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Good;
use App\Models\OpeningStockBalance;
use App\Models\StockAudit;
use App\Models\StockAuditLine;
use App\Models\StockSurplus;
use App\Models\StockSurplusLine;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\StockWriteoff;
use App\Models\StockWriteoffLine;
use App\Models\Warehouse;
use App\Services\OpeningBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockInventoryController extends Controller
{
    public function __construct(
        private readonly OpeningBalanceService $openingBalanceService
    ) {}

    public function moveIndex(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        $warehouses = $this->warehousesForBranch($branchId);
        $selectedWarehouseId = $this->resolveWarehouseFilter($request, $warehouses);

        $documents = StockTransfer::query()
            ->where('branch_id', $branchId)
            ->when($selectedWarehouseId > 0, function ($q) use ($selectedWarehouseId) {
                $q->where(function ($q2) use ($selectedWarehouseId) {
                    $q2->where('from_warehouse_id', $selectedWarehouseId)
                        ->orWhere('to_warehouse_id', $selectedWarehouseId);
                });
            })
            ->with(['fromWarehouse', 'toWarehouse', 'lines'])
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return view('admin.stock.move.index', [
            'pageTitle' => 'Товары: перемещение',
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'documents' => $documents,
        ]);
    }

    public function moveCreate(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        $warehouses = $this->warehousesForBranch($branchId);
        $fromId = (int) old('from_warehouse_id', $request->integer('from_warehouse_id'));
        $toId = (int) old('to_warehouse_id', $request->integer('to_warehouse_id'));
        $defaultFrom = $warehouses->first()?->id;
        if ($fromId === 0 || ! $warehouses->contains('id', $fromId)) {
            $fromId = (int) ($defaultFrom ?? 0);
        }
        if ($toId === 0 || ! $warehouses->contains('id', $toId)) {
            $toId = (int) ($warehouses->firstWhere('id', '!=', $fromId)?->id ?? 0);
        }

        return view('admin.stock.move.create', [
            'pageTitle' => 'Новое перемещение',
            'warehouses' => $warehouses,
            'fromWarehouseId' => $fromId,
            'toWarehouseId' => $toId,
            'defaultDocumentDate' => now()->toDateString(),
            'initialRows' => $this->moveInitialRowsForForm($request, $branchId),
        ]);
    }

    public function moveStore(Request $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $validated = $request->validate([
            'from_warehouse_id' => ['required', 'integer'],
            'to_warehouse_id' => ['required', 'integer', 'different:from_warehouse_id'],
            'document_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array'],
            'lines.*.good_id' => ['nullable'],
            'lines.*.quantity' => ['nullable'],
        ]);

        $fromId = (int) $validated['from_warehouse_id'];
        $toId = (int) $validated['to_warehouse_id'];
        $this->assertWarehouseInBranch($fromId, $branchId);
        $this->assertWarehouseInBranch($toId, $branchId);

        $lines = $this->filterGoodQuantityLines($request->input('lines', []));

        if ($lines === []) {
            return back()->withInput()->withErrors(['lines' => 'Добавьте хотя бы одну строку с товаром и количеством.']);
        }

        try {
            DB::transaction(function () use ($branchId, $fromId, $toId, $validated, $lines) {
                $doc = StockTransfer::query()->create([
                    'branch_id' => $branchId,
                    'from_warehouse_id' => $fromId,
                    'to_warehouse_id' => $toId,
                    'document_date' => $validated['document_date'],
                    'note' => isset($validated['note']) ? trim((string) $validated['note']) : null,
                ]);

                foreach ($lines as $line) {
                    $goodId = (int) $line['good_id'];
                    $qty = $line['quantity'];
                    $this->assertGoodInBranch($goodId, $branchId);
                    $this->openingBalanceService->transferBetweenWarehouses($branchId, $fromId, $toId, $goodId, $qty);
                    StockTransferLine::query()->create([
                        'stock_transfer_id' => $doc->id,
                        'good_id' => $goodId,
                        'quantity' => $qty,
                    ]);
                }
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.stock.move', ['warehouse_id' => $fromId])
            ->with('status', 'Перемещение проведено, остатки обновлены.');
    }

    public function incomingIndex(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        $warehouses = $this->warehousesForBranch($branchId);
        $selectedWarehouseId = $this->resolveWarehouseFilter($request, $warehouses);

        $documents = StockSurplus::query()
            ->where('branch_id', $branchId)
            ->when($selectedWarehouseId > 0, fn ($q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->with(['warehouse', 'lines'])
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return view('admin.stock.incoming.index', [
            'pageTitle' => 'Товары: оприходование',
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'documents' => $documents,
        ]);
    }

    public function incomingCreate(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        $warehouses = $this->warehousesForBranch($branchId);
        $selectedWarehouseId = (int) old('warehouse_id', $request->integer('warehouse_id') ?: 0);
        $defaultId = $warehouses->firstWhere('is_default')?->id ?? $warehouses->first()?->id;

        if ($selectedWarehouseId === 0 || ! $warehouses->contains('id', $selectedWarehouseId)) {
            $selectedWarehouseId = (int) ($defaultId ?? 0);
        }

        return view('admin.stock.incoming.create', [
            'pageTitle' => 'Оприходование излишков',
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'warehouseId' => $selectedWarehouseId,
            'defaultDocumentDate' => now()->toDateString(),
            'document' => null,
            'initialRows' => [],
        ]);
    }

    public function incomingEdit(Request $request, StockSurplus $stockSurplus): View
    {
        $branchId = (int) auth()->user()->branch_id;
        if ((int) $stockSurplus->branch_id !== $branchId) {
            abort(404);
        }

        $warehouses = $this->warehousesForBranch($branchId);
        $stockSurplus->load(['lines.good']);

        $warehouseId = (int) old('warehouse_id', $stockSurplus->warehouse_id);
        if ($warehouseId === 0 || ! $warehouses->contains('id', $warehouseId)) {
            $warehouseId = (int) $stockSurplus->warehouse_id;
        }

        return view('admin.stock.incoming.create', [
            'pageTitle' => 'Редактирование оприходования',
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $warehouseId,
            'warehouseId' => $warehouseId,
            'defaultDocumentDate' => old('document_date', $stockSurplus->document_date->format('Y-m-d')),
            'document' => $stockSurplus,
            'initialRows' => $this->incomingInitialRowsForForm($request, $stockSurplus),
        ]);
    }

    public function incomingStore(Request $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'document_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array'],
            'lines.*.good_id' => ['nullable'],
            'lines.*.quantity' => ['nullable'],
            'lines.*.unit_cost' => ['nullable'],
            'lines.*.article_code' => ['nullable', 'string', 'max:100'],
            'lines.*.manual_name' => ['nullable', 'string', 'max:500'],
            'lines.*.unit' => ['nullable', 'string', 'max:30'],
            'lines.*.sale_price' => ['nullable', 'string', 'max:50'],
        ]);

        $warehouseId = (int) $validated['warehouse_id'];
        $this->assertWarehouseInBranch($warehouseId, $branchId);

        $lines = $this->filterIncomingLines($request->input('lines', []));

        if ($lines === []) {
            return back()->withInput()->withErrors(['lines' => 'Добавьте хотя бы одну строку: выберите товар из списка или укажите артикул и название для новой позиции.']);
        }

        if ($ucErr = $this->incomingUnitCostValidationError($lines)) {
            return back()->withInput()->withErrors(['lines' => $ucErr]);
        }

        try {
            DB::transaction(function () use ($branchId, $warehouseId, $validated, $lines) {
                $doc = StockSurplus::query()->create([
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'document_date' => $validated['document_date'],
                    'note' => isset($validated['note']) ? trim((string) $validated['note']) : null,
                ]);

                foreach ($lines as $line) {
                    $qty = $line['quantity'];
                    $unitCost = $line['unit_cost'] ?? null;
                    $ucParsed = $unitCost !== null ? $this->openingBalanceService->parseOptionalMoney($unitCost) : null;

                    if (! empty($line['good_id'])) {
                        $goodId = (int) $line['good_id'];
                        $this->assertGoodInBranch($goodId, $branchId);
                        $good = Good::query()->findOrFail($goodId);

                        $this->openingBalanceService->addIncomingLine($branchId, $warehouseId, [
                            'article_code' => $good->article_code,
                            'name' => $good->name,
                            'quantity' => $qty,
                            'unit' => $good->unit,
                            'unit_price' => $unitCost,
                            'barcode' => $good->barcode,
                            'category' => $good->category,
                            'sale_price' => $line['sale_price'] ?? null,
                        ]);

                        StockSurplusLine::query()->create([
                            'stock_surplus_id' => $doc->id,
                            'good_id' => $goodId,
                            'quantity' => $qty,
                            'unit_cost' => $ucParsed,
                        ]);

                        continue;
                    }

                    $good = $this->openingBalanceService->addIncomingLine($branchId, $warehouseId, [
                        'article_code' => $line['article_code'],
                        'name' => $line['manual_name'],
                        'quantity' => $qty,
                        'unit' => $line['unit'],
                        'unit_price' => $unitCost,
                        'barcode' => null,
                        'category' => null,
                        'sale_price' => $line['sale_price'] ?? null,
                    ]);

                    if ($good === null) {
                        throw new RuntimeException('Не удалось создать позицию: проверьте артикул и название.');
                    }

                    StockSurplusLine::query()->create([
                        'stock_surplus_id' => $doc->id,
                        'good_id' => $good->id,
                        'quantity' => $qty,
                        'unit_cost' => $ucParsed,
                    ]);
                }
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.stock.incoming', ['warehouse_id' => $warehouseId])
            ->with('status', 'Оприходование проведено.');
    }

    public function incomingUpdate(Request $request, StockSurplus $stockSurplus): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        if ((int) $stockSurplus->branch_id !== $branchId) {
            abort(404);
        }

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'document_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array'],
            'lines.*.good_id' => ['nullable'],
            'lines.*.quantity' => ['nullable'],
            'lines.*.unit_cost' => ['nullable'],
            'lines.*.article_code' => ['nullable', 'string', 'max:100'],
            'lines.*.manual_name' => ['nullable', 'string', 'max:500'],
            'lines.*.unit' => ['nullable', 'string', 'max:30'],
            'lines.*.sale_price' => ['nullable', 'string', 'max:50'],
        ]);

        $warehouseId = (int) $validated['warehouse_id'];
        $this->assertWarehouseInBranch($warehouseId, $branchId);

        $lines = $this->filterIncomingLines($request->input('lines', []));

        if ($lines === []) {
            return redirect()
                ->route('admin.stock.incoming.edit', $stockSurplus)
                ->withInput()
                ->withErrors(['lines' => 'Добавьте хотя бы одну строку: выберите товар из списка или укажите артикул и название для новой позиции.']);
        }

        if ($ucErr = $this->incomingUnitCostValidationError($lines)) {
            return redirect()
                ->route('admin.stock.incoming.edit', $stockSurplus)
                ->withInput()
                ->withErrors(['lines' => $ucErr]);
        }

        try {
            DB::transaction(function () use ($branchId, $warehouseId, $validated, $lines, $stockSurplus) {
                $doc = StockSurplus::query()
                    ->where('branch_id', $branchId)
                    ->whereKey($stockSurplus->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $oldLines = StockSurplusLine::query()
                    ->where('stock_surplus_id', $doc->id)
                    ->get();

                $oldWarehouseId = (int) $doc->warehouse_id;

                foreach ($oldLines as $ol) {
                    $this->openingBalanceService->reverseIncomingLine(
                        $oldWarehouseId,
                        (int) $ol->good_id,
                        $ol->quantity,
                        $ol->unit_cost
                    );
                }

                StockSurplusLine::query()->where('stock_surplus_id', $doc->id)->delete();

                $doc->update([
                    'warehouse_id' => $warehouseId,
                    'document_date' => $validated['document_date'],
                    'note' => isset($validated['note']) ? trim((string) $validated['note']) : null,
                ]);

                foreach ($lines as $line) {
                    $qty = $line['quantity'];
                    $unitCost = $line['unit_cost'] ?? null;
                    $ucParsed = $unitCost !== null ? $this->openingBalanceService->parseOptionalMoney($unitCost) : null;

                    if (! empty($line['good_id'])) {
                        $goodId = (int) $line['good_id'];
                        $this->assertGoodInBranch($goodId, $branchId);
                        $good = Good::query()->findOrFail($goodId);

                        $this->openingBalanceService->addIncomingLine($branchId, $warehouseId, [
                            'article_code' => $good->article_code,
                            'name' => $good->name,
                            'quantity' => $qty,
                            'unit' => $good->unit,
                            'unit_price' => $unitCost,
                            'barcode' => $good->barcode,
                            'category' => $good->category,
                            'sale_price' => $line['sale_price'] ?? null,
                        ]);

                        StockSurplusLine::query()->create([
                            'stock_surplus_id' => $doc->id,
                            'good_id' => $goodId,
                            'quantity' => $qty,
                            'unit_cost' => $ucParsed,
                        ]);

                        continue;
                    }

                    $good = $this->openingBalanceService->addIncomingLine($branchId, $warehouseId, [
                        'article_code' => $line['article_code'],
                        'name' => $line['manual_name'],
                        'quantity' => $qty,
                        'unit' => $line['unit'],
                        'unit_price' => $unitCost,
                        'barcode' => null,
                        'category' => null,
                        'sale_price' => $line['sale_price'] ?? null,
                    ]);

                    if ($good === null) {
                        throw new RuntimeException('Не удалось создать позицию: проверьте артикул и название.');
                    }

                    StockSurplusLine::query()->create([
                        'stock_surplus_id' => $doc->id,
                        'good_id' => $good->id,
                        'quantity' => $qty,
                        'unit_cost' => $ucParsed,
                    ]);
                }
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.stock.incoming.edit', $stockSurplus)
                ->withInput()
                ->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.stock.incoming', ['warehouse_id' => $warehouseId])
            ->with('status', 'Оприходование обновлено.');
    }

    public function writeoffIndex(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        $warehouses = $this->warehousesForBranch($branchId);
        $selectedWarehouseId = $this->resolveWarehouseFilter($request, $warehouses);

        $documents = StockWriteoff::query()
            ->where('branch_id', $branchId)
            ->when($selectedWarehouseId > 0, fn ($q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->with(['warehouse', 'lines'])
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return view('admin.stock.writeoff.index', [
            'pageTitle' => 'Товары: списание',
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'documents' => $documents,
        ]);
    }

    public function writeoffCreate(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        $warehouses = $this->warehousesForBranch($branchId);
        $warehouseId = (int) old('warehouse_id', $request->integer('warehouse_id'));
        if ($warehouseId === 0 || ! $warehouses->contains('id', $warehouseId)) {
            $warehouseId = (int) ($warehouses->firstWhere('is_default')?->id ?? $warehouses->first()?->id ?? 0);
        }

        return view('admin.stock.writeoff.create', [
            'pageTitle' => 'Списание товаров',
            'warehouses' => $warehouses,
            'warehouseId' => $warehouseId,
            'defaultDocumentDate' => now()->toDateString(),
            'document' => null,
            'initialRows' => [],
        ]);
    }

    public function writeoffEdit(Request $request, StockWriteoff $stockWriteoff): View
    {
        $branchId = (int) auth()->user()->branch_id;
        if ((int) $stockWriteoff->branch_id !== $branchId) {
            abort(404);
        }

        $warehouses = $this->warehousesForBranch($branchId);
        $stockWriteoff->load(['lines.good']);

        $warehouseId = (int) old('warehouse_id', $stockWriteoff->warehouse_id);
        if ($warehouseId === 0 || ! $warehouses->contains('id', $warehouseId)) {
            $warehouseId = (int) $stockWriteoff->warehouse_id;
        }

        return view('admin.stock.writeoff.create', [
            'pageTitle' => 'Редактирование списания',
            'warehouses' => $warehouses,
            'warehouseId' => $warehouseId,
            'defaultDocumentDate' => old('document_date', $stockWriteoff->document_date->format('Y-m-d')),
            'document' => $stockWriteoff,
            'initialRows' => $this->writeoffInitialRowsForForm($request, $stockWriteoff),
        ]);
    }

    public function writeoffStore(Request $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'document_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array'],
            'lines.*.good_id' => ['nullable'],
            'lines.*.quantity' => ['nullable'],
        ]);

        $warehouseId = (int) $validated['warehouse_id'];
        $this->assertWarehouseInBranch($warehouseId, $branchId);

        $lines = $this->filterGoodQuantityLines($request->input('lines', []));

        if ($lines === []) {
            return back()->withInput()->withErrors(['lines' => 'Добавьте хотя бы одну строку с товаром и количеством.']);
        }

        try {
            DB::transaction(function () use ($branchId, $warehouseId, $validated, $lines) {
                $doc = StockWriteoff::query()->create([
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'document_date' => $validated['document_date'],
                    'note' => isset($validated['note']) ? trim((string) $validated['note']) : null,
                ]);

                foreach ($lines as $line) {
                    $goodId = (int) $line['good_id'];
                    $qty = $line['quantity'];
                    $this->assertGoodInBranch($goodId, $branchId);
                    $this->openingBalanceService->applyOutboundSaleLine($warehouseId, $goodId, $qty);
                    StockWriteoffLine::query()->create([
                        'stock_writeoff_id' => $doc->id,
                        'good_id' => $goodId,
                        'quantity' => $qty,
                    ]);
                }
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.stock.writeoff', ['warehouse_id' => $warehouseId])
            ->with('status', 'Списание проведено.');
    }

    public function writeoffUpdate(Request $request, StockWriteoff $stockWriteoff): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        if ((int) $stockWriteoff->branch_id !== $branchId) {
            abort(404);
        }

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'document_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array'],
            'lines.*.good_id' => ['nullable'],
            'lines.*.quantity' => ['nullable'],
        ]);

        $warehouseId = (int) $validated['warehouse_id'];
        $this->assertWarehouseInBranch($warehouseId, $branchId);

        $lines = $this->filterGoodQuantityLines($request->input('lines', []));

        if ($lines === []) {
            return redirect()
                ->route('admin.stock.writeoff.edit', $stockWriteoff)
                ->withInput()
                ->withErrors(['lines' => 'Добавьте хотя бы одну строку с товаром и количеством.']);
        }

        try {
            DB::transaction(function () use ($branchId, $warehouseId, $validated, $lines, $stockWriteoff) {
                $doc = StockWriteoff::query()
                    ->where('branch_id', $branchId)
                    ->whereKey($stockWriteoff->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $oldLines = StockWriteoffLine::query()
                    ->where('stock_writeoff_id', $doc->id)
                    ->get();

                $oldWarehouseId = (int) $doc->warehouse_id;

                foreach ($oldLines as $ol) {
                    $this->openingBalanceService->reverseOutboundSaleLine(
                        $branchId,
                        $oldWarehouseId,
                        (int) $ol->good_id,
                        $ol->quantity
                    );
                }

                StockWriteoffLine::query()->where('stock_writeoff_id', $doc->id)->delete();

                $doc->update([
                    'warehouse_id' => $warehouseId,
                    'document_date' => $validated['document_date'],
                    'note' => isset($validated['note']) ? trim((string) $validated['note']) : null,
                ]);

                foreach ($lines as $line) {
                    $goodId = (int) $line['good_id'];
                    $qty = $line['quantity'];
                    $this->assertGoodInBranch($goodId, $branchId);
                    $this->openingBalanceService->applyOutboundSaleLine($warehouseId, $goodId, $qty);
                    StockWriteoffLine::query()->create([
                        'stock_writeoff_id' => $doc->id,
                        'good_id' => $goodId,
                        'quantity' => $qty,
                    ]);
                }
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.stock.writeoff.edit', $stockWriteoff)
                ->withInput()
                ->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.stock.writeoff', ['warehouse_id' => $warehouseId])
            ->with('status', 'Списание обновлено.');
    }

    public function auditIndex(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        $warehouses = $this->warehousesForBranch($branchId);
        $selectedWarehouseId = $this->resolveWarehouseFilter($request, $warehouses);

        $documents = StockAudit::query()
            ->where('branch_id', $branchId)
            ->when($selectedWarehouseId > 0, fn ($q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->with(['warehouse', 'lines'])
            ->orderByDesc('is_draft')
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return view('admin.stock.audit.index', [
            'pageTitle' => 'Товары: ревизия',
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'documents' => $documents,
        ]);
    }

    public function auditCreate(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        $warehouses = $this->warehousesForBranch($branchId);
        $warehouseId = (int) old('warehouse_id', $request->integer('warehouse_id'));
        if ($warehouseId === 0 || ! $warehouses->contains('id', $warehouseId)) {
            $warehouseId = (int) ($warehouses->firstWhere('is_default')?->id ?? $warehouses->first()?->id ?? 0);
        }

        return view('admin.stock.audit.create', [
            'pageTitle' => 'Ревизия (инвентаризация)',
            'warehouses' => $warehouses,
            'warehouseId' => $warehouseId,
            'defaultDocumentDate' => now()->toDateString(),
            'document' => null,
            'initialRows' => $this->auditInitialRowsForForm($request, $branchId, null),
            'linesLoadUrl' => null,
        ]);
    }

    public function auditEdit(Request $request, StockAudit $stockAudit): View
    {
        $branchId = (int) auth()->user()->branch_id;
        if ((int) $stockAudit->branch_id !== $branchId) {
            abort(404);
        }
        if (! $stockAudit->is_draft) {
            abort(404);
        }

        $warehouses = $this->warehousesForBranch($branchId);
        $warehouseId = (int) old('warehouse_id', $stockAudit->warehouse_id);
        if ($warehouseId === 0 || ! $warehouses->contains('id', $warehouseId)) {
            $warehouseId = (int) $stockAudit->warehouse_id;
        }

        $hasOldLines = is_array($request->old('lines')) && count($request->old('lines')) > 0;
        $linesLoadUrl = null;
        if ($hasOldLines) {
            $initialRows = $this->auditInitialRowsForForm($request, $branchId, $stockAudit);
        } elseif ($stockAudit->lines()->count() > 400) {
            $initialRows = [];
            $linesLoadUrl = route('admin.stock.audit.lines', $stockAudit);
        } else {
            $initialRows = $this->auditInitialRowsForForm($request, $branchId, $stockAudit);
        }

        return view('admin.stock.audit.create', [
            'pageTitle' => 'Ревизия — черновик № '.$stockAudit->id,
            'warehouses' => $warehouses,
            'warehouseId' => $warehouseId,
            'defaultDocumentDate' => old('document_date', $stockAudit->document_date->format('Y-m-d')),
            'document' => $stockAudit,
            'initialRows' => $initialRows,
            'linesLoadUrl' => $linesLoadUrl,
        ]);
    }

    public function auditLinesJson(StockAudit $stockAudit): JsonResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        if ((int) $stockAudit->branch_id !== $branchId) {
            abort(404);
        }
        if (! $stockAudit->is_draft) {
            abort(404);
        }

        return response()->json([
            'lines' => $this->stockAuditDocumentRowsForClient($stockAudit),
        ]);
    }

    public function auditExport(StockAudit $stockAudit): StreamedResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        if ((int) $stockAudit->branch_id !== $branchId) {
            abort(404);
        }
        if ($stockAudit->is_draft) {
            abort(404);
        }

        $stockAudit->load(['lines.good', 'warehouse']);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ревизия');

        $headers = [
            '№',
            'Артикул',
            'Название',
            'Штрихкод',
            'Ед.',
            'Учёт до ревизии',
            'Факт (после ревизии)',
            'Разница (факт − учёт)',
            'Остаток после (факт)',
            'Закуп. цена (снимок)',
            'Сумма разницы',
        ];
        $col = 1;
        foreach ($headers as $h) {
            $sheet->setCellValue([$col, 1], $h);
            $col++;
        }

        $rowNum = 2;
        $totalSum = 0.0;
        foreach ($stockAudit->lines as $line) {
            $g = $line->good;
            $book = $line->quantity_book !== null ? (float) $line->quantity_book : null;
            $counted = (float) $line->quantity_counted;
            $diff = $book !== null ? round($counted - $book, 4) : null;
            $uc = $line->unit_cost_snapshot !== null ? (float) $line->unit_cost_snapshot : null;
            $sumLine = ($diff !== null && $uc !== null) ? round($diff * $uc, 2) : null;
            if ($sumLine !== null) {
                $totalSum += $sumLine;
            }

            $sheet->setCellValue([1, $rowNum], $rowNum - 1);
            $sheet->setCellValue([2, $rowNum], $g?->article_code ?? '');
            $sheet->setCellValue([3, $rowNum], $g?->name ?? '');
            $sheet->setCellValue([4, $rowNum], $g?->barcode ?? '');
            $sheet->setCellValue([5, $rowNum], $g?->unit ?? '');
            $sheet->setCellValue([6, $rowNum], $book !== null ? $book : '');
            $sheet->setCellValue([7, $rowNum], $counted);
            $sheet->setCellValue([8, $rowNum], $diff !== null ? $diff : '');
            $sheet->setCellValue([9, $rowNum], $counted);
            $sheet->setCellValue([10, $rowNum], $uc !== null ? $uc : '');
            $sheet->setCellValue([11, $rowNum], $sumLine !== null ? $sumLine : '');
            $rowNum++;
        }

        $sheet->setCellValue([1, $rowNum], 'Итого сумма разницы (× закуп. цена)');
        $sheet->mergeCells('A'.$rowNum.':J'.$rowNum);
        $sheet->setCellValue([11, $rowNum], round($totalSum, 2));

        $metaRow = $rowNum + 1;
        $sheet->setCellValue([1, $metaRow], 'Склад: '.($stockAudit->warehouse?->name ?? '—'));
        $sheet->setCellValue([1, $metaRow + 1], 'Дата документа: '.$stockAudit->document_date->format('d.m.Y'));
        $sheet->setCellValue([1, $metaRow + 2], '№ документа: '.$stockAudit->id);

        foreach (range('A', 'K') as $letter) {
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }

        $filename = 'reviziya-'.$stockAudit->id.'-'.$stockAudit->document_date->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function auditStore(Request $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'document_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array'],
            'lines.*.good_id' => ['nullable'],
            'lines.*.quantity_counted' => ['nullable'],
            'commit' => ['required', 'in:draft,post'],
        ]);

        $warehouseId = (int) $validated['warehouse_id'];
        $this->assertWarehouseInBranch($warehouseId, $branchId);

        $lines = $this->filterAuditLines($request->input('lines', []));

        if ($lines === []) {
            return back()->withInput()->withErrors(['lines' => 'Добавьте строки с фактическим количеством по товарам.']);
        }

        if ($validated['commit'] === 'draft') {
            try {
                $doc = DB::transaction(function () use ($branchId, $warehouseId, $validated, $lines) {
                    $d = StockAudit::query()->create([
                        'branch_id' => $branchId,
                        'warehouse_id' => $warehouseId,
                        'document_date' => $validated['document_date'],
                        'note' => isset($validated['note']) ? trim((string) $validated['note']) : null,
                        'is_draft' => true,
                    ]);

                    foreach ($lines as $line) {
                        $goodId = (int) $line['good_id'];
                        $counted = $line['quantity_counted'];
                        $this->assertGoodInBranch($goodId, $branchId);
                        StockAuditLine::query()->create([
                            'stock_audit_id' => $d->id,
                            'good_id' => $goodId,
                            'quantity_book' => null,
                            'unit_cost_snapshot' => null,
                            'quantity_counted' => $counted,
                        ]);
                    }

                    return $d;
                });
            } catch (RuntimeException $e) {
                return back()->withInput()->withErrors(['lines' => $e->getMessage()]);
            }

            return redirect()
                ->route('admin.stock.audit', ['warehouse_id' => $warehouseId])
                ->with(
                    'status',
                    'Черновик № '.$doc->id.' сохранён. Откройте его в журнале при необходимости или нажмите «Новая ревизия», чтобы начать следующий.'
                );
        }

        try {
            DB::transaction(function () use ($branchId, $warehouseId, $validated, $lines) {
                $doc = StockAudit::query()->create([
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'document_date' => $validated['document_date'],
                    'note' => isset($validated['note']) ? trim((string) $validated['note']) : null,
                    'is_draft' => false,
                ]);

                foreach ($lines as $line) {
                    $goodId = (int) $line['good_id'];
                    $counted = $line['quantity_counted'];
                    $this->assertGoodInBranch($goodId, $branchId);

                    $balance = OpeningStockBalance::query()
                        ->where('warehouse_id', $warehouseId)
                        ->where('good_id', $goodId)
                        ->first();

                    $bookQty = $balance === null ? '0' : (string) $balance->quantity;
                    $ucSnap = $balance?->unit_cost;

                    StockAuditLine::query()->create([
                        'stock_audit_id' => $doc->id,
                        'good_id' => $goodId,
                        'quantity_book' => $bookQty,
                        'unit_cost_snapshot' => $ucSnap,
                        'quantity_counted' => $counted,
                    ]);

                    $this->openingBalanceService->applyAuditAdjustment($branchId, $warehouseId, $goodId, $counted);
                }
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.stock.audit', ['warehouse_id' => $warehouseId])
            ->with('status', 'Ревизия проведена. Скачайте отчёт Excel в журнале.');
    }

    public function auditUpdate(Request $request, StockAudit $stockAudit): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        if ((int) $stockAudit->branch_id !== $branchId) {
            abort(404);
        }
        if (! $stockAudit->is_draft) {
            abort(404);
        }

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'document_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array'],
            'lines.*.good_id' => ['nullable'],
            'lines.*.quantity_counted' => ['nullable'],
            'commit' => ['required', 'in:draft,post'],
        ]);

        $warehouseId = (int) $validated['warehouse_id'];
        $this->assertWarehouseInBranch($warehouseId, $branchId);

        $lines = $this->filterAuditLines($request->input('lines', []));

        if ($lines === []) {
            return back()->withInput()->withErrors(['lines' => 'Добавьте строки с фактическим количеством по товарам.']);
        }

        if ($validated['commit'] === 'draft') {
            try {
                DB::transaction(function () use ($stockAudit, $warehouseId, $validated, $lines, $branchId) {
                    $stockAudit->update([
                        'warehouse_id' => $warehouseId,
                        'document_date' => $validated['document_date'],
                        'note' => isset($validated['note']) ? trim((string) $validated['note']) : null,
                    ]);

                    StockAuditLine::query()->where('stock_audit_id', $stockAudit->id)->delete();

                    foreach ($lines as $line) {
                        $goodId = (int) $line['good_id'];
                        $counted = $line['quantity_counted'];
                        $this->assertGoodInBranch($goodId, $branchId);
                        StockAuditLine::query()->create([
                            'stock_audit_id' => $stockAudit->id,
                            'good_id' => $goodId,
                            'quantity_book' => null,
                            'unit_cost_snapshot' => null,
                            'quantity_counted' => $counted,
                        ]);
                    }
                });
            } catch (RuntimeException $e) {
                return redirect()
                    ->route('admin.stock.audit.edit', $stockAudit)
                    ->withInput()
                    ->withErrors(['lines' => $e->getMessage()]);
            }

            return redirect()
                ->route('admin.stock.audit', ['warehouse_id' => $warehouseId])
                ->with(
                    'status',
                    'Черновик № '.$stockAudit->id.' сохранён. Откройте его в журнале при необходимости или нажмите «Новая ревизия», чтобы начать следующий.'
                );
        }

        try {
            DB::transaction(function () use ($stockAudit, $branchId, $warehouseId, $validated, $lines) {
                $stockAudit->update([
                    'warehouse_id' => $warehouseId,
                    'document_date' => $validated['document_date'],
                    'note' => isset($validated['note']) ? trim((string) $validated['note']) : null,
                    'is_draft' => false,
                ]);

                StockAuditLine::query()->where('stock_audit_id', $stockAudit->id)->delete();

                foreach ($lines as $line) {
                    $goodId = (int) $line['good_id'];
                    $counted = $line['quantity_counted'];
                    $this->assertGoodInBranch($goodId, $branchId);

                    $balance = OpeningStockBalance::query()
                        ->where('warehouse_id', $warehouseId)
                        ->where('good_id', $goodId)
                        ->first();

                    $bookQty = $balance === null ? '0' : (string) $balance->quantity;
                    $ucSnap = $balance?->unit_cost;

                    StockAuditLine::query()->create([
                        'stock_audit_id' => $stockAudit->id,
                        'good_id' => $goodId,
                        'quantity_book' => $bookQty,
                        'unit_cost_snapshot' => $ucSnap,
                        'quantity_counted' => $counted,
                    ]);

                    $this->openingBalanceService->applyAuditAdjustment($branchId, $warehouseId, $goodId, $counted);
                }
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.stock.audit.edit', $stockAudit)
                ->withInput()
                ->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.stock.audit', ['warehouse_id' => $warehouseId])
            ->with('status', 'Ревизия проведена. Скачайте отчёт Excel в журнале.');
    }

    public function auditDestroy(StockAudit $stockAudit): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        if ((int) $stockAudit->branch_id !== $branchId) {
            abort(404);
        }

        $warehouseId = (int) $stockAudit->warehouse_id;

        if ($stockAudit->is_draft) {
            $stockAudit->delete();

            return redirect()
                ->route('admin.stock.audit', ['warehouse_id' => $warehouseId])
                ->with('status', 'Черновик удалён.');
        }

        try {
            DB::transaction(function () use ($stockAudit, $branchId, $warehouseId) {
                $stockAudit->load('lines');

                foreach ($stockAudit->lines as $line) {
                    $goodId = (int) $line->good_id;
                    $book = $line->quantity_book !== null ? (float) $line->quantity_book : null;
                    $counted = (float) $line->quantity_counted;

                    if ($book === null) {
                        throw new RuntimeException(
                            'В строках документа нет снимка учёта до ревизии — удаление проведённого документа не поддерживается.'
                        );
                    }

                    $diff = $counted - $book;
                    if (abs($diff) < 1e-9) {
                        continue;
                    }

                    if ($diff > 0) {
                        $this->openingBalanceService->reverseIncomingLine(
                            $warehouseId,
                            $goodId,
                            $diff,
                            $line->unit_cost_snapshot
                        );
                    } else {
                        $this->openingBalanceService->reverseOutboundSaleLine(
                            $branchId,
                            $warehouseId,
                            $goodId,
                            abs($diff)
                        );
                    }
                }

                $stockAudit->delete();
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.stock.audit', ['warehouse_id' => $warehouseId])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.stock.audit', ['warehouse_id' => $warehouseId])
            ->with('status', 'Документ ревизии удалён, остатки по складу откатаны.');
    }

    /**
     * Объединить несколько черновиков ревизии в один: по каждому товару количества суммируются (как общий факт по складу).
     */
    public function auditMergeDrafts(Request $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $validated = $request->validate([
            'draft_ids' => ['required', 'array', 'min:2'],
            'draft_ids.*' => ['integer', 'distinct'],
            'warehouse_id' => ['nullable', 'integer'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['draft_ids'])));
        $backToJournal = function () use ($request) {
            $wid = (int) $request->input('warehouse_id', 0);

            return redirect()->route('admin.stock.audit', $wid > 0 ? ['warehouse_id' => $wid] : []);
        };

        $docs = StockAudit::query()
            ->where('branch_id', $branchId)
            ->whereIn('id', $ids)
            ->where('is_draft', true)
            ->with('lines')
            ->orderBy('id')
            ->get();

        if ($docs->count() !== count($ids)) {
            return $backToJournal()
                ->with('error', 'Не удалось объединить: выберите только существующие черновики ревизии.');
        }

        $warehouseIds = $docs->pluck('warehouse_id')->unique()->values();
        if ($warehouseIds->count() !== 1) {
            return $backToJournal()
                ->with('error', 'Объединять можно только черновики по одному складу.');
        }

        $warehouseId = (int) $warehouseIds->first();

        $sums = [];
        foreach ($docs as $doc) {
            foreach ($doc->lines as $line) {
                $gid = (int) $line->good_id;
                if (! isset($sums[$gid])) {
                    $sums[$gid] = 0.0;
                }
                $sums[$gid] += (float) $line->quantity_counted;
            }
        }

        if ($sums === []) {
            return redirect()
                ->route('admin.stock.audit', ['warehouse_id' => $warehouseId])
                ->with('error', 'В выбранных черновиках нет строк с количеством — нечего объединять.');
        }

        $mergedLines = [];
        foreach ($sums as $gid => $sum) {
            if ($sum < 0) {
                return redirect()
                    ->route('admin.stock.audit', ['warehouse_id' => $warehouseId])
                    ->with('error', 'Суммарное количество не может быть отрицательным.');
            }
            $this->assertGoodInBranch((int) $gid, $branchId);
            $mergedLines[] = [
                'good_id' => (int) $gid,
                'quantity_counted' => (string) round($sum, 4),
            ];
        }

        $maxDate = $docs->max('document_date');
        $sourceLabel = 'Объединение черновиков № '.implode(', ', $ids).'.';

        try {
            $newDoc = DB::transaction(function () use ($branchId, $warehouseId, $maxDate, $sourceLabel, $mergedLines, $docs) {
                foreach ($docs as $d) {
                    $d->delete();
                }

                $doc = StockAudit::query()->create([
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'document_date' => $maxDate,
                    'note' => $sourceLabel,
                    'is_draft' => true,
                ]);

                foreach ($mergedLines as $line) {
                    StockAuditLine::query()->create([
                        'stock_audit_id' => $doc->id,
                        'good_id' => $line['good_id'],
                        'quantity_book' => null,
                        'unit_cost_snapshot' => null,
                        'quantity_counted' => $line['quantity_counted'],
                    ]);
                }

                return $doc;
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.stock.audit', ['warehouse_id' => $warehouseId])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.stock.audit.edit', $newDoc)
            ->with('status', 'Черновики объединены: по каждой позиции количества сложены. Проверьте и проведите ревизию один раз.');
    }

    /**
     * Строки формы перемещения после ошибки валидации (товары только из справочника).
     *
     * @return list<array<string, mixed>>
     */
    private function moveInitialRowsForForm(Request $request, int $branchId): array
    {
        $oldLines = $request->old('lines');
        if (! is_array($oldLines) || $oldLines === []) {
            return [];
        }

        $out = [];
        foreach ($oldLines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $gid = (int) ($line['good_id'] ?? 0);
            if ($gid <= 0) {
                continue;
            }
            $good = Good::query()->where('branch_id', $branchId)->find($gid);
            if ($good === null) {
                continue;
            }
            $out[] = [
                'good_id' => $gid,
                'query' => $good->article_code.' — '.$good->name,
                'name' => $good->name,
                'article' => $good->article_code,
                'unit' => $good->unit ?? 'шт.',
                'qty' => (string) ($line['quantity'] ?? ''),
                'unit_cost' => '',
                'stock_qty' => null,
                'article_manual' => '',
                'name_manual' => '',
                'unit_manual' => 'шт.',
            ];
        }

        return $out;
    }

    /**
     * Строки формы оприходования: после ошибки валидации или из документа.
     *
     * @return list<array<string, mixed>>
     */
    private function incomingInitialRowsForForm(Request $request, StockSurplus $doc): array
    {
        $oldLines = $request->old('lines');
        if (is_array($oldLines) && $oldLines !== []) {
            $out = [];
            foreach ($oldLines as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $gid = (int) ($line['good_id'] ?? 0);
                if ($gid > 0) {
                    $good = Good::query()->where('branch_id', $doc->branch_id)->find($gid);
                    if ($good === null) {
                        continue;
                    }
                    $out[] = [
                        'good_id' => $gid,
                        'query' => $good->article_code.' — '.$good->name,
                        'name' => $good->name,
                        'article' => $good->article_code,
                        'unit' => $good->unit ?? 'шт.',
                        'qty' => (string) ($line['quantity'] ?? ''),
                        'unit_cost' => isset($line['unit_cost']) ? trim((string) $line['unit_cost']) : '',
                        'sale_price' => isset($line['sale_price']) ? trim((string) $line['sale_price']) : ($good->sale_price !== null ? (string) $good->sale_price : ''),
                        'stock_qty' => null,
                        'article_manual' => '',
                        'name_manual' => '',
                        'unit_manual' => 'шт.',
                    ];

                    continue;
                }

                $article = trim((string) ($line['article_code'] ?? ''));
                $manualName = trim((string) ($line['manual_name'] ?? ''));
                if ($article === '' || $manualName === '') {
                    continue;
                }
                $out[] = [
                    'good_id' => null,
                    'query' => '',
                    'name' => '',
                    'article' => '',
                    'unit' => '',
                    'qty' => (string) ($line['quantity'] ?? ''),
                    'unit_cost' => isset($line['unit_cost']) ? trim((string) $line['unit_cost']) : '',
                    'sale_price' => isset($line['sale_price']) ? trim((string) $line['sale_price']) : '',
                    'stock_qty' => null,
                    'article_manual' => $article,
                    'name_manual' => $manualName,
                    'unit_manual' => trim((string) ($line['unit'] ?? '')) ?: 'шт.',
                ];
            }

            return $out;
        }

        $doc->loadMissing('lines.good');

        return $doc->lines->map(function ($line) {
            $g = $line->good;
            $article = $g?->article_code ?? '';
            $name = $g?->name ?? '';

            return [
                'good_id' => (int) $line->good_id,
                'query' => ($article !== '' || $name !== '') ? $article.' — '.$name : '',
                'name' => $name,
                'article' => $article,
                'unit' => $g?->unit ?? 'шт.',
                'qty' => (string) $line->quantity,
                'unit_cost' => $line->unit_cost !== null ? (string) $line->unit_cost : '',
                'sale_price' => $g?->sale_price !== null ? (string) $g->sale_price : '',
                'stock_qty' => null,
                'article_manual' => '',
                'name_manual' => '',
                'unit_manual' => 'шт.',
            ];
        })->values()->all();
    }

    /**
     * @return list<array{good_id: int, query: string, name: string, article: string, unit: string, qty: string, unit_cost: string, stock_qty: null}>
     */
    private function writeoffInitialRowsForForm(Request $request, StockWriteoff $doc): array
    {
        $oldLines = $request->old('lines');
        if (is_array($oldLines) && $oldLines !== []) {
            $out = [];
            foreach ($oldLines as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $gid = (int) ($line['good_id'] ?? 0);
                if ($gid <= 0) {
                    continue;
                }
                $good = Good::query()->where('branch_id', $doc->branch_id)->find($gid);
                if ($good === null) {
                    continue;
                }
                $out[] = [
                    'good_id' => $gid,
                    'query' => $good->article_code.' — '.$good->name,
                    'name' => $good->name,
                    'article' => $good->article_code,
                    'unit' => $good->unit ?? 'шт.',
                    'qty' => (string) ($line['quantity'] ?? ''),
                    'unit_cost' => '',
                    'stock_qty' => null,
                ];
            }

            return $out;
        }

        $doc->loadMissing('lines.good');

        return $doc->lines->map(function ($line) {
            $g = $line->good;
            $article = $g?->article_code ?? '';
            $name = $g?->name ?? '';

            return [
                'good_id' => (int) $line->good_id,
                'query' => ($article !== '' || $name !== '') ? $article.' — '.$name : '',
                'name' => $name,
                'article' => $article,
                'unit' => $g?->unit ?? 'шт.',
                'qty' => (string) $line->quantity,
                'unit_cost' => '',
                'stock_qty' => null,
            ];
        })->values()->all();
    }

    /**
     * @return Collection<int, Warehouse>
     */
    private function warehousesForBranch(int $branchId)
    {
        return Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function resolveWarehouseFilter(Request $request, $warehouses): int
    {
        $selectedWarehouseId = (int) $request->integer('warehouse_id');
        $defaultId = $warehouses->firstWhere('is_default')?->id ?? $warehouses->first()?->id;
        if ($selectedWarehouseId === 0 || ! $warehouses->contains('id', $selectedWarehouseId)) {
            $selectedWarehouseId = (int) ($defaultId ?? 0);
        }

        return $selectedWarehouseId;
    }

    private function assertWarehouseInBranch(int $warehouseId, int $branchId): void
    {
        $ok = Warehouse::query()->where('id', $warehouseId)->where('branch_id', $branchId)->exists();
        if (! $ok) {
            abort(403);
        }
    }

    private function assertGoodInBranch(int $goodId, int $branchId): void
    {
        $ok = Good::query()->where('id', $goodId)->where('branch_id', $branchId)->exists();
        if (! $ok) {
            abort(403);
        }
    }

    /**
     * @param  list<mixed>  $lines
     * @return list<array{good_id: int, quantity: string}>
     */
    private function filterGoodQuantityLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $gid = isset($line['good_id']) ? (int) $line['good_id'] : 0;
            if ($gid <= 0) {
                continue;
            }
            $qtyRaw = $line['quantity'] ?? null;
            $qty = $this->openingBalanceService->parseDecimal($qtyRaw);
            if ($qty === null || (float) $qty <= 0) {
                continue;
            }
            $out[] = ['good_id' => $gid, 'quantity' => $qty];
        }

        return $out;
    }

    /**
     * @param  list<mixed>  $lines
     * @return list<array{good_id: int, quantity: string, unit_cost: mixed}>
     */
    /**
     * @return list<array{good_id: int, quantity: string, unit_cost: mixed}|array{good_id: null, article_code: string, manual_name: string, unit: string, quantity: string, unit_cost: mixed}>
     */
    private function filterIncomingLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $qtyRaw = $line['quantity'] ?? null;
            $qty = $this->openingBalanceService->parseDecimal($qtyRaw);
            if ($qty === null || (float) $qty <= 0) {
                continue;
            }
            $uc = $line['unit_cost'] ?? null;
            $gid = isset($line['good_id']) ? (int) $line['good_id'] : 0;
            $saleRaw = $line['sale_price'] ?? null;
            if ($saleRaw !== null && is_string($saleRaw) && trim($saleRaw) === '') {
                $saleRaw = null;
            }

            if ($gid > 0) {
                $out[] = [
                    'good_id' => $gid,
                    'quantity' => $qty,
                    'unit_cost' => $uc,
                    'sale_price' => $saleRaw,
                ];

                continue;
            }

            $article = trim((string) ($line['article_code'] ?? ''));
            $manualName = trim((string) ($line['manual_name'] ?? ''));
            $unit = trim((string) ($line['unit'] ?? ''));
            if ($unit === '') {
                $unit = 'шт.';
            }
            if ($article === '' || $manualName === '') {
                continue;
            }

            $out[] = [
                'good_id' => null,
                'article_code' => $article,
                'manual_name' => $manualName,
                'unit' => $unit,
                'quantity' => $qty,
                'unit_cost' => $uc,
                'sale_price' => $saleRaw,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function incomingUnitCostValidationError(array $lines): ?string
    {
        foreach ($lines as $line) {
            $raw = $line['unit_cost'] ?? null;
            if ($raw === null || (is_string($raw) && trim($raw) === '')) {
                continue;
            }
            $parsed = $this->openingBalanceService->parseOptionalMoney($raw);
            if ($parsed === null) {
                return 'Проверьте закупочную цену: введите число (например 1250 или 1250,50).';
            }
        }

        return null;
    }

    /**
     * Строки ревизии: один товар — одна строка (количества суммируются при дубликатах в форме).
     *
     * @param  list<mixed>  $lines
     * @return list<array{good_id: int, quantity_counted: string}>
     */
    private function filterAuditLines(array $lines): array
    {
        $sums = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $gid = isset($line['good_id']) ? (int) $line['good_id'] : 0;
            if ($gid <= 0) {
                continue;
            }
            $raw = $line['quantity_counted'] ?? null;
            $q = $this->openingBalanceService->parseDecimal($raw);
            if ($q === null) {
                continue;
            }
            if ((float) $q < 0) {
                continue;
            }
            if (! isset($sums[$gid])) {
                $sums[$gid] = 0.0;
            }
            $sums[$gid] += (float) $q;
        }

        $out = [];
        foreach ($sums as $gid => $sum) {
            $out[] = ['good_id' => (int) $gid, 'quantity_counted' => (string) round($sum, 4)];
        }

        return $out;
    }

    /**
     * Строки формы ревизии: после ошибки валидации или из черновика.
     *
     * @return list<array<string, mixed>>
     */
    private function auditInitialRowsForForm(Request $request, int $branchId, ?StockAudit $document = null): array
    {
        $oldLines = $request->old('lines');
        if (is_array($oldLines) && $oldLines !== []) {
            $out = [];
            foreach ($oldLines as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $gid = (int) ($line['good_id'] ?? 0);
                if ($gid <= 0) {
                    continue;
                }
                $good = Good::query()->where('branch_id', $branchId)->find($gid);
                if ($good === null) {
                    continue;
                }
                $out[] = [
                    'good_id' => $gid,
                    'name' => $good->name,
                    'article' => $good->article_code,
                    'unit' => $good->unit ?? 'шт.',
                    'quantity_counted' => (string) ($line['quantity_counted'] ?? ''),
                    'stock_qty' => null,
                    'barcode' => $good->barcode ?? '',
                ];
            }

            return $out;
        }

        if ($document !== null) {
            return $this->stockAuditDocumentRowsForClient($document);
        }

        return [];
    }

    /**
     * Строки черновика ревизии в формате для формы / JSON.
     *
     * @return list<array<string, mixed>>
     */
    private function stockAuditDocumentRowsForClient(StockAudit $document): array
    {
        $document->loadMissing('lines.good');
        $wid = (int) $document->warehouse_id;

        return $document->lines->map(function ($line) use ($wid) {
            $g = $line->good;
            $stockQty = null;
            if ($wid > 0 && $line->good_id) {
                $bal = OpeningStockBalance::query()
                    ->where('warehouse_id', $wid)
                    ->where('good_id', $line->good_id)
                    ->first();
                $stockQty = $bal?->quantity;
            }

            return [
                'good_id' => (int) $line->good_id,
                'name' => $g?->name ?? '',
                'article' => $g?->article_code ?? '',
                'unit' => $g?->unit ?? 'шт.',
                'quantity_counted' => (string) $line->quantity_counted,
                'stock_qty' => $stockQty,
                'barcode' => $g?->barcode ?? '',
            ];
        })->values()->all();
    }
}
