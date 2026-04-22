<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalEntitySale;
use App\Models\Organization;
use App\Models\OrganizationBankAccount;
use App\Services\Esf\EsfXmlGenerator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EsfController extends Controller
{
    public function __construct(
        private readonly EsfXmlGenerator $esfXmlGenerator
    ) {}

    public function index(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $availableFilterError = null;
        if (($dateFrom && ! $dateTo) || (! $dateFrom && $dateTo)) {
            $availableFilterError = 'Укажите обе даты периода или оставьте поля пустыми (тогда показываются последние 50 документов).';
            $dateFrom = null;
            $dateTo = null;
        }
        if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
            $availableFilterError = 'Дата «с» не может быть позже даты «по».';
            $dateFrom = null;
            $dateTo = null;
        }

        $availableQuery = LegalEntitySale::query()
            ->where('branch_id', $branchId)
            ->with(['warehouse', 'lines.good']);

        if ($dateFrom && $dateTo) {
            $availableQuery
                ->whereDate('document_date', '>=', $dateFrom)
                ->whereDate('document_date', '<=', $dateTo)
                ->orderByDesc('document_date')
                ->orderByDesc('id')
                ->limit(500);
            $availableListHint = 'Период: с '.Carbon::parse($dateFrom)->format('d.m.Y').' по '.Carbon::parse($dateTo)->format('d.m.Y').', не более 500 строк.';
        } else {
            $availableQuery
                ->orderByDesc('document_date')
                ->orderByDesc('id')
                ->limit(50);
            $availableListHint = 'Без периода: последние 50 реализаций по дате документа.';
        }

        $takeLimit = ($dateFrom && $dateTo) ? 500 : 50;
        $salesAvailable = $availableQuery->get()
            ->filter(fn (LegalEntitySale $s) => $s->esfIsAvailableListCandidate())
            ->take($takeLimit)
            ->values();

        $salesInEsfWorkflow = LegalEntitySale::query()
            ->where('branch_id', $branchId)
            ->where(function ($q) {
                $q->where('esf_queue_goods', true)
                    ->orWhere('esf_queue_services', true)
                    ->orWhereNotNull('esf_submitted_goods_at')
                    ->orWhereNotNull('esf_submitted_services_at');
            })
            ->with(['warehouse', 'lines.good'])
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->get();

        $organizations = Organization::query()
            ->where('branch_id', $branchId)
            ->with(['bankAccounts' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $orgsPayload = $organizations->map(fn (Organization $o) => [
            'id' => $o->id,
            'name' => $o->name,
            'accounts' => $o->bankAccounts->map(fn (OrganizationBankAccount $a) => [
                'id' => $a->id,
                'type' => $a->account_type,
                'label' => $a->summaryLabel(),
            ])->values()->all(),
        ])->values()->all();

        $salesPending = LegalEntitySale::collectEsfPendingRows($salesInEsfWorkflow);
        $salesRecorded = LegalEntitySale::collectEsfRecordedRows($salesInEsfWorkflow);
        $esfTabDefault = $salesPending->isNotEmpty() ? 'pending' : 'recorded';

        $hasAnyLegalSales = LegalEntitySale::query()
            ->where('branch_id', $branchId)
            ->exists();

        return view('admin.esf.index', [
            'salesAvailable' => $salesAvailable,
            'salesPending' => $salesPending,
            'salesRecorded' => $salesRecorded,
            'hasAnyLegalSales' => $hasAnyLegalSales,
            'esfTabDefault' => $esfTabDefault,
            'organizations' => $organizations,
            'orgsPayload' => $orgsPayload,
            'availableFilterDateFrom' => $dateFrom,
            'availableFilterDateTo' => $dateTo,
            'availableFilterError' => $availableFilterError,
            'availableListHint' => $availableListHint,
            'esfFilter' => [
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
            ],
        ]);
    }

    public function queueBulk(Request $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;

        $request->validate([
            'queue_items' => ['required', 'array', 'min:1', 'max:200'],
            'queue_items.*' => ['string', 'regex:/^\d+:(goods|services)$/'],
            'date_from' => ['nullable', 'string'],
            'date_to' => ['nullable', 'string'],
        ]);

        $rawItems = array_values(array_unique($request->input('queue_items', [])));
        if ($rawItems === []) {
            return $this->redirectToEsfIndex($request)->with('error', 'Нечего отмечать: выберите товары и/или услуги в таблице.');
        }

        $affected = 0;
        $errors = [];

        DB::transaction(function () use ($rawItems, $branchId, &$affected, &$errors) {
            foreach ($rawItems as $raw) {
                [$id, $kind] = explode(':', $raw, 2);
                $id = (int) $id;
                if (! in_array($kind, ['goods', 'services'], true)) {
                    continue;
                }
                $sale = LegalEntitySale::query()
                    ->where('branch_id', $branchId)
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->first();
                if ($sale === null) {
                    $errors[] = 'Документ #'.$id.' не найден.';

                    continue;
                }
                $sale->load('lines.good');
                if (! $sale->esfCanQueueKind($kind)) {
                    $errors[] = 'Документ #'.$id.': нет позиций типа «'.($kind === 'goods' ? 'товары' : 'услуги').'».';

                    continue;
                }
                if ($sale->esfIsKindQueued($kind)) {
                    $errors[] = 'Документ #'.$id.': «'.($kind === 'goods' ? 'Товары' : 'Услуги').'» уже в очереди.';

                    continue;
                }
                if (($kind === 'goods' ? $sale->esf_submitted_goods_at : $sale->esf_submitted_services_at) !== null) {
                    $errors[] = 'Документ #'.$id.': эта часть уже отмечена как записанная.';

                    continue;
                }
                $sale->esfQueueSetForKind($kind, true);
                $sale->save();
                $affected++;
            }
        });

        if ($affected === 0) {
            $msg = $errors !== [] ? implode(' ', array_slice($errors, 0, 3)) : 'Ни одна отметка не применена.';

            return $this->redirectToEsfIndex($request)->with('error', $msg);
        }

        $status = 'В очередь на ЭСФ добавлено отметок: '.$affected.'.';
        if ($errors !== []) {
            $status .= ' (часть строк пропущена: см. уведомления).';
        }

        return $this->redirectToEsfIndex($request)->with('status', $status);
    }

    public function queueForEsf(Request $request, LegalEntitySale $legalEntitySale): RedirectResponse
    {
        if ((int) $legalEntitySale->branch_id !== (int) auth()->user()->branch_id) {
            abort(403);
        }

        $validated = $request->validate([
            'esf_lines' => ['required', Rule::in(['goods', 'services'])],
            'date_from' => ['nullable', 'string'],
            'date_to' => ['nullable', 'string'],
        ]);
        $kind = $validated['esf_lines'];

        $legalEntitySale->load('lines.good');
        if (! $legalEntitySale->esfCanQueueKind($kind)) {
            return $this->redirectToEsfIndex($request)->with(
                'error',
                'По этому документу нет позиций для «'.($kind === 'goods' ? 'товаров' : 'услуг').'».'
            );
        }
        if ($legalEntitySale->esfIsKindQueued($kind)) {
            return $this->redirectToEsfIndex($request)->with('status', 'Эта часть документа уже в очереди на ЭСФ.');
        }
        if (($kind === 'goods' ? $legalEntitySale->esf_submitted_goods_at : $legalEntitySale->esf_submitted_services_at) !== null) {
            return $this->redirectToEsfIndex($request)->with('error', 'Эта часть уже отмечена как записанная в ЭСФ.');
        }

        $legalEntitySale->esfQueueSetForKind($kind, true);
        $legalEntitySale->save();

        $label = $kind === 'goods' ? 'Товары' : 'Услуги';

        return $this->redirectToEsfIndex($request)->with(
            'status',
            'Документ № '.$legalEntitySale->id.': в очередь на ЭСФ добавлено — «'.$label.'». Перейдите на вкладку «Нужно записать».'
        );
    }

    public function unqueueFromEsf(Request $request, LegalEntitySale $legalEntitySale): RedirectResponse
    {
        if ((int) $legalEntitySale->branch_id !== (int) auth()->user()->branch_id) {
            abort(403);
        }

        $validated = $request->validate([
            'esf_lines' => ['required', Rule::in(['goods', 'services'])],
            'date_from' => ['nullable', 'string'],
            'date_to' => ['nullable', 'string'],
        ]);
        $kind = $validated['esf_lines'];

        if (! $legalEntitySale->esfIsKindQueued($kind)) {
            return $this->redirectToEsfIndex($request)->with('error', 'Эта часть не была в очереди на ЭСФ.');
        }

        if (($kind === 'goods' ? $legalEntitySale->esf_submitted_goods_at : $legalEntitySale->esf_submitted_services_at) !== null) {
            return $this->redirectToEsfIndex($request)->with(
                'error',
                'Нельзя убрать из очереди: эта ЭСФ уже отмечена как записанная. Сначала снимите отметку на вкладке «Записано в ЭСФ».'
            );
        }

        $legalEntitySale->esfQueueSetForKind($kind, false);
        if (! $legalEntitySale->esf_queue_goods && ! $legalEntitySale->esf_queue_services) {
            $legalEntitySale->esf_exchange_code = null;
        }
        $legalEntitySale->save();

        $label = $kind === 'goods' ? 'товары' : 'услуги';

        return $this->redirectToEsfIndex($request)->with(
            'status',
            'Документ № '.$legalEntitySale->id.': из очереди снята часть «'.$label.'».'
        );
    }

    public function unqueueFromEsfBulk(Request $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;

        $request->validate([
            'unqueue_items' => ['required', 'array', 'min:1', 'max:100'],
            'unqueue_items.*' => ['string', 'regex:/^\d+:(goods|services)$/'],
            'date_from' => ['nullable', 'string'],
            'date_to' => ['nullable', 'string'],
        ]);

        $raw = array_values(array_unique($request->input('unqueue_items', [])));
        if ($raw === []) {
            return $this->redirectToEsfIndex($request)->with('error', 'Ничего не выбрано.');
        }

        $done = 0;
        $notApplied = 0;

        DB::transaction(function () use ($branchId, $raw, &$done, &$notApplied) {
            foreach ($raw as $item) {
                $parts = explode(':', (string) $item, 2);
                if (count($parts) !== 2) {
                    $notApplied++;

                    continue;
                }
                $id = (int) $parts[0];
                $kind = $parts[1];
                if ($id < 1 || ! in_array($kind, ['goods', 'services'], true)) {
                    $notApplied++;

                    continue;
                }
                $sale = LegalEntitySale::query()
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->find($id);
                if ($sale === null) {
                    $notApplied++;

                    continue;
                }
                if (! $sale->esfIsKindQueued($kind)) {
                    $notApplied++;

                    continue;
                }
                if ($sale->esfSubmittedAtForKind($kind) !== null) {
                    $notApplied++;

                    continue;
                }
                $sale->esfQueueSetForKind($kind, false);
                if (! $sale->esf_queue_goods && ! $sale->esf_queue_services) {
                    $sale->esf_exchange_code = null;
                }
                $sale->save();
                $done++;
            }
        });

        if ($done === 0) {
            return $this->redirectToEsfIndex($request)->with(
                'error',
                'Не удалось снять с очереди: отметьте части, которые сейчас в очереди и без отметки «записано в ЭСФ», и повторите.'
            );
        }

        $msg = 'Снято с очереди: '.$done.' ч.';
        if ($notApplied > 0) {
            $msg .= ' Без изменений: '.$notApplied.' (уже снято, с отметкой в ЭСФ или не найдено).';
        }

        return $this->redirectToEsfIndex($request)->with('status', $msg);
    }

    public function downloadEsfLinesExcel(Request $request): StreamedResponse|RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;

        $request->validate([
            'excel_items' => ['required', 'array', 'min:1', 'max:200'],
            'excel_items.*' => ['string', 'regex:/^\d+:(goods|services)$/'],
        ]);

        $raw = array_values(array_unique($request->input('excel_items', [])));
        if ($raw === []) {
            return $this->redirectToEsfIndex($request)->with('error', 'Ничего не выбрано для выгрузки в Excel.');
        }

        $pairs = [];
        foreach ($raw as $s) {
            $parts = explode(':', (string) $s, 2);
            if (count($parts) !== 2) {
                return $this->redirectToEsfIndex($request)->with('error', 'Некорректный выбор строк.');

            }
            $pairs[] = ['id' => (int) $parts[0], 'kind' => $parts[1]];
        }

        $kinds = array_unique(array_column($pairs, 'kind'));
        if (count($kinds) !== 1) {
            return $this->redirectToEsfIndex($request)->with(
                'error',
                'В одном Excel нельзя смешивать товары и услуги. Отметьте только строки «Товары» или только «Услуги».'
            );
        }
        $linesKind = (string) $kinds[0];
        if (! in_array($linesKind, ['goods', 'services'], true)) {
            return $this->redirectToEsfIndex($request)->with('error', 'Некорректный вид строк.');

        }

        $idList = array_values(array_unique(array_map(fn (array $p) => $p['id'], $pairs)));
        sort($idList, SORT_NUMERIC);

        $rows = [];
        foreach ($idList as $id) {
            $sale = LegalEntitySale::query()
                ->where('branch_id', $branchId)
                ->whereKey($id)
                ->with('lines.good')
                ->first();
            if ($sale === null) {
                return $this->redirectToEsfIndex($request)->with('error', 'Документ № '.$id.' не найден в этой филиальной базе.');
            }
            foreach ($sale->lines as $line) {
                $g = $line->good;
                if ($g === null) {
                    continue;
                }
                if ($linesKind === 'goods' && $g->is_service) {
                    continue;
                }
                if ($linesKind === 'services' && ! $g->is_service) {
                    continue;
                }
                $name = $line->name !== null && (string) $line->name !== '' ? (string) $line->name : (string) $g->name;
                $unit = $line->unit !== null && (string) $line->unit !== '' ? (string) $line->unit : (string) ($g->unit ?? '');
                if ($name === '' && $unit === '') {
                    continue;
                }
                if ($name === '' && $unit !== '') {
                    $name = '—';
                }
                $rows[] = [
                    'name' => $name,
                    'unit' => $unit,
                ];
            }
        }

        if ($rows === []) {
            return $this->redirectToEsfIndex($request)->with('error', 'По отмеченным документам нет подходящих позиций для списка.');
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Наименование');
        $sheet->setCellValue('B1', 'Базовая единица измерения');
        if ($linesKind === 'goods') {
            $sheet->setCellValue('C1', 'ТНВЭД');
        } else {
            $sheet->setCellValue('C1', 'Код ГКЭД');
        }

        $r = 2;
        foreach ($rows as $row) {
            $sheet->setCellValue('A'.$r, $row['name']);
            $sheet->setCellValue('B'.$r, $row['unit']);
            $sheet->setCellValue('C'.$r, '');
            $r++;
        }

        $sheet->getColumnDimension('A')->setWidth(52);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('C')->setWidth(14);

        $typePart = $linesKind === 'goods' ? 'tovary' : 'uslugi';
        $fileName = 'esf_pozitsii_'.$typePart.'_'.date('Y-m-d_His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function redirectToEsfIndex(Request $request): RedirectResponse
    {
        $q = array_filter([
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ], static fn ($v) => $v !== null && $v !== '');

        return redirect()->route('admin.esf.index', $q);
    }

    public function downloadXml(Request $request, LegalEntitySale $legalEntitySale): Response|RedirectResponse
    {
        if ((int) $legalEntitySale->branch_id !== (int) auth()->user()->branch_id) {
            abort(403);
        }

        $branchId = (int) auth()->user()->branch_id;

        $validated = $request->validate([
            'organization_id' => [
                'required',
                'integer',
                Rule::exists('organizations', 'id')->where(fn ($q) => $q->where('branch_id', $branchId)),
            ],
            'payment_kind' => ['required', Rule::in(['cash', 'bank'])],
            'organization_bank_account_id' => ['nullable', 'integer'],
            'esf_lines' => ['required', Rule::in(['goods', 'services'])],
        ]);

        $legalEntitySale->load('lines.good');
        if (! $legalEntitySale->esfIsKindQueued($validated['esf_lines'])) {
            abort(404);
        }

        if ($legalEntitySale->esfSubmittedAtForKind($validated['esf_lines']) !== null) {
            return redirect()
                ->route('admin.esf.index')
                ->with('error', 'По этой части ЭСФ уже отмечена как записанная. Снимите отметку, если нужно сформировать файл заново.');
        }

        if (strlen($legalEntitySale->resolvedBuyerPinForEsf()) < 10) {
            return redirect()
                ->route('admin.esf.index')
                ->with(
                    'error',
                    'Не удалось определить ИНН/ПИН покупателя для XML (contractorPin). Укажите ИНН в карточке контрагента в «Контрагенты» и совпадающее наименование покупателя в реализации, либо введите ИНН в поле «ИНН/ПИН покупателя» в документе.'
                );
        }

        /** @var Organization $seller */
        $seller = Organization::query()
            ->where('branch_id', $branchId)
            ->whereKey($validated['organization_id'])
            ->firstOrFail();

        $paymentKind = $validated['payment_kind'];
        $accountId = $validated['organization_bank_account_id'] ?? null;

        $paymentAccount = $this->esfResolveOrganizationPaymentAccount(
            $seller,
            $paymentKind,
            $accountId !== null ? (int) $accountId : null,
            $request
        );
        if ($paymentAccount instanceof RedirectResponse) {
            return $paymentAccount;
        }

        if ($paymentKind === 'bank' && $legalEntitySale->resolvedBuyerBankAccountNumberForEsf() === null) {
            return redirect()
                ->route('admin.esf.index')
                ->with(
                    'error',
                    'Для безнала в XML нужен банковский счёт покупателя (поле contractorBankAccount). Добавьте счёт в карточке контрагента или выберите покупателя из подсказки, чтобы документ был связан с контрагентом.'
                );
        }

        $profile = $legalEntitySale->esfGoodsServicesLinesProfile();
        if (! $profile['has_goods'] && ! $profile['has_services']) {
            return redirect()
                ->route('admin.esf.index')
                ->with(
                    'error',
                    'В документе нет строк с привязкой к номенклатуре — выгрузка ЭСФ невозможна. Откройте реализацию и проверьте позиции.'
                );
        }

        $linesKind = $validated['esf_lines'];
        if ($linesKind === 'goods' && ! $profile['has_goods']) {
            return redirect()
                ->route('admin.esf.index')
                ->with('error', 'В документе нет товарных позиций для этой выгрузки.');
        }
        if ($linesKind === 'services' && ! $profile['has_services']) {
            return redirect()
                ->route('admin.esf.index')
                ->with('error', 'В документе нет услуг для этой выгрузки.');
        }
        $splitReceiptNoteAndCrm = $profile['mixed'];

        $randomExchange = (bool) config('esf.random_exchange_code_each_download', true);
        if ($randomExchange) {
            $exchangeCode = (string) Str::uuid();
        } else {
            if (blank($legalEntitySale->esf_exchange_code)) {
                $legalEntitySale->forceFill(['esf_exchange_code' => (string) Str::uuid()])->save();
                $legalEntitySale->refresh();
            }
            $exchangeCode = (string) $legalEntitySale->esf_exchange_code;
        }

        try {
            $xml = $this->esfXmlGenerator->build(
                $legalEntitySale,
                $seller,
                $paymentKind,
                $paymentAccount,
                $exchangeCode,
                $linesKind,
                $splitReceiptNoteAndCrm
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('admin.esf.index')
                ->with('error', $e->getMessage());
        }

        $fileSuffix = '';
        if ($splitReceiptNoteAndCrm) {
            $fileSuffix = $linesKind === 'goods' ? '_tovary' : '_uslugi';
        }

        $fileName = sprintf(
            'ESF_%d_%s%s.xml',
            $legalEntitySale->id,
            $legalEntitySale->document_date->format('Y-m-d'),
            $fileSuffix
        );

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    public function downloadXmlMerge(Request $request): Response|RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;

        $request->validate([
            'merge_items' => ['required', 'array', 'min:2', 'max:50'],
            'merge_items.*' => ['string', 'regex:/^\d+:(goods|services)$/'],
            'merge_org_ids' => ['required', 'array', 'min:2'],
            'merge_org_ids.*' => [
                'integer',
                Rule::exists('organizations', 'id')->where(fn ($q) => $q->where('branch_id', $branchId)),
            ],
            'organization_id' => [
                'required',
                'integer',
                Rule::exists('organizations', 'id')->where(fn ($q) => $q->where('branch_id', $branchId)),
            ],
            'payment_kind' => ['required', Rule::in(['cash', 'bank'])],
            'organization_bank_account_id' => ['nullable', 'integer'],
            'esf_lines' => ['required', Rule::in(['goods', 'services'])],
            'date_from' => ['nullable', 'string'],
            'date_to' => ['nullable', 'string'],
        ]);

        $raw = array_values(array_unique($request->input('merge_items', [])));
        $linesKind = (string) $request->input('esf_lines');
        if ($raw === [] || count($raw) < 2) {
            return $this->redirectToEsfIndex($request)->with('error', 'Отметьте не меньше двух строк с одинаковым видом (только товары или только услуги).');
        }

        $mergeOrgIds = $request->input('merge_org_ids', []);
        if (! is_array($mergeOrgIds) || count($mergeOrgIds) !== count($raw)) {
            return $this->redirectToEsfIndex($request)->with('error', 'Сбой передачи организаций по строкам. Обновите страницу и повторите выбор.');
        }
        $mergeOrgIds = array_map('intval', $mergeOrgIds);
        if (in_array(0, $mergeOrgIds, true)) {
            return $this->redirectToEsfIndex($request)->with('error', 'Для выбранных строк не указана организация (продавец).');
        }
        if (count(array_unique($mergeOrgIds)) > 1) {
            return $this->redirectToEsfIndex($request)->with(
                'error',
                'Объединение в один XML невозможно: в выбранных строках должна быть одна и та же организация-продавец.'
            );
        }
        if ((int) $mergeOrgIds[0] !== (int) $request->input('organization_id')) {
            return $this->redirectToEsfIndex($request)->with(
                'error',
                'Организация в заявке на объединение не совпадает с выбранной в строках таблицы.'
            );
        }

        $pairs = [];
        foreach ($raw as $s) {
            $parts = explode(':', (string) $s, 2);
            if (count($parts) !== 2) {
                return $this->redirectToEsfIndex($request)->with('error', 'Некорректный выбор строк для объединения.');

            }
            $pairs[] = ['id' => (int) $parts[0], 'kind' => $parts[1]];
        }
        $kinds = array_unique(array_column($pairs, 'kind'));
        if (count($kinds) !== 1 || ! in_array($linesKind, $kinds, true)) {
            return $this->redirectToEsfIndex($request)->with(
                'error',
                'В одном XML можно объединить только товары или только услуги — выберите строки одного вида (без смешивания).'
            );
        }

        /** @var Organization $seller */
        $seller = Organization::query()
            ->where('branch_id', $branchId)
            ->whereKey((int) $request->input('organization_id'))
            ->firstOrFail();

        $paymentKind = (string) $request->input('payment_kind');
        $accountId = $request->input('organization_bank_account_id') !== null && $request->input('organization_bank_account_id') !== ''
            ? (int) $request->input('organization_bank_account_id')
            : null;
        $paymentAccount = $this->esfResolveOrganizationPaymentAccount($seller, $paymentKind, $accountId, $request);

        if ($paymentAccount instanceof RedirectResponse) {
            return $paymentAccount;
        }

        $idList = array_values(array_unique(array_map(fn (array $p) => $p['id'], $pairs)));
        sort($idList, SORT_NUMERIC);

        $sales = [];
        foreach ($idList as $id) {
            $sale = LegalEntitySale::query()
                ->where('branch_id', $branchId)
                ->whereKey($id)
                ->with('lines.good')
                ->first();
            if ($sale === null) {
                return $this->redirectToEsfIndex($request)->with('error', 'Документ № '.$id.' не найден в этой филиальной базе.');
            }
            if (! $sale->esfIsKindQueued($linesKind)) {
                return $this->redirectToEsfIndex($request)->with('error', 'Документ № '.$id.' не в очереди на ЭСФ для выбранного вида (или снят из очереди).');
            }
            if ($sale->esfSubmittedAtForKind($linesKind) !== null) {
                return $this->redirectToEsfIndex($request)->with('error', 'По документу № '.$id.' эта часть уже отмечена как записанная — исключите её из выбора.');
            }
            if (strlen($sale->resolvedBuyerPinForEsf()) < 10) {
                return $this->redirectToEsfIndex($request)->with(
                    'error',
                    'Документ № '.$id.': не удаётся определить ИНН/ПИН покупателя (contractorPin) для XML.'
                );
            }
            if ($paymentKind === 'bank' && $sale->resolvedBuyerBankAccountNumberForEsf() === null) {
                return $this->redirectToEsfIndex($request)->with(
                    'error',
                    'Документ № '.$id.': для безнала в XML нужен банковский счёт покупателя (контрагент).'
                );
            }
            $p = $sale->esfGoodsServicesLinesProfile();
            if ($linesKind === 'goods' && ! $p['has_goods']) {
                return $this->redirectToEsfIndex($request)->with('error', 'Документ № '.$id.': в нём нет товарных позиций.');
            }
            if ($linesKind === 'services' && ! $p['has_services']) {
                return $this->redirectToEsfIndex($request)->with('error', 'Документ № '.$id.': в нём нет услуг.');
            }
            $sales[] = $sale;
        }

        if (count($sales) < 2) {
            return $this->redirectToEsfIndex($request)->with('error', 'После проверки осталось меньше двух подходящих документов.');
        }

        try {
            $xml = $this->esfXmlGenerator->buildMany(
                $sales,
                $seller,
                $paymentKind,
                $paymentAccount,
                $linesKind
            );
        } catch (\InvalidArgumentException $e) {
            return $this->redirectToEsfIndex($request)->with('error', $e->getMessage());
        }

        $idsPart = implode('-', array_map('strval', $idList));
        $typePart = $linesKind === 'goods' ? 'tovary' : 'uslugi';
        $fileName = 'ESF_merged_'.date('Y-m-d').'_'.$typePart.'_'.$idsPart.'.xml';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    private function esfResolveOrganizationPaymentAccount(
        Organization $seller,
        string $paymentKind,
        ?int $accountId,
        Request $request
    ): OrganizationBankAccount|RedirectResponse|null {
        $paymentAccount = null;
        if ($paymentKind === 'bank') {
            if ($accountId === null) {
                return $this->redirectToEsfIndex($request)->with('error', 'Для безнала выберите банковский счёт организации.');
            }
            $paymentAccount = OrganizationBankAccount::query()
                ->where('organization_id', $seller->id)
                ->whereKey($accountId)
                ->first();
            if ($paymentAccount === null) {
                return $this->redirectToEsfIndex($request)->with('error', 'Указанный счёт не принадлежит выбранной организации.');
            }
            if ($paymentAccount->isCash()) {
                return $this->redirectToEsfIndex($request)->with('error', 'Для безнала выберите банковский счёт (не «наличные»).');
            }
        }

        if ($paymentKind === 'cash' && $accountId !== null) {
            $paymentAccount = OrganizationBankAccount::query()
                ->where('organization_id', $seller->id)
                ->whereKey($accountId)
                ->first();
            if ($paymentAccount === null) {
                return $this->redirectToEsfIndex($request)->with('error', 'Указанный счёт не принадлежит выбранной организации.');
            }
            if (! $paymentAccount->isCash()) {
                return $this->redirectToEsfIndex($request)->with('error', 'Для наличных выберите счёт с типом «наличные» или оставьте счёт пустым.');
            }
        }

        return $paymentAccount;
    }

    public function markSubmitted(Request $request, LegalEntitySale $legalEntitySale): RedirectResponse
    {
        if ((int) $legalEntitySale->branch_id !== (int) auth()->user()->branch_id) {
            abort(403);
        }

        $validated = $request->validate([
            'esf_lines' => ['required', Rule::in(['goods', 'services'])],
            'date_from' => ['nullable', 'string'],
            'date_to' => ['nullable', 'string'],
        ]);
        $kind = $validated['esf_lines'];

        if (! $legalEntitySale->esfIsKindQueued($kind)) {
            abort(404);
        }

        if ($legalEntitySale->esfSubmittedAtForKind($kind) !== null) {
            return $this->redirectToEsfIndex($request)->with('status', 'Эта часть уже отмечена как записанная в ЭСФ.');
        }

        if ($kind === 'goods') {
            $legalEntitySale->esf_submitted_goods_at = now();
            $legalEntitySale->esf_queue_goods = false;
        } else {
            $legalEntitySale->esf_submitted_services_at = now();
            $legalEntitySale->esf_queue_services = false;
        }
        $legalEntitySale->save();

        $label = $kind === 'goods' ? 'Товары' : 'Услуги';

        return $this->redirectToEsfIndex($request)->with(
            'status',
            'Отмечено: «'.$label.'» — ЭСФ записана в налоговой (документ № '.$legalEntitySale->id.').'
        );
    }

    public function markSubmittedBulk(Request $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;

        $request->validate([
            'submitted_items' => ['required', 'array', 'min:1', 'max:100'],
            'submitted_items.*' => ['string', 'regex:/^\d+:(goods|services)$/'],
            'date_from' => ['nullable', 'string'],
            'date_to' => ['nullable', 'string'],
        ]);

        $raw = array_values(array_unique($request->input('submitted_items', [])));
        if ($raw === []) {
            return $this->redirectToEsfIndex($request)->with('error', 'Ничего не выбрано.');
        }

        $done = 0;
        $notApplied = 0;

        DB::transaction(function () use ($branchId, $raw, &$done, &$notApplied) {
            foreach ($raw as $item) {
                $parts = explode(':', (string) $item, 2);
                if (count($parts) !== 2) {
                    $notApplied++;

                    continue;
                }
                $id = (int) $parts[0];
                $kind = $parts[1];
                if ($id < 1 || ! in_array($kind, ['goods', 'services'], true)) {
                    $notApplied++;

                    continue;
                }
                $sale = LegalEntitySale::query()
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->find($id);
                if ($sale === null) {
                    $notApplied++;

                    continue;
                }
                if (! $sale->esfIsKindQueued($kind) || $sale->esfSubmittedAtForKind($kind) !== null) {
                    $notApplied++;

                    continue;
                }
                if ($kind === 'goods') {
                    $sale->esf_submitted_goods_at = now();
                    $sale->esf_queue_goods = false;
                } else {
                    $sale->esf_submitted_services_at = now();
                    $sale->esf_queue_services = false;
                }
                $sale->save();
                $done++;
            }
        });

        if ($done === 0) {
            return $this->redirectToEsfIndex($request)->with(
                'error',
                'Не удалось проставить отметку: выберите строки, которые сейчас в очереди и ещё без «записано в ЭСФ», и повторите.'
            );
        }

        $msg = 'Проставлена отметка «записано в ЭСФ» для выбранных частей: '.$done.'.';
        if ($notApplied > 0) {
            $msg .= ' Без изменений: '.$notApplied.' (уже с отметкой, не в очереди или не найдено).';
        }

        return $this->redirectToEsfIndex($request)->with('status', $msg);
    }

    public function unmarkSubmitted(Request $request, LegalEntitySale $legalEntitySale): RedirectResponse
    {
        if ((int) $legalEntitySale->branch_id !== (int) auth()->user()->branch_id) {
            abort(403);
        }

        $validated = $request->validate([
            'esf_lines' => ['required', Rule::in(['goods', 'services'])],
            'date_from' => ['nullable', 'string'],
            'date_to' => ['nullable', 'string'],
        ]);
        $kind = $validated['esf_lines'];

        if ($legalEntitySale->esfSubmittedAtForKind($kind) === null) {
            return $this->redirectToEsfIndex($request)->with('error', 'Для этой части не было отметки «записано в ЭСФ».');
        }

        if ($kind === 'goods') {
            $legalEntitySale->esf_submitted_goods_at = null;
            $legalEntitySale->esf_queue_goods = true;
        } else {
            $legalEntitySale->esf_submitted_services_at = null;
            $legalEntitySale->esf_queue_services = true;
        }
        if ($legalEntitySale->esf_submitted_goods_at === null && $legalEntitySale->esf_submitted_services_at === null) {
            $legalEntitySale->esf_exchange_code = null;
        }
        $legalEntitySale->save();

        return $this->redirectToEsfIndex($request)->with(
            'status',
            'Отметка «записано в ЭСФ» снята для выбранной части. При следующей выгрузке будет новый код обмена (exchangeCode).'
        );
    }
}
