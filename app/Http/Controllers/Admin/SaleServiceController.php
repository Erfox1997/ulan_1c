<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportSaleServicesRequest;
use App\Http\Requests\StoreSaleServiceRequest;
use App\Http\Requests\UpdateSaleServiceRequest;
use App\Models\Good;
use App\Services\OpeningBalanceService;
use App\Services\SaleServiceImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleServiceController extends Controller
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
            ->where('is_service', true);

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

        $placeholderId = 9_999_999_002;
        $editUrlTemplate = str_replace(
            (string) $placeholderId,
            '__ID__',
            route('admin.sale-services.edit', ['service' => $placeholderId], true)
        );

        return view('admin.sale-services.index', [
            'services' => $services,
            'searchQuery' => $q,
            'servicesSearchConfig' => [
                'searchUrl' => route('admin.goods.search', ['services_only' => true]),
                'editUrlTemplate' => $editUrlTemplate,
                'initialQuery' => $q,
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.sale-services.create', [
            'service' => new Good([
                'unit' => 'усл.',
                'is_service' => true,
            ]),
        ]);
    }

    public function store(StoreSaleServiceRequest $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $salePrice = $this->openingBalanceService->parseOptionalMoney($request->input('sale_price'));

        Good::query()->create([
            'branch_id' => $branchId,
            'article_code' => $this->generateUniqueServiceArticleCode($branchId),
            'name' => trim((string) $request->validated('name')),
            'unit' => trim((string) ($request->validated('unit') ?: 'усл.')) ?: 'усл.',
            'sale_price' => $salePrice,
            'is_service' => true,
        ]);

        return redirect()
            ->route('admin.sale-services.index')
            ->with('status', 'Услуга добавлена.');
    }

    public function edit(int $service): View
    {
        $good = $this->serviceGoodOrAbort($service);

        return view('admin.sale-services.edit', [
            'service' => $good,
        ]);
    }

    public function update(UpdateSaleServiceRequest $request, int $service): RedirectResponse
    {
        $good = $this->serviceGoodOrAbort($service);
        $salePrice = $this->openingBalanceService->parseOptionalMoney($request->input('sale_price'));

        $good->update([
            'name' => trim((string) $request->validated('name')),
            'unit' => trim((string) ($request->validated('unit') ?: 'усл.')) ?: 'усл.',
            'sale_price' => $salePrice,
        ]);

        return redirect()
            ->route('admin.sale-services.index')
            ->with('status', 'Услуга сохранена.');
    }

    public function destroy(int $service): RedirectResponse
    {
        $good = $this->serviceGoodOrAbort($service);

        if ($good->retailSaleLines()->exists()) {
            return redirect()
                ->route('admin.sale-services.index')
                ->withErrors(['delete' => 'Нельзя удалить услугу: есть строки в розничных продажах.']);
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
