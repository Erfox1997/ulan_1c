<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportSaleServicesRequest;
use App\Http\Requests\StoreSaleServiceRequest;
use App\Http\Requests\UpdateSaleServiceRequest;
use App\Models\Good;
use App\Services\OpeningBalanceService;
use App\Services\SaleServiceImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleServiceController extends Controller
{
    private const PER_PAGE = 80;

    /** Query-string value for services without category (folder). */
    private const NO_CATEGORY_QUERY = '__no_category__';

    public function __construct(
        private readonly OpeningBalanceService $openingBalanceService
    ) {}

    public function index(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        $q = trim((string) $request->query('q', ''));
        $rawCategory = $request->query('category');

        $placeholderId = 9_999_999_002;
        $editUrlTemplate = str_replace(
            (string) $placeholderId,
            '__ID__',
            route('admin.sale-services.edit', ['service' => $placeholderId], true)
        );

        $servicesSearchConfig = [
            'searchUrl' => route('admin.goods.search', ['services_only' => true]),
            'editUrlTemplate' => $editUrlTemplate,
            'initialQuery' => $q,
            'openModalEventName' => 'sale-service-open-modal',
        ];

        if ($rawCategory === null || ! is_string($rawCategory) || trim($rawCategory) === '') {
            $categories = $this->saleServiceCategoryFolders($branchId);

            return view('admin.sale-services.index', [
                'viewMode' => 'categories',
                'categories' => $categories,
                'services' => null,
                'searchQuery' => '',
                'selectedCategoryKey' => null,
                'categoryTitle' => null,
                'servicesSearchConfig' => $servicesSearchConfig,
                'serviceModalConfig' => null,
            ]);
        }

        $categoryQueryParam = trim($rawCategory);
        $categoryFilter = $categoryQueryParam === self::NO_CATEGORY_QUERY
            ? ''
            : $categoryQueryParam;

        $query = Good::query()
            ->where('branch_id', $branchId)
            ->where('is_service', true);

        if ($categoryFilter === '') {
            $query->where(function ($w) {
                $w->whereNull('category')
                    ->orWhere('category', '')
                    ->orWhereRaw("TRIM(COALESCE(category, '')) = ''");
            });
        } else {
            $query->where('category', $categoryFilter);
        }

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

        $services = $query
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $categoryTitle = $categoryFilter === '' ? 'Без категории' : $categoryFilter;

        $serviceModalConfig = [
            'dataUrlTemplate' => str_replace(
                (string) $placeholderId,
                '__ID__',
                route('admin.sale-services.modal-data', ['service' => $placeholderId], true)
            ),
            'updateUrlTemplate' => str_replace(
                (string) $placeholderId,
                '__ID__',
                route('admin.sale-services.update', ['service' => $placeholderId], true)
            ),
            'csrf' => csrf_token(),
        ];

        return view('admin.sale-services.index', [
            'viewMode' => 'services',
            'categories' => collect(),
            'services' => $services,
            'searchQuery' => $q,
            'selectedCategoryKey' => $categoryQueryParam,
            'categoryTitle' => $categoryTitle,
            'servicesSearchConfig' => $servicesSearchConfig,
            'serviceModalConfig' => $serviceModalConfig,
        ]);
    }

    /**
     * @return Collection<int, array{label: string, count: int, category_key: string}>
     */
    private function saleServiceCategoryFolders(int $branchId): Collection
    {
        $groups = Good::query()
            ->where('branch_id', $branchId)
            ->where('is_service', true)
            ->get(['category'])
            ->groupBy(function (Good $g): string {
                $c = $g->category;
                if ($c === null) {
                    return '';
                }
                $t = trim((string) $c);

                return $t === '' ? '' : $t;
            })
            ->map(fn (Collection $group, string $key): array => [
                'label' => $key === '' ? 'Без категории' : $key,
                'count' => $group->count(),
                'category_key' => $key === '' ? self::NO_CATEGORY_QUERY : $key,
            ]);

        return $groups->values()->sort(function (array $a, array $b): int {
            $aUncat = $a['category_key'] === self::NO_CATEGORY_QUERY;
            $bUncat = $b['category_key'] === self::NO_CATEGORY_QUERY;
            if ($aUncat !== $bUncat) {
                return $aUncat ? 1 : -1;
            }

            return strcasecmp($a['label'], $b['label']);
        })->values();
    }

    public function modalData(int $service): JsonResponse
    {
        $model = $this->serviceGoodOrAbort($service);
        $branchId = (int) auth()->user()->branch_id;

        $fmtOpt = static function ($v): string {
            if ($v === null || $v === '') {
                return '';
            }

            return number_format((float) $v, 2, ',', ' ');
        };

        $goodPayload = [
            'id' => (int) $model->id,
            'article_code' => (string) $model->article_code,
            'name' => (string) $model->name,
            'unit' => (string) ($model->unit ?? 'усл.'),
            'sale_price' => $fmtOpt($model->sale_price),
            'category' => $model->category ?? '',
        ];

        return response()->json([
            'good' => $goodPayload,
            'categories' => $this->distinctSaleServiceCategoryNames($branchId),
        ]);
    }

    /**
     * @return list<string>
     */
    private function distinctSaleServiceCategoryNames(int $branchId): array
    {
        $names = Good::query()
            ->where('branch_id', $branchId)
            ->where('is_service', true)
            ->whereNotNull('category')
            ->pluck('category');

        return $names
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn (string $s): bool => $s !== '')
            ->unique()
            ->sort(fn (string $a, string $b): int => strcasecmp($a, $b))
            ->values()
            ->all();
    }

    public function create(): View
    {
        $branchId = (int) auth()->user()->branch_id;

        return view('admin.sale-services.create', [
            'service' => new Good([
                'unit' => 'усл.',
                'is_service' => true,
            ]),
            'serviceCategories' => $this->distinctSaleServiceCategoryNames($branchId),
        ]);
    }

    public function store(StoreSaleServiceRequest $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $v = $request->validated();
        $salePrice = $this->openingBalanceService->parseOptionalMoney($request->input('sale_price'));
        $category = isset($v['category']) ? trim((string) $v['category']) : '';
        $category = $category === '' ? null : $category;

        Good::query()->create([
            'branch_id' => $branchId,
            'article_code' => $this->generateUniqueServiceArticleCode($branchId),
            'name' => trim((string) $v['name']),
            'unit' => trim((string) ($v['unit'] ?: 'усл.')) ?: 'усл.',
            'sale_price' => $salePrice,
            'is_service' => true,
            'category' => $category,
        ]);

        return redirect()
            ->route('admin.sale-services.index')
            ->with('status', 'Услуга добавлена.');
    }

    public function edit(int $service): View
    {
        $branchId = (int) auth()->user()->branch_id;
        $good = $this->serviceGoodOrAbort($service);

        return view('admin.sale-services.edit', [
            'service' => $good,
            'serviceCategories' => $this->distinctSaleServiceCategoryNames($branchId),
        ]);
    }

    public function update(UpdateSaleServiceRequest $request, int $service): RedirectResponse|JsonResponse
    {
        $good = $this->serviceGoodOrAbort($service);
        $v = $request->validated();
        $salePrice = $this->openingBalanceService->parseOptionalMoney($request->input('sale_price'));
        $category = isset($v['category']) ? trim((string) $v['category']) : '';
        $category = $category === '' ? null : $category;

        $good->update([
            'name' => trim((string) $v['name']),
            'unit' => trim((string) ($v['unit'] ?: 'усл.')) ?: 'усл.',
            'sale_price' => $salePrice,
            'category' => $category,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => 'Услуга сохранена.']);
        }

        return redirect()
            ->route('admin.sale-services.index')
            ->with('status', 'Услуга сохранена.');
    }

    public function destroy(int $service): RedirectResponse
    {
        $good = $this->serviceGoodOrAbort($service);

        if ($good->retailSaleLines()->exists()
            || $good->serviceOrderLines()->exists()
            || $good->legalEntitySaleLines()->exists()
            || $good->customerReturnLines()->exists()) {
            return redirect()
                ->route('admin.sale-services.index')
                ->withErrors([
                    'delete' => 'Нельзя удалить услугу: она используется в документах продаж или заказ-нарядах.',
                ]);
        }

        $good->delete();

        return redirect()
            ->route('admin.sale-services.index')
            ->with('status', 'Услуга удалена.');
    }

    public function import(ImportSaleServicesRequest $request, SaleServiceImportService $importService): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $path = $request->file('file')->getRealPath();
        if ($path === false) {
            return back()->withErrors(['file' => 'Не удалось прочитать файл.']);
        }

        $result = $importService->importFromFile($path, $branchId);

        $message = 'Импортировано услуг: '.$result['imported'].'.';
        if ($result['skipped'] > 0) {
            $message .= ' Пропущено пустых строк: '.$result['skipped'].'.';
        }

        if ($result['errors'] !== []) {
            session()->flash('import_errors', array_slice($result['errors'], 0, 40));
        }

        return redirect()
            ->route('admin.sale-services.index')
            ->with('status', $message);
    }

    public function sampleImport(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Наименование *', 'Единица', 'Цена, сом'],
            ['Диагностика автомобиля', 'усл.', '1500'],
            ['Замена масла', 'усл.', '800,50'],
            ['Сход-развал', 'час', ''],
        ], null, 'A1', true);

        foreach (range('A', 'C') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'obrazec_uslug.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function serviceGoodOrAbort(int $id): Good
    {
        $branchId = (int) auth()->user()->branch_id;

        return Good::query()
            ->where('branch_id', $branchId)
            ->where('is_service', true)
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * Внутренний код для связи с розницей; пользователю не показывается.
     */
    private function generateUniqueServiceArticleCode(int $branchId): string
    {
        for ($i = 0; $i < 25; $i++) {
            $code = 'SVC-'.Str::ulid();
            if (! Good::query()->where('branch_id', $branchId)->where('article_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Не удалось сгенерировать код услуги.');
    }

    private function escapeLikePattern(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
