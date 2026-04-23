<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportSaleGoodsRequest;
use App\Http\Requests\StoreSaleGoodsRequest;
use App\Http\Requests\UpdateSaleGoodsRequest;
use App\Models\Good;
use App\Services\OpeningBalanceService;
use App\Services\SaleGoodsImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleGoodsController extends Controller
{
    private const PER_PAGE = 80;

    public function __construct(
        private readonly OpeningBalanceService $openingBalanceService
    ) {}

    public function index(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        $q = trim((string) $request->query('q', ''));

        $query = Good::query()
            ->where('branch_id', $branchId)
            ->where('is_service', false);

        if ($q !== '') {
            $like = '%'.$this->escapeLikePattern($q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)
                    ->orWhere('article_code', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhere('category', 'like', $like)
                    ->orWhere('oem', 'like', $like)
                    ->orWhere('factory_number', 'like', $like);
            });
        }

        $goods = $query
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $placeholderId = 9_999_999_001;
        $editUrlTemplate = str_replace(
            (string) $placeholderId,
            '__ID__',
            route('admin.sale-goods.edit', ['good' => $placeholderId], true)
        );

        return view('admin.sale-goods.index', [
            'goods' => $goods,
            'searchQuery' => $q,
            'goodsSearchConfig' => [
                'searchUrl' => route('admin.goods.search', ['exclude_services' => true]),
                'editUrlTemplate' => $editUrlTemplate,
                'initialQuery' => $q,
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.sale-goods.create', [
            'good' => new Good([
                'unit' => 'шт.',
                'is_service' => false,
            ]),
        ]);
    }

    public function store(StoreSaleGoodsRequest $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $v = $request->validated();

        Good::query()->create($this->payloadFromRequest($v, $branchId, false));

        return redirect()
            ->route('admin.sale-goods.index')
            ->with('status', 'Товар добавлен.');
    }

    public function edit(int $good): View
    {
        $model = $this->goodsGoodOrAbort($good);

        return view('admin.sale-goods.edit', [
            'good' => $model,
        ]);
    }

    public function update(UpdateSaleGoodsRequest $request, int $good): RedirectResponse
    {
        $model = $this->goodsGoodOrAbort($good);
        $v = $request->validated();

        $model->update(
            $this->payloadFromRequest($v, (int) $model->branch_id, true)
        );

        return redirect()
            ->route('admin.sale-goods.index')
            ->with('status', 'Товар сохранён.');
    }

    public function destroy(int $good): RedirectResponse
    {
        $model = $this->goodsGoodOrAbort($good);

        if ($this->goodHasBlockingReferences($model)) {
            return redirect()
                ->route('admin.sale-goods.index')
                ->withErrors(['delete' => 'Нельзя удалить товар: он используется в документах или на складе. Сначала исправьте справочные данные или оформите корректировки.']);
        }

        $model->delete();

        return redirect()
            ->route('admin.sale-goods.index')
            ->with('status', 'Товар удалён.');
    }

    public function import(ImportSaleGoodsRequest $request, SaleGoodsImportService $importService): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $path = $request->file('file')->getRealPath();
        if ($path === false) {
            return back()->withErrors(['file' => 'Не удалось прочитать файл.']);
        }

        $result = $importService->importFromFile($path, $branchId);

        $message = 'Импортировано товаров: '.$result['imported'].'.';
        if ($result['skipped'] > 0) {
            $message .= ' Пропущено пустых строк: '.$result['skipped'].'.';
        }

        if ($result['errors'] !== []) {
            session()->flash('import_errors', array_slice($result['errors'], 0, 40));
        }

        return redirect()
            ->route('admin.sale-goods.index')
            ->with('status', $message);
    }

    public function sampleImport(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Артикул', 'Наименование *', 'Ед.', 'Цена, сом', 'Штрихкод', 'Категория'],
            ['ART-001', 'Фильтр масляный', 'шт.', '450', '', 'Расходники'],
            ['', 'Колодки передние', ' компл.', '2500,50', '', ''],
        ], null, 'A1', true);

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'obrazec_tovarov.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function goodsGoodOrAbort(int $id): Good
    {
        $branchId = (int) auth()->user()->branch_id;

        return Good::query()
            ->where('branch_id', $branchId)
            ->where('is_service', false)
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private function payloadFromRequest(array $v, int $branchId, bool $forUpdate): array
    {
        $unit = trim((string) ($v['unit'] ?? 'шт.'));

        $salePrice = $this->openingBalanceService->parseOptionalMoney($v['sale_price'] ?? null);
        $wholesalePrice = $this->openingBalanceService->parseOptionalMoney($v['wholesale_price'] ?? null);
        $minSalePrice = $this->openingBalanceService->parseOptionalMoney($v['min_sale_price'] ?? null);
        $minStock = $this->openingBalanceService->parseOptionalNonNegativeDecimal($v['min_stock'] ?? null);

        $barcode = isset($v['barcode']) ? trim((string) $v['barcode']) : '';
        $barcode = $barcode === '' ? null : $barcode;
        $category = isset($v['category']) ? trim((string) $v['category']) : '';
        $category = $category === '' ? null : $category;
        $oem = isset($v['oem']) ? trim((string) $v['oem']) : '';
        $oem = $oem === '' ? null : $oem;
        $factory = isset($v['factory_number']) ? trim((string) $v['factory_number']) : '';
        $factory = $factory === '' ? null : $factory;

        $data = [
            'article_code' => trim((string) $v['article_code']),
            'name' => trim((string) $v['name']),
            'barcode' => $barcode,
            'category' => $category,
            'unit' => $unit !== '' ? $unit : 'шт.',
            'sale_price' => $salePrice,
            'wholesale_price' => $wholesalePrice,
            'is_service' => false,
            'min_sale_price' => $minSalePrice,
            'oem' => $oem,
            'factory_number' => $factory,
            'min_stock' => $minStock,
        ];

        if (! $forUpdate) {
            $data['branch_id'] = $branchId;
        }

        return $data;
    }

    private function goodHasBlockingReferences(Good $good): bool
    {
        return $good->retailSaleLines()->exists()
            || $good->legalEntitySaleLines()->exists()
            || $good->serviceOrderLines()->exists()
            || $good->purchaseReceiptLines()->exists()
            || $good->customerReturnLines()->exists()
            || $good->openingStockBalances()->exists();
    }

    private function escapeLikePattern(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
