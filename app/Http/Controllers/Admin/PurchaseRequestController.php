<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseRequestRequest;
use App\Models\OpeningStockBalance;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseRequestController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;

        $paginator = PurchaseRequest::query()
            ->where('branch_id', $branchId)
            ->with('user')
            ->withCount('lines')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.purchase-requests.index', [
            'pageTitle' => 'Заявки на закупку',
            'paginator' => $paginator,
        ]);
    }

    /**
     * Позиции выбранных заявок филиала (колонки экспорта: наименование, к закупке, ОЭМ).
     *
     * @param  list<int>  $purchaseRequestIds
     * @return Collection<int, PurchaseRequestLine>
     */
    private function exportLinesForPurchaseRequests(int $branchId, array $purchaseRequestIds): Collection
    {
        return PurchaseRequestLine::query()
            ->select('purchase_request_lines.*')
            ->join('purchase_requests', 'purchase_requests.id', '=', 'purchase_request_lines.purchase_request_id')
            ->where('purchase_requests.branch_id', $branchId)
            ->whereIn('purchase_requests.id', $purchaseRequestIds)
            ->orderByDesc('purchase_requests.created_at')
            ->orderByDesc('purchase_request_lines.id')
            ->with(['good'])
            ->get();
    }

    /**
     * @return list<int>
     */
    private function validatedPurchaseRequestIds(Request $request, int $branchId): array
    {
        /** @var array{ids: list<int|string>} $v */
        $v = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', Rule::exists('purchase_requests', 'id')->where('branch_id', $branchId)],
        ], [
            'ids.required' => 'Отметьте хотя бы одну заявку в списке.',
            'ids.min' => 'Отметьте хотя бы одну заявку в списке.',
        ]);

        return array_values(array_map('intval', $v['ids']));
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $ids = $this->validatedPurchaseRequestIds($request, $branchId);

        $lines = $this->exportLinesForPurchaseRequests($branchId, $ids);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Заявки');

        $sheet->fromArray([
            ['Наименование', 'К закупке', 'ОЭМ'],
        ], null, 'A1', true);

        $rowNum = 2;
        foreach ($lines as $line) {
            $name = (string) ($line->good?->name ?? '');
            $qty = (float) $line->quantity_requested;
            $oem = (string) ($line->oem_snapshot ?? '');
            $sheet->setCellValue('A'.$rowNum, $name === '' ? '—' : $name);
            $sheet->setCellValue('B'.$rowNum, $qty);
            $sheet->setCellValue('C'.$rowNum, $oem === '' ? '—' : $oem);
            $rowNum++;
        }

        foreach (['A', 'B', 'C'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $idsSlug = collect($ids)->sort()->implode('-');
        $filename = 'zayavki_zakupka_'.$idsSlug.'_'.now()->format('His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $branchId = (int) auth()->user()->branch_id;
        $ids = $this->validatedPurchaseRequestIds($request, $branchId);

        $branch = auth()->user()->branch;
        $lines = $this->exportLinesForPurchaseRequests($branchId, $ids);

        $rows = $lines->map(function (PurchaseRequestLine $line): array {
            $name = (string) ($line->good?->name ?? '');
            $qty = (float) $line->quantity_requested;
            $oem = (string) ($line->oem_snapshot ?? '');

            return [
                'name' => $name === '' ? '—' : $name,
                'qty' => $qty,
                'oem' => $oem === '' ? '—' : $oem,
            ];
        });

        $requestTitles = collect($ids)->sort()->values()->map(fn (int $id): string => '№ '.$id)->implode(', ');

        $pdf = Pdf::loadView('admin.purchase-requests.export-pdf', [
            'branchName' => (string) ($branch?->name ?? 'Филиал'),
            'requestTitles' => $requestTitles,
            'rows' => $rows,
            'generatedAt' => now(),
        ])
            ->setPaper('a4', 'portrait');

        $idsSlug = collect($ids)->sort()->implode('-');
        $basename = 'zayavki_zakupka_'.$idsSlug.'_'.now()->format('His').'.pdf';

        if ($request->boolean('inline')) {
            return $pdf->stream($basename);
        }

        return $pdf->download($basename);
    }

    public function show(PurchaseRequest $purchaseRequest): View
    {
        $purchaseRequest->load([
            'lines.good',
            'lines.warehouse',
            'user',
        ]);

        return view('admin.purchase-requests.show', [
            'pageTitle' => 'Заявка № '.$purchaseRequest->id,
            'purchaseRequest' => $purchaseRequest,
        ]);
    }

    public function store(StorePurchaseRequestRequest $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $validated = $request->validated();
        $items = $validated['items'];
        $note = isset($validated['note']) ? trim((string) $validated['note']) : '';
        $note = $note === '' ? null : $note;

        DB::transaction(function () use ($branchId, $items, $note): void {
            $header = PurchaseRequest::query()->create([
                'branch_id' => $branchId,
                'user_id' => (int) auth()->id(),
                'note' => $note,
            ]);

            foreach ($items as $row) {
                $balanceId = (int) $row['opening_stock_balance_id'];
                /** @var OpeningStockBalance $balance */
                $balance = OpeningStockBalance::query()
                    ->where('branch_id', $branchId)
                    ->whereKey($balanceId)
                    ->with('good')
                    ->firstOrFail();

                $good = $balance->good;
                if ($good === null || $good->is_service) {
                    abort(422, 'Некорректная позиция остатка.');
                }

                $qtyReq = (float) $row['quantity'];

                PurchaseRequestLine::query()->create([
                    'purchase_request_id' => $header->id,
                    'good_id' => (int) $good->id,
                    'warehouse_id' => (int) $balance->warehouse_id,
                    'opening_stock_balance_id' => (int) $balance->id,
                    'quantity_requested' => $qtyReq,
                    'quantity_snapshot' => (float) $balance->quantity,
                    'min_stock_snapshot' => $good->min_stock !== null ? (float) $good->min_stock : null,
                    'oem_snapshot' => $good->oem !== null && $good->oem !== '' ? (string) $good->oem : null,
                ]);
            }
        });

        return redirect()
            ->back()
            ->with('status', 'Заявка на закупку создана.');
    }
}
