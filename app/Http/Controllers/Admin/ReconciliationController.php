<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\Counterparty;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnLine;
use App\Models\LegalEntitySale;
use App\Models\LegalEntitySaleLine;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptLine;
use App\Models\PurchaseReturn;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReconciliationController extends Controller
{
    /** Список «Покупатели» в сверке (тип в справочнике). */
    public const MODE_BUYERS = 'buyers';

    /** Список «Поставщики» в сверке (тип в справочнике). */
    public const MODE_SELLERS = 'sellers';

    public function index(Request $request): View|RedirectResponse
    {
        $resolved = $this->prepareReconciliationPage($request);
        if ($resolved instanceof RedirectResponse) {
            return $resolved;
        }

        return view('admin.reconciliation.index', $resolved);
    }

    public function exportPdf(Request $request): RedirectResponse|Response
    {
        $resolved = $this->prepareReconciliationPage($request);
        if ($resolved instanceof RedirectResponse) {
            return $resolved;
        }

        $resolved['branchName'] = auth()->user()->branch?->name ?? '—';
        $resolved['forPdf'] = true;
        $filename = $this->reconciliationExportFilename($resolved, 'pdf');

        return Pdf::loadView('admin.reconciliation.export-pdf', $resolved)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    public function exportExcel(Request $request): RedirectResponse|StreamedResponse
    {
        $resolved = $this->prepareReconciliationPage($request);
        if ($resolved instanceof RedirectResponse) {
            return $resolved;
        }

        $spreadsheet = $this->buildReconciliationSpreadsheet($resolved);
        $filename = $this->reconciliationExportFilename($resolved, 'xlsx');

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array<string, mixed>|RedirectResponse
     */
    private function prepareReconciliationPage(Request $request): array|RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;

        $mode = $this->normalizeMode($request->query('mode'));

        $branchHasAnyCounterparty = Counterparty::query()->where('branch_id', $branchId)->exists();

        $counterparties = $this->counterpartiesForReconciliationMode($branchId, $mode);

        $counterpartyId = (int) $request->integer('counterparty_id') ?: 0;
        $counterparty = $counterpartyId > 0
            ? Counterparty::query()->where('branch_id', $branchId)->find($counterpartyId)
            : null;

        if ($counterparty !== null && $counterparty->kind === Counterparty::KIND_OTHER) {
            return redirect()->route('admin.reconciliation.index', array_merge(
                $request->only(['date_from', 'date_to']),
                ['mode' => $mode],
            ));
        }

        if ($counterparty !== null && ! $this->counterpartyMatchesMode($counterparty, $mode)) {
            $alt = $mode === self::MODE_BUYERS ? self::MODE_SELLERS : self::MODE_BUYERS;
            if ($this->counterpartyMatchesMode($counterparty, $alt)) {
                return redirect()->route('admin.reconciliation.index', array_merge(
                    $request->only(['date_from', 'date_to']),
                    ['mode' => $alt, 'counterparty_id' => $counterparty->id]
                ));
            }

            abort(404);
        }

        if ($counterpartyId === 0) {
            // Список: всегда полный долг за всю историю (период на этом экране не задаётся).
            $from = Carbon::create(2000, 1, 1)->startOfDay();
            $to = Carbon::now()->endOfDay();
        } else {
            $anyDateFilter = $request->filled('date_from') || $request->filled('date_to');
            // Карточка контрагента: без дат — типичный месяц для разбора по периоду.
            if (! $anyDateFilter) {
                $from = Carbon::now()->startOfMonth();
                $to = Carbon::now()->startOfDay();
            } else {
                $from = $request->date('date_from') ?: Carbon::now()->startOfMonth();
                $to = $request->date('date_to') ?: Carbon::now()->startOfDay();
            }
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        $buyerRows = collect();
        $supplierRows = collect();
        $buyerOpening = '0';
        $buyerClosing = '0';
        $supplierOpening = '0';
        $supplierClosing = '0';
        $summaryRows = collect();
        $paidIncomePeriod = '0';
        $paidExpensePeriod = '0';
        $buyerSalesPeriod = '0';
        $buyerReturnsPeriod = '0';
        $buyerPeriodPurchasesNet = '0';
        $supplierPurchasesPeriod = '0';
        $supplierReturnsPeriod = '0';
        $buyerSaleCount = 0;
        $buyerReturnCount = 0;
        $buyerAvgSale = '0';
        $supplierPurchasesGross = '0';
        $supplierPurchaseCount = 0;
        $supplierReturnCount = 0;
        $supplierAvgPurchase = '0';

        if ($counterparty !== null) {
            $data = $this->reconciliationForCounterparty($branchId, $counterparty, $from, $to);
            $buyerRows = $data['buyerRows'];
            $supplierRows = $data['supplierRows'];
            $buyerOpening = $data['buyerOpening'];
            $buyerClosing = $data['buyerClosing'];
            $supplierOpening = $data['supplierOpening'];
            $supplierClosing = $data['supplierClosing'];
            $paidIncomePeriod = $data['paidIncomePeriod'];
            $paidExpensePeriod = $data['paidExpensePeriod'];
            $buyerSalesPeriod = $data['buyerSalesPeriod'];
            $buyerReturnsPeriod = $data['buyerReturnsPeriod'];
            $buyerPeriodPurchasesNet = $data['buyerPeriodPurchasesNet'];
            $supplierPurchasesPeriod = $data['supplierPurchasesPeriod'];
            $supplierReturnsPeriod = $data['supplierReturnsPeriod'];
            $buyerSaleCount = $data['buyerSaleCount'];
            $buyerReturnCount = $data['buyerReturnCount'];
            $buyerAvgSale = $data['buyerAvgSale'];
            $supplierPurchasesGross = $data['supplierPurchasesGross'];
            $supplierPurchaseCount = $data['supplierPurchaseCount'];
            $supplierReturnCount = $data['supplierReturnCount'];
            $supplierAvgPurchase = $data['supplierAvgPurchase'];
        } else {
            foreach ($counterparties as $cp) {
                $data = $this->reconciliationForCounterparty($branchId, $cp, $from, $to);
                if ($mode === self::MODE_BUYERS) {
                    $summaryRows->push([
                        'counterparty' => $cp,
                        'period_purchases' => $data['buyerPeriodPurchasesNet'],
                        'paid' => $data['paidIncomePeriod'],
                        'debt' => $data['buyerClosing'],
                        'opening_debt_card' => $this->openingDebtString($cp->opening_debt_as_buyer),
                    ]);
                } else {
                    $summaryRows->push([
                        'counterparty' => $cp,
                        'period_purchases' => $data['supplierPurchasesPeriod'],
                        'paid' => $data['paidExpensePeriod'],
                        'debt' => $data['supplierClosing'],
                        'opening_debt_card' => $this->openingDebtString($cp->opening_debt_as_supplier),
                    ]);
                }
            }
        }

        $payload = [
            'counterparties' => $counterparties,
            'branchHasAnyCounterparty' => $branchHasAnyCounterparty,
            'counterpartyId' => $counterpartyId,
            'counterparty' => $counterparty,
            'mode' => $mode,
            'from' => $from,
            'to' => $to,
            'summaryRows' => $summaryRows,
            'buyerRows' => $buyerRows,
            'supplierRows' => $supplierRows,
            'buyerOpening' => $buyerOpening,
            'buyerClosing' => $buyerClosing,
            'supplierOpening' => $supplierOpening,
            'supplierClosing' => $supplierClosing,
            'paidIncomePeriod' => $paidIncomePeriod,
            'paidExpensePeriod' => $paidExpensePeriod,
            'buyerSalesPeriod' => $buyerSalesPeriod,
            'buyerReturnsPeriod' => $buyerReturnsPeriod,
            'buyerPeriodPurchasesNet' => $buyerPeriodPurchasesNet,
            'supplierPurchasesPeriod' => $supplierPurchasesPeriod,
            'supplierReturnsPeriod' => $supplierReturnsPeriod ?? '0',
            'buyerSaleCount' => $buyerSaleCount,
            'buyerReturnCount' => $buyerReturnCount,
            'buyerAvgSale' => $buyerAvgSale,
            'supplierPurchasesGross' => $supplierPurchasesGross,
            'supplierPurchaseCount' => $supplierPurchaseCount,
            'supplierReturnCount' => $supplierReturnCount,
            'supplierAvgPurchase' => $supplierAvgPurchase,
        ];

        return $this->enrichReconciliationDisplay($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function enrichReconciliationDisplay(array $payload): array
    {
        $counterparty = $payload['counterparty'];
        $from = $payload['from'];
        /** @var Collection $buyerRows */
        $buyerRows = $payload['buyerRows'];
        /** @var Collection $supplierRows */
        $supplierRows = $payload['supplierRows'];
        /** @var Collection $summaryRows */
        $summaryRows = $payload['summaryRows'];

        $buyerDocs = collect();
        $buyerPaymentsList = collect();
        $supplierDocs = collect();
        $supplierPaymentsList = collect();
        $totalPeriodPurchases = '0';
        $totalPaid = '0';
        $totalDebt = '0';
        $totalOpeningCard = '0';

        if ($counterparty !== null) {
            $buyerDocs = $buyerRows->whereIn('kind', ['sale', 'return'])->values();
            $openingBuyerCard = number_format((float) ($counterparty->opening_debt_as_buyer ?? 0), 2, '.', '');
            $buyerDocs = collect([
                [
                    'sort' => $from->format('Y-m-d').'-0-0',
                    'date' => $from,
                    'kind' => 'opening_card',
                    'title' => 'Начальные долги',
                    'detail' => '',
                    'debit' => $openingBuyerCard,
                    'credit' => null,
                ],
            ])->merge($buyerDocs);

            $buyerPaymentsList = $buyerRows->where('kind', 'payment');
            $supplierDocs = $supplierRows->whereIn('kind', ['purchase', 'purchase_return'])->values();
            $openingSupplierCard = number_format((float) ($counterparty->opening_debt_as_supplier ?? 0), 2, '.', '');
            $supplierDocs = collect([
                [
                    'sort' => $from->format('Y-m-d').'-0-0',
                    'date' => $from,
                    'kind' => 'opening_card',
                    'title' => 'Начальные долги',
                    'detail' => '',
                    'debit' => null,
                    'credit' => $openingSupplierCard,
                ],
            ])->merge($supplierDocs);

            $supplierPaymentsList = $supplierRows->where('kind', 'payment');
        } elseif ($summaryRows->isNotEmpty()) {
            foreach ($summaryRows as $sr) {
                $totalPeriodPurchases = bcadd($totalPeriodPurchases, (string) $sr['period_purchases'], 2);
                $totalPaid = bcadd($totalPaid, (string) $sr['paid'], 2);
                $totalDebt = bcadd($totalDebt, (string) $sr['debt'], 2);
                $totalOpeningCard = bcadd($totalOpeningCard, (string) ($sr['opening_debt_card'] ?? '0'), 2);
            }
        }

        return array_merge($payload, [
            'buyerDocs' => $buyerDocs,
            'buyerPaymentsList' => $buyerPaymentsList,
            'supplierDocs' => $supplierDocs,
            'supplierPaymentsList' => $supplierPaymentsList,
            'totalPeriodPurchases' => $totalPeriodPurchases,
            'totalPaid' => $totalPaid,
            'totalDebt' => $totalDebt,
            'totalOpeningCard' => $totalOpeningCard,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function reconciliationExportFilename(array $data, string $ext): string
    {
        $mode = $data['mode'] === self::MODE_BUYERS ? 'pokupateli' : 'postavshchiki';
        $from = $data['from']->format('Y-m-d');
        $to = $data['to']->format('Y-m-d');
        /** @var Counterparty|null $cp */
        $cp = $data['counterparty'];
        if ($cp !== null) {
            $label = Str::slug(Str::limit(trim((string) ($cp->full_name ?: $cp->name)), 40, ''), '-');
            if ($label === '') {
                $label = 'kontragent-'.$cp->id;
            }

            return "sverka_{$mode}_{$label}_{$from}_{$to}.{$ext}";
        }

        return "sverka_{$mode}_{$from}_{$to}.{$ext}";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildReconciliationSpreadsheet(array $data): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Сверка');

        $isList = $data['counterparty'] === null;
        $isBuyers = $data['mode'] === self::MODE_BUYERS;
        $branchName = auth()->user()->branch?->name ?? '—';

        $sheet->setCellValue('A1', 'Сверка с контрагентами');
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', 'Филиал: '.$branchName);

        $row = 4;
        if ($isList) {
            $sheet->setCellValue('A'.$row, $isBuyers ? 'Покупатели' : 'Поставщики');
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);
            $row++;
            $headers = $isBuyers
                ? ['Контрагент', 'Начальные долги', 'Всего купил у нас', 'Всего перевёл', 'Долг нам (сейчас)']
                : ['Контрагент', 'Начальные долги', 'Всего закупили у него', 'Всего оплатили', 'Мы должны (сейчас)'];
            $col = 'A';
            foreach ($headers as $h) {
                $sheet->setCellValue($col.$row, $h);
                $col++;
            }
            $headerRow = $row;
            $row++;
            /** @var Collection $summaryRows */
            $summaryRows = $data['summaryRows'];
            foreach ($summaryRows as $sr) {
                $cp = $sr['counterparty'];
                $label = trim((string) $cp->full_name) !== '' ? $cp->full_name : $cp->name;
                $sheet->setCellValue('A'.$row, $label);
                $sheet->setCellValue('B'.$row, $this->excelMoney($sr['opening_debt_card'] ?? '0'));
                $sheet->setCellValue('C'.$row, $this->excelMoney($sr['period_purchases']));
                $sheet->setCellValue('D'.$row, $this->excelMoney($sr['paid']));
                $sheet->setCellValue('E'.$row, $this->excelMoney($sr['debt']));
                $row++;
            }
            $lastFilledRow = $row - 1;
            if ($summaryRows->isNotEmpty()) {
                $sheet->setCellValue('A'.$row, 'Итого по списку');
                $sheet->getStyle('A'.$row)->getFont()->setBold(true);
                $sheet->setCellValue('B'.$row, $this->excelMoney($data['totalOpeningCard']));
                $sheet->setCellValue('C'.$row, $this->excelMoney($data['totalPeriodPurchases']));
                $sheet->setCellValue('D'.$row, $this->excelMoney($data['totalPaid']));
                $sheet->setCellValue('E'.$row, $this->excelMoney($data['totalDebt']));
                $lastFilledRow = $row;
            }
            $this->applySheetBorders($sheet, $headerRow, $lastFilledRow);
        } else {
            /** @var Counterparty $cp */
            $cp = $data['counterparty'];
            $cpLabel = trim((string) $cp->full_name) !== '' ? $cp->full_name : $cp->name;
            $sheet->setCellValue('A'.$row, 'Контрагент: '.$cpLabel);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);
            $row++;
            $sheet->setCellValue(
                'A'.$row,
                'Период: '.$data['from']->format('d.m.Y').' — '.$data['to']->format('d.m.Y')
            );
            $row += 2;

            if ($isBuyers) {
                $buyerPayV = $data['buyerPaymentsList']->values();
                $buyerDocV = $data['buyerDocs']->values();
                $pairRows = max($buyerPayV->count(), $buyerDocV->count());
                $sheet->setCellValue('A'.$row, 'Оплатил');
                $sheet->mergeCells('A'.$row.':B'.$row);
                $sheet->setCellValue('C'.$row, 'Продажи и возвраты');
                $sheet->mergeCells('C'.$row.':E'.$row);
                $sheet->getStyle('A'.$row.':E'.$row)->getFont()->setBold(true);
                $sheet->getStyle('A'.$row.':E'.$row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8EEF5');
                $row++;
                $sheet->setCellValue('A'.$row, 'Дата');
                $sheet->setCellValue('B'.$row, 'Сумма оплаты');
                $sheet->setCellValue('C'.$row, 'Дата');
                $sheet->setCellValue('D'.$row, 'Документ');
                $sheet->setCellValue('E'.$row, 'Сумма');
                $headerRow = $row;
                $row++;
                for ($i = 0; $i < $pairRows; $i++) {
                    if (isset($buyerPayV[$i])) {
                        $sheet->setCellValue('A'.$row, $buyerPayV[$i]['date']->format('d.m.Y'));
                        $sheet->setCellValue('B'.$row, $this->excelMoney($buyerPayV[$i]['credit']));
                    }
                    if (isset($buyerDocV[$i])) {
                        $d = $buyerDocV[$i];
                        $sheet->setCellValue('C'.$row, $d['date']->format('d.m.Y'));
                        $docLabel = $d['title'].($d['detail'] !== '' && ($d['kind'] ?? '') !== 'opening_card' ? ' · '.$d['detail'] : '');
                        $sheet->setCellValue('D'.$row, $docLabel);
                        if (($d['kind'] ?? '') === 'return') {
                            $sheet->setCellValue('E'.$row, $this->excelMoneyNeg($d['credit']));
                        } else {
                            $sheet->setCellValue('E'.$row, $this->excelMoney($d['debit']));
                        }
                    }
                    $row++;
                }
            } else {
                $supPayV = $data['supplierPaymentsList']->values();
                $supDocV = $data['supplierDocs']->values();
                $pairRows = max($supPayV->count(), $supDocV->count());
                $sheet->setCellValue('A'.$row, 'Оплатили');
                $sheet->mergeCells('A'.$row.':B'.$row);
                $sheet->setCellValue('C'.$row, 'Закупки');
                $sheet->mergeCells('C'.$row.':E'.$row);
                $sheet->getStyle('A'.$row.':E'.$row)->getFont()->setBold(true);
                $sheet->getStyle('A'.$row.':E'.$row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8EEF5');
                $row++;
                $sheet->setCellValue('A'.$row, 'Дата');
                $sheet->setCellValue('B'.$row, 'Сумма оплаты');
                $sheet->setCellValue('C'.$row, 'Дата');
                $sheet->setCellValue('D'.$row, 'Документ');
                $sheet->setCellValue('E'.$row, 'Сумма');
                $headerRow = $row;
                $row++;
                for ($i = 0; $i < $pairRows; $i++) {
                    if (isset($supPayV[$i])) {
                        $sheet->setCellValue('A'.$row, $supPayV[$i]['date']->format('d.m.Y'));
                        $sheet->setCellValue('B'.$row, $this->excelMoney($supPayV[$i]['debit']));
                    }
                    if (isset($supDocV[$i])) {
                        $d = $supDocV[$i];
                        $sheet->setCellValue('C'.$row, $d['date']->format('d.m.Y'));
                        $docLabel = $d['title'].($d['detail'] !== '' && ($d['kind'] ?? '') !== 'opening_card' ? ' · '.$d['detail'] : '');
                        $sheet->setCellValue('D'.$row, $docLabel);
                        if (($d['kind'] ?? '') === 'purchase_return') {
                            $sheet->setCellValue('E'.$row, $this->excelMoneyNeg($d['debit']));
                        } else {
                            $sheet->setCellValue('E'.$row, $this->excelMoney($d['credit']));
                        }
                    }
                    $row++;
                }
            }
            $this->applySheetBorders($sheet, $headerRow, $row - 1);
            $row += 2;
            $sheet->setCellValue('A'.$row, 'Итог');
            $sheet->mergeCells('A'.$row.':E'.$row);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);
            $row++;
            if ($isBuyers) {
                $sheet->setCellValue('A'.$row, 'Сальдо на '.$data['from']->format('d.m.Y'));
                $sheet->setCellValue('E'.$row, $this->excelMoney($data['buyerOpening']));
                $row++;
                $sheet->setCellValue('A'.$row, 'Долг нам на '.$data['to']->format('d.m.Y'));
                $sheet->setCellValue('E'.$row, $this->excelMoney($data['buyerClosing']));
                $sheet->getStyle('A'.$row.':E'.$row)->getFont()->setBold(true);
            } else {
                $sheet->setCellValue('A'.$row, 'Сальдо на '.$data['from']->format('d.m.Y'));
                $sheet->setCellValue('E'.$row, $this->excelMoney($data['supplierOpening']));
                $row++;
                $sheet->setCellValue('A'.$row, 'Мы должны на '.$data['to']->format('d.m.Y'));
                $sheet->setCellValue('E'.$row, $this->excelMoney($data['supplierClosing']));
                $sheet->getStyle('A'.$row.':E'.$row)->getFont()->setBold(true);
            }
        }

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    private function excelMoney(?string $v): float
    {
        if ($v === null || $v === '') {
            return 0.0;
        }

        return (float) $v;
    }

    private function excelMoneyNeg(?string $v): float
    {
        return -1 * abs($this->excelMoney($v));
    }

    private function applySheetBorders(Worksheet $sheet, int $headerRow, int $lastRow): void
    {
        if ($lastRow < $headerRow) {
            return;
        }
        $style = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
        ];
        $sheet->getStyle('A'.$headerRow.':E'.$lastRow)->applyFromArray($style);
        $sheet->getStyle('A'.$headerRow.':E'.$headerRow)->getFont()->setBold(true);
        if ($lastRow > $headerRow) {
            $sheet->getStyle('B'.($headerRow + 1).':E'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }

    /**
     * @return array{
     *     buyerRows: Collection,
     *     supplierRows: Collection,
     *     buyerOpening: string,
     *     buyerClosing: string,
     *     supplierOpening: string,
     *     supplierClosing: string,
     *     paidIncomePeriod: string,
     *     paidExpensePeriod: string,
     * }
     */
    private function reconciliationForCounterparty(int $branchId, Counterparty $counterparty, Carbon $from, Carbon $to): array
    {
        $buyerAliases = $counterparty->legalSaleBuyerNameAliases();
        $supplierAliases = $counterparty->supplierNameAliases();

        $buyerOpening = $this->buyerOpeningBalance($branchId, $counterparty, $buyerAliases, $from);
        $supplierOpening = $this->supplierOpeningBalance($branchId, $counterparty, $supplierAliases, $from);

        $buyerRows = $this->buyerPeriodRows($branchId, $counterparty->id, $buyerAliases, $from, $to);
        $supplierRows = $this->supplierPeriodRows($branchId, $counterparty->id, $supplierAliases, $from, $to);

        $buyerClosing = bcadd($buyerOpening, $this->sumBuyerDelta($buyerRows), 2);
        $supplierClosing = bcadd($supplierOpening, $this->sumSupplierDelta($supplierRows), 2);

        $paidIncomePeriod = $this->sumIncomeClientInPeriod($branchId, $counterparty->id, $from, $to);
        $paidExpensePeriod = $this->sumExpenseSupplierInPeriod($branchId, $counterparty->id, $from, $to);

        $buyerSalesPeriod = $this->sumBuyerSalesInPeriod($branchId, $buyerAliases, $from, $to);
        $buyerReturnsPeriod = $this->sumBuyerReturnsInPeriod($branchId, $buyerAliases, $from, $to);

        $supplierPurchasesGross = $this->sumSupplierPurchasesInPeriod($branchId, $supplierAliases, $from, $to);
        $supplierReturnsPeriod = $this->sumSupplierReturnsInPeriod($branchId, $supplierAliases, $from, $to);

        $buyerSaleCount = $this->countBuyerSalesInPeriod($branchId, $buyerAliases, $from, $to);
        $buyerReturnCount = $this->countBuyerReturnsInPeriod($branchId, $buyerAliases, $from, $to);
        $buyerAvgSale = $buyerSaleCount > 0
            ? bcdiv($buyerSalesPeriod, (string) $buyerSaleCount, 2)
            : '0';

        $supplierPurchaseCount = $this->countSupplierPurchasesInPeriod($branchId, $supplierAliases, $from, $to);
        $supplierReturnCount = $this->countSupplierReturnsInPeriod($branchId, $supplierAliases, $from, $to);
        $supplierAvgPurchase = $supplierPurchaseCount > 0
            ? bcdiv($supplierPurchasesGross, (string) $supplierPurchaseCount, 2)
            : '0';

        return [
            'buyerRows' => $buyerRows,
            'supplierRows' => $supplierRows,
            'buyerOpening' => $buyerOpening,
            'buyerClosing' => $buyerClosing,
            'supplierOpening' => $supplierOpening,
            'supplierClosing' => $supplierClosing,
            'paidIncomePeriod' => $paidIncomePeriod,
            'paidExpensePeriod' => $paidExpensePeriod,
            'buyerPeriodPurchasesNet' => bcsub($buyerSalesPeriod, $buyerReturnsPeriod, 2),
            'buyerSalesPeriod' => $buyerSalesPeriod,
            'buyerReturnsPeriod' => $buyerReturnsPeriod,
            'buyerSaleCount' => $buyerSaleCount,
            'buyerReturnCount' => $buyerReturnCount,
            'buyerAvgSale' => $buyerAvgSale,
            'supplierPurchasesPeriod' => bcsub($supplierPurchasesGross, $supplierReturnsPeriod, 2),
            'supplierPurchasesGross' => $supplierPurchasesGross,
            'supplierReturnsPeriod' => $supplierReturnsPeriod,
            'supplierPurchaseCount' => $supplierPurchaseCount,
            'supplierReturnCount' => $supplierReturnCount,
            'supplierAvgPurchase' => $supplierAvgPurchase,
        ];
    }

    private function normalizeMode(mixed $mode): string
    {
        $m = is_string($mode) ? $mode : '';
        if ($m === 'they_owe' || $m === self::MODE_BUYERS) {
            return self::MODE_BUYERS;
        }
        if ($m === 'we_owe' || $m === self::MODE_SELLERS) {
            return self::MODE_SELLERS;
        }

        return self::MODE_BUYERS;
    }

    /**
     * Список контрагентов для вкладки: только покупатель или только поставщик (тип «Прочее» не показывается).
     *
     * @return Collection<int, Counterparty>
     */
    private function counterpartiesForReconciliationMode(int $branchId, string $mode): Collection
    {
        $q = Counterparty::query()
            ->where('branch_id', $branchId)
            ->orderBy('full_name')
            ->orderBy('name');

        if ($mode === self::MODE_BUYERS) {
            $q->where('kind', Counterparty::KIND_BUYER);
        } else {
            $q->where('kind', Counterparty::KIND_SUPPLIER);
        }

        return $q->get();
    }

    private function counterpartyMatchesMode(Counterparty $counterparty, string $mode): bool
    {
        if ($mode === self::MODE_BUYERS) {
            return $counterparty->kind === Counterparty::KIND_BUYER;
        }

        return $counterparty->kind === Counterparty::KIND_SUPPLIER;
    }

    private function sumBuyerSalesInPeriod(int $branchId, array $buyerAliases, Carbon $from, Carbon $to): string
    {
        if ($buyerAliases === []) {
            return '0';
        }

        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();
        $total = '0';
        $sales = LegalEntitySale::query()
            ->where('branch_id', $branchId)
            ->whereIn('buyer_name', $buyerAliases)
            ->whereBetween('document_date', [$fromStr, $toStr])
            ->with('lines')
            ->get();

        foreach ($sales as $sale) {
            $total = bcadd($total, $this->sumLines($sale->lines), 2);
        }

        return $total;
    }

    private function sumBuyerReturnsInPeriod(int $branchId, array $buyerAliases, Carbon $from, Carbon $to): string
    {
        if ($buyerAliases === []) {
            return '0';
        }

        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();
        $total = '0';
        $returns = CustomerReturn::query()
            ->where('branch_id', $branchId)
            ->whereIn('buyer_name', $buyerAliases)
            ->whereBetween('document_date', [$fromStr, $toStr])
            ->with('lines')
            ->get();

        foreach ($returns as $ret) {
            $total = bcadd($total, $this->sumLines($ret->lines), 2);
        }

        return $total;
    }

    private function sumSupplierPurchasesInPeriod(int $branchId, array $supplierAliases, Carbon $from, Carbon $to): string
    {
        if ($supplierAliases === []) {
            return '0';
        }

        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();
        $total = '0';
        $docs = PurchaseReceipt::query()
            ->where('branch_id', $branchId)
            ->whereIn('supplier_name', $supplierAliases)
            ->whereBetween('document_date', [$fromStr, $toStr])
            ->with('lines')
            ->get();

        foreach ($docs as $doc) {
            $total = bcadd($total, $this->sumLines($doc->lines), 2);
        }

        return $total;
    }

    private function sumSupplierReturnsInPeriod(int $branchId, array $supplierAliases, Carbon $from, Carbon $to): string
    {
        if ($supplierAliases === []) {
            return '0';
        }

        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();
        $total = '0';
        $docs = PurchaseReturn::query()
            ->where('branch_id', $branchId)
            ->whereIn('supplier_name', $supplierAliases)
            ->whereBetween('document_date', [$fromStr, $toStr])
            ->with('lines')
            ->get();

        foreach ($docs as $doc) {
            $total = bcadd($total, $this->sumLines($doc->lines), 2);
        }

        return $total;
    }

    /** Число документов с ненулевой суммой строк (как в таблице сверки). */
    private function countBuyerSalesInPeriod(int $branchId, array $buyerAliases, Carbon $from, Carbon $to): int
    {
        if ($buyerAliases === []) {
            return 0;
        }

        $sales = LegalEntitySale::query()
            ->where('branch_id', $branchId)
            ->whereIn('buyer_name', $buyerAliases)
            ->whereBetween('document_date', [$from->toDateString(), $to->toDateString()])
            ->with('lines')
            ->get();

        $n = 0;
        foreach ($sales as $sale) {
            if (bccomp($this->sumLines($sale->lines), '0', 2) !== 0) {
                $n++;
            }
        }

        return $n;
    }

    private function countBuyerReturnsInPeriod(int $branchId, array $buyerAliases, Carbon $from, Carbon $to): int
    {
        if ($buyerAliases === []) {
            return 0;
        }

        $returns = CustomerReturn::query()
            ->where('branch_id', $branchId)
            ->whereIn('buyer_name', $buyerAliases)
            ->whereBetween('document_date', [$from->toDateString(), $to->toDateString()])
            ->with('lines')
            ->get();

        $n = 0;
        foreach ($returns as $ret) {
            if (bccomp($this->sumLines($ret->lines), '0', 2) !== 0) {
                $n++;
            }
        }

        return $n;
    }

    private function countSupplierPurchasesInPeriod(int $branchId, array $supplierAliases, Carbon $from, Carbon $to): int
    {
        if ($supplierAliases === []) {
            return 0;
        }

        $docs = PurchaseReceipt::query()
            ->where('branch_id', $branchId)
            ->whereIn('supplier_name', $supplierAliases)
            ->whereBetween('document_date', [$from->toDateString(), $to->toDateString()])
            ->with('lines')
            ->get();

        $n = 0;
        foreach ($docs as $doc) {
            if (bccomp($this->sumLines($doc->lines), '0', 2) !== 0) {
                $n++;
            }
        }

        return $n;
    }

    private function countSupplierReturnsInPeriod(int $branchId, array $supplierAliases, Carbon $from, Carbon $to): int
    {
        if ($supplierAliases === []) {
            return 0;
        }

        $docs = PurchaseReturn::query()
            ->where('branch_id', $branchId)
            ->whereIn('supplier_name', $supplierAliases)
            ->whereBetween('document_date', [$from->toDateString(), $to->toDateString()])
            ->with('lines')
            ->get();

        $n = 0;
        foreach ($docs as $doc) {
            if (bccomp($this->sumLines($doc->lines), '0', 2) !== 0) {
                $n++;
            }
        }

        return $n;
    }

    private function sumIncomeClientInPeriod(int $branchId, int $counterpartyId, Carbon $from, Carbon $to): string
    {
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();
        $sum = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('counterparty_id', $counterpartyId)
            ->where('kind', CashMovement::KIND_INCOME_CLIENT)
            ->whereBetween('occurred_on', [$fromStr, $toStr])
            ->sum('amount');

        return number_format((float) $sum, 2, '.', '');
    }

    private function sumExpenseSupplierInPeriod(int $branchId, int $counterpartyId, Carbon $from, Carbon $to): string
    {
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();
        $sum = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('counterparty_id', $counterpartyId)
            ->where('kind', CashMovement::KIND_EXPENSE_SUPPLIER)
            ->whereBetween('occurred_on', [$fromStr, $toStr])
            ->sum('amount');

        return number_format((float) $sum, 2, '.', '');
    }

    /**
     * Дебиторка: начальный долг из карточки + реализации − оплаты − возвраты до даты (сальдо «нам должны»).
     */
    private function buyerOpeningBalance(int $branchId, Counterparty $counterparty, array $buyerAliases, Carbon $from): string
    {
        $carry = $this->openingDebtString($counterparty->opening_debt_as_buyer);
        if ($buyerAliases === []) {
            return $carry;
        }

        $before = $from->toDateString();

        $sales = $this->sumLegalSalesBefore($branchId, $buyerAliases, $before);
        $payments = $this->sumIncomeClientBefore($branchId, $counterparty->id, $before);
        $returns = $this->sumCustomerReturnsBefore($branchId, $buyerAliases, $before);

        $fromDocs = bcsub(bcsub($sales, $payments, 2), $returns, 2);

        return bcadd($carry, $fromDocs, 2);
    }

    /**
     * Кредиторка: начальный долг из карточки + закупки − возвраты поставщику − оплаты до даты (сальдо «мы должны»).
     */
    private function supplierOpeningBalance(int $branchId, Counterparty $counterparty, array $supplierAliases, Carbon $from): string
    {
        $carry = $this->openingDebtString($counterparty->opening_debt_as_supplier);
        if ($supplierAliases === []) {
            return $carry;
        }

        $before = $from->toDateString();

        $purchases = $this->sumPurchasesBefore($branchId, $supplierAliases, $before);
        $returns = $this->sumPurchaseReturnsBefore($branchId, $supplierAliases, $before);
        $payments = $this->sumExpenseSupplierBefore($branchId, $counterparty->id, $before);

        $fromDocs = bcsub(bcsub($purchases, $returns, 2), $payments, 2);

        return bcadd($carry, $fromDocs, 2);
    }

    private function openingDebtString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function sumLegalSalesBefore(int $branchId, array $aliases, string $beforeDate): string
    {
        $total = '0';
        $sales = LegalEntitySale::query()
            ->where('branch_id', $branchId)
            ->whereIn('buyer_name', $aliases)
            ->where('document_date', '<', $beforeDate)
            ->with('lines')
            ->get();

        foreach ($sales as $sale) {
            $total = bcadd($total, $this->sumLines($sale->lines), 2);
        }

        return $total;
    }

    private function sumIncomeClientBefore(int $branchId, int $counterpartyId, string $beforeDate): string
    {
        $sum = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('counterparty_id', $counterpartyId)
            ->where('kind', CashMovement::KIND_INCOME_CLIENT)
            ->where('occurred_on', '<', $beforeDate)
            ->sum('amount');

        return number_format((float) $sum, 2, '.', '');
    }

    private function sumCustomerReturnsBefore(int $branchId, array $aliases, string $beforeDate): string
    {
        $total = '0';
        $returns = CustomerReturn::query()
            ->where('branch_id', $branchId)
            ->whereIn('buyer_name', $aliases)
            ->where('document_date', '<', $beforeDate)
            ->with('lines')
            ->get();

        foreach ($returns as $ret) {
            $total = bcadd($total, $this->sumLines($ret->lines), 2);
        }

        return $total;
    }

    private function sumPurchasesBefore(int $branchId, array $aliases, string $beforeDate): string
    {
        $total = '0';
        $docs = PurchaseReceipt::query()
            ->where('branch_id', $branchId)
            ->whereIn('supplier_name', $aliases)
            ->where('document_date', '<', $beforeDate)
            ->with('lines')
            ->get();

        foreach ($docs as $doc) {
            $total = bcadd($total, $this->sumLines($doc->lines), 2);
        }

        return $total;
    }

    private function sumPurchaseReturnsBefore(int $branchId, array $aliases, string $beforeDate): string
    {
        $total = '0';
        $docs = PurchaseReturn::query()
            ->where('branch_id', $branchId)
            ->whereIn('supplier_name', $aliases)
            ->where('document_date', '<', $beforeDate)
            ->with('lines')
            ->get();

        foreach ($docs as $doc) {
            $total = bcadd($total, $this->sumLines($doc->lines), 2);
        }

        return $total;
    }

    private function sumExpenseSupplierBefore(int $branchId, int $counterpartyId, string $beforeDate): string
    {
        $sum = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('counterparty_id', $counterpartyId)
            ->where('kind', CashMovement::KIND_EXPENSE_SUPPLIER)
            ->where('occurred_on', '<', $beforeDate)
            ->sum('amount');

        return number_format((float) $sum, 2, '.', '');
    }

    /**
     * @param  iterable<int, LegalEntitySaleLine|CustomerReturnLine|PurchaseReceiptLine|PurchaseReturnLine>  $lines
     */
    private function sumLines(iterable $lines): string
    {
        $t = '0';
        foreach ($lines as $line) {
            if ($line->line_sum !== null) {
                $t = bcadd($t, (string) $line->line_sum, 2);
            }
        }

        return $t;
    }

    private function buyerPeriodRows(int $branchId, int $counterpartyId, array $buyerAliases, Carbon $from, Carbon $to): Collection
    {
        if ($buyerAliases === []) {
            return collect();
        }

        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        $rows = collect();

        $sales = LegalEntitySale::query()
            ->where('branch_id', $branchId)
            ->whereIn('buyer_name', $buyerAliases)
            ->whereBetween('document_date', [$fromStr, $toStr])
            ->with('lines')
            ->orderBy('document_date')
            ->orderBy('id')
            ->get();

        foreach ($sales as $sale) {
            $amt = $this->sumLines($sale->lines);
            if (bccomp($amt, '0', 2) === 0) {
                continue;
            }
            $rows->push([
                'sort' => $sale->document_date->format('Y-m-d').'-1-'.$sale->id,
                'date' => $sale->document_date,
                'kind' => 'sale',
                'title' => 'Продажа',
                'detail' => 'Документ № '.$sale->id,
                'debit' => $amt,
                'credit' => null,
            ]);
        }

        $returns = CustomerReturn::query()
            ->where('branch_id', $branchId)
            ->whereIn('buyer_name', $buyerAliases)
            ->whereBetween('document_date', [$fromStr, $toStr])
            ->with('lines')
            ->orderBy('document_date')
            ->orderBy('id')
            ->get();

        foreach ($returns as $ret) {
            $amt = $this->sumLines($ret->lines);
            if (bccomp($amt, '0', 2) === 0) {
                continue;
            }
            $rows->push([
                'sort' => $ret->document_date->format('Y-m-d').'-2-'.$ret->id,
                'date' => $ret->document_date,
                'kind' => 'return',
                'title' => 'Возврат',
                'detail' => 'Документ № '.$ret->id,
                'debit' => null,
                'credit' => $amt,
            ]);
        }

        $payments = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('counterparty_id', $counterpartyId)
            ->where('kind', CashMovement::KIND_INCOME_CLIENT)
            ->whereBetween('occurred_on', [$fromStr, $toStr])
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();

        foreach ($payments as $p) {
            $amt = number_format((float) $p->amount, 2, '.', '');
            $rows->push([
                'sort' => $p->occurred_on->format('Y-m-d').'-3-'.$p->id,
                'date' => $p->occurred_on,
                'kind' => 'payment',
                'title' => 'Перевод денег',
                'detail' => trim((string) $p->comment) !== '' ? $p->comment : '—',
                'debit' => null,
                'credit' => $amt,
            ]);
        }

        return $rows->sortBy('sort')->values();
    }

    private function supplierPeriodRows(int $branchId, int $counterpartyId, array $supplierAliases, Carbon $from, Carbon $to): Collection
    {
        if ($supplierAliases === []) {
            return collect();
        }

        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        $rows = collect();

        $purchases = PurchaseReceipt::query()
            ->where('branch_id', $branchId)
            ->whereIn('supplier_name', $supplierAliases)
            ->whereBetween('document_date', [$fromStr, $toStr])
            ->with('lines')
            ->orderBy('document_date')
            ->orderBy('id')
            ->get();

        foreach ($purchases as $doc) {
            $amt = $this->sumLines($doc->lines);
            if (bccomp($amt, '0', 2) === 0) {
                continue;
            }
            $rows->push([
                'sort' => $doc->document_date->format('Y-m-d').'-1-'.$doc->id,
                'date' => $doc->document_date,
                'kind' => 'purchase',
                'title' => 'Закуп у поставщика',
                'detail' => 'Документ № '.$doc->id,
                'debit' => null,
                'credit' => $amt,
            ]);
        }

        $purchaseReturns = PurchaseReturn::query()
            ->where('branch_id', $branchId)
            ->whereIn('supplier_name', $supplierAliases)
            ->whereBetween('document_date', [$fromStr, $toStr])
            ->with('lines')
            ->orderBy('document_date')
            ->orderBy('id')
            ->get();

        foreach ($purchaseReturns as $ret) {
            $amt = $this->sumLines($ret->lines);
            if (bccomp($amt, '0', 2) === 0) {
                continue;
            }
            $rows->push([
                'sort' => $ret->document_date->format('Y-m-d').'-2-'.$ret->id,
                'date' => $ret->document_date,
                'kind' => 'purchase_return',
                'title' => 'Возврат поставщику',
                'detail' => 'Документ № '.$ret->id,
                'debit' => $amt,
                'credit' => null,
            ]);
        }

        $payments = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('counterparty_id', $counterpartyId)
            ->where('kind', CashMovement::KIND_EXPENSE_SUPPLIER)
            ->whereBetween('occurred_on', [$fromStr, $toStr])
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();

        foreach ($payments as $p) {
            $amt = number_format((float) $p->amount, 2, '.', '');
            $rows->push([
                'sort' => $p->occurred_on->format('Y-m-d').'-3-'.$p->id,
                'date' => $p->occurred_on,
                'kind' => 'payment',
                'title' => 'Перевод денег поставщику',
                'detail' => trim((string) $p->comment) !== '' ? $p->comment : '—',
                'debit' => $amt,
                'credit' => null,
            ]);
        }

        return $rows->sortBy('sort')->values();
    }

    private function sumBuyerDelta(Collection $rows): string
    {
        $t = '0';
        foreach ($rows as $r) {
            if ($r['debit'] !== null) {
                $t = bcadd($t, $r['debit'], 2);
            }
            if ($r['credit'] !== null) {
                $t = bcsub($t, $r['credit'], 2);
            }
        }

        return $t;
    }

    private function sumSupplierDelta(Collection $rows): string
    {
        $t = '0';
        foreach ($rows as $r) {
            if ($r['credit'] !== null) {
                $t = bcadd($t, $r['credit'], 2);
            }
            if ($r['debit'] !== null) {
                $t = bcsub($t, $r['debit'], 2);
            }
        }

        return $t;
    }
}
