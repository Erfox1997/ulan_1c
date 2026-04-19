<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportOpeningBalancesRequest;
use App\Http\Requests\StoreOpeningBalancesRequest;
use App\Models\OpeningStockBalance;
use App\Models\Warehouse;
use App\Services\OpeningBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpeningBalanceController extends Controller
{
    private const PER_PAGE = 100;

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

        $selectedWarehouseId = (int) old('warehouse_id', request()->integer('warehouse_id') ?: 0);
        $defaultId = $warehouses->firstWhere('is_default')?->id ?? $warehouses->first()?->id;

        if ($selectedWarehouseId === 0 || ! $warehouses->contains('id', $selectedWarehouseId)) {
            $selectedWarehouseId = (int) ($defaultId ?? 0);
        }

        $openingBalancesPaginator = null;
        $linesForForm = [$this->emptyOpeningBalanceLine()];

        if ($selectedWarehouseId !== 0) {
            $page = max(1, (int) old('page', request()->integer('page') ?: 1));

            $openingBalancesPaginator = OpeningStockBalance::query()
                ->where('branch_id', $branchId)
                ->where('warehouse_id', $selectedWarehouseId)
                ->with('good')
                ->orderBy('id')
                ->paginate(self::PER_PAGE, ['*'], 'page', $page)
                ->withQueryString();

            $openingBalancesPaginator->onEachSide = 1;

            $oldLines = old('lines');
            if (is_array($oldLines) && $oldLines !== []) {
                $lines = [];
                foreach ($oldLines as $line) {
                    if (! is_array($line)) {
                        continue;
                    }
                    $gid = $line['good_id'] ?? null;
                    $lines[] = [
                        'good_id' => $gid !== null && $gid !== '' ? (int) $gid : null,
                        'article_code' => (string) ($line['article_code'] ?? ''),
                        'name' => (string) ($line['name'] ?? ''),
                        'barcode' => (string) ($line['barcode'] ?? ''),
                        'category' => (string) ($line['category'] ?? ''),
                        'quantity' => (string) ($line['quantity'] ?? ''),
                        'unit_cost' => isset($line['unit_cost']) ? (string) $line['unit_cost'] : '',
                        'wholesale_price' => isset($line['wholesale_price']) ? (string) $line['wholesale_price'] : '',
                        'sale_price' => isset($line['sale_price']) ? (string) $line['sale_price'] : '',
                        'oem' => (string) ($line['oem'] ?? ''),
                        'factory_number' => (string) ($line['factory_number'] ?? ''),
                        'min_stock' => isset($line['min_stock']) ? (string) $line['min_stock'] : '',
                        'unit' => trim((string) ($line['unit'] ?? '')) ?: 'шт.',
                    ];
                }
                if ($lines === []) {
                    $lines = [$this->emptyOpeningBalanceLine()];
                }
                $linesForForm = $lines;
            } else {
                $balances = $openingBalancesPaginator->getCollection();
                $lines = $balances->map(fn (OpeningStockBalance $b) => [
                    'good_id' => (int) $b->good_id,
                    'article_code' => $b->good->article_code,
                    'name' => $b->good->name,
                    'barcode' => (string) ($b->good->barcode ?? ''),
                    'category' => (string) ($b->good->category ?? ''),
                    'quantity' => (string) $b->quantity,
                    'unit_cost' => $b->unit_cost !== null ? (string) $b->unit_cost : '',
                    'wholesale_price' => $b->good->wholesale_price !== null ? (string) $b->good->wholesale_price : '',
                    'sale_price' => $b->good->sale_price !== null ? (string) $b->good->sale_price : '',
                    'oem' => (string) ($b->good->oem ?? ''),
                    'factory_number' => (string) ($b->good->factory_number ?? ''),
                    'min_stock' => $b->good->min_stock !== null ? (string) $b->good->min_stock : '',
                    'unit' => $b->good->unit ?? 'шт.',
                ])->values()->all();

                if ($lines === []) {
                    $lines = [$this->emptyOpeningBalanceLine()];
                }
                $linesForForm = $lines;
            }
        }

        return view('admin.opening-balances.index', [
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'openingBalancesPaginator' => $openingBalancesPaginator,
            'linesForForm' => $linesForForm,
        ]);
    }

    public function store(StoreOpeningBalancesRequest $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $warehouseId = (int) $request->validated('warehouse_id');
        $lines = $request->input('lines', []);

        $normalized = [];
        $clearedGoodIds = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $code = trim((string) ($line['article_code'] ?? ''));
            if ($code === '') {
                $gidRaw = $line['good_id'] ?? null;
                if ($gidRaw !== null && $gidRaw !== '') {
                    $gid = (int) $gidRaw;
                    if ($gid > 0) {
                        $clearedGoodIds[] = $gid;
                    }
                }

                continue;
            }
            $normalized[] = [
                'article_code' => $code,
                'name' => trim((string) ($line['name'] ?? '')),
                'barcode' => trim((string) ($line['barcode'] ?? '')),
                'category' => trim((string) ($line['category'] ?? '')),
                'quantity' => $line['quantity'] ?? '',
                'unit_cost' => $line['unit_cost'] ?? null,
                'wholesale_price' => $line['wholesale_price'] ?? null,
                'sale_price' => $line['sale_price'] ?? null,
                'oem' => $line['oem'] ?? null,
                'factory_number' => $line['factory_number'] ?? null,
                'min_stock' => $line['min_stock'] ?? null,
                'unit' => trim((string) ($line['unit'] ?? '')) ?: 'шт.',
            ];
        }

        $deletedGoodIds = array_values(array_unique(array_merge(
            array_map('intval', $request->validated('deleted_good_ids') ?? []),
            $clearedGoodIds
        )));
        if ($deletedGoodIds !== []) {
            OpeningStockBalance::query()
                ->where('warehouse_id', $warehouseId)
                ->where('branch_id', $branchId)
                ->whereIn('good_id', $deletedGoodIds)
                ->delete();
        }

        $this->openingBalanceService->syncManualLines($branchId, $warehouseId, $normalized, deleteMissing: false);

        $page = max(1, (int) $request->input('page', 1));

        return redirect()->route('admin.opening-balances.index', [
            'warehouse_id' => $warehouseId,
            'page' => $page,
        ])
            ->with('status', 'Начальные остатки сохранены.');
    }

    public function import(ImportOpeningBalancesRequest $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $warehouseId = (int) $request->validated('warehouse_id');
        $path = $request->file('file')->getRealPath();
        if ($path === false) {
            return back()->withErrors(['file' => 'Не удалось прочитать файл.']);
        }

        $result = $this->openingBalanceService->importFromFile($path, $branchId, $warehouseId);

        $message = 'Импортировано позиций: '.$result['imported'].'.';
        if ($result['skipped'] > 0) {
            $message .= ' Пропущено пустых строк: '.$result['skipped'].'.';
        }

        if ($result['errors'] !== []) {
            session()->flash('import_errors', array_slice($result['errors'], 0, 40));
        }

        return redirect()->route('admin.opening-balances.index', [
            'warehouse_id' => $warehouseId,
            'page' => 1,
        ])
            ->with('status', $message);
    }

    public function sample(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Наименование', 'Штрихкод', 'Категория', 'Артикул', 'Количество', 'Ед. изм.', 'Закупочная цена', 'Оптовая цена', 'Продажная цена', 'ОЭМ', 'Заводской номер', 'Мин. остаток'],
            ['Фильтр масляный', '', 'Расходники', 'ART-001', '24', 'шт.', '350', '480', '520', 'OEM-001', 'SN-123', '2'],
            ['Свеча зажигания', '', 'Запчасти', 'ART-002', '100', 'шт.', '', '', '180', '', '', '5'],
        ], null, 'A1', true);

        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'obrazec_nachalnyh_ostatkov.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function emptyOpeningBalanceLine(): array
    {
        return [
            'good_id' => null,
            'article_code' => '',
            'name' => '',
            'barcode' => '',
            'category' => '',
            'quantity' => '',
            'unit_cost' => '',
            'wholesale_price' => '',
            'sale_price' => '',
            'oem' => '',
            'factory_number' => '',
            'min_stock' => '',
            'unit' => 'шт.',
        ];
    }
}
