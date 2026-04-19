<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RequiresOpenCashShift;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRetailCheckoutDraftRequest;
use App\Http\Requests\StoreRetailDebtGroupPaymentRequest;
use App\Http\Requests\StoreRetailDebtPaymentRequest;
use App\Http\Requests\StoreRetailSaleCheckoutRequest;
use App\Http\Requests\StoreRetailSaleReturnRequest;
use App\Http\Requests\UpdateRetailSaleRequest;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnLine;
use App\Models\Good;
use App\Models\OpeningStockBalance;
use App\Models\OrganizationBankAccount;
use App\Models\RetailSale;
use App\Models\RetailSaleLine;
use App\Models\RetailSalePayment;
use App\Models\RetailSaleRefund;
use App\Models\Warehouse;
use App\Services\OpeningBalanceService;
use App\Support\InvoiceNakladnayaFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class RetailSaleController extends Controller
{
    use RequiresOpenCashShift;

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

        $defaultWarehouseId = $warehouses->firstWhere('is_default')?->id ?? $warehouses->first()?->id;

        $fromQuery = (int) request()->integer('warehouse_id') ?: 0;
        $selectedWarehouseId = 0;
        $oldWarehouseId = old('warehouse_id');
        if ($oldWarehouseId !== null && $oldWarehouseId !== '') {
            $ow = (int) $oldWarehouseId;
            if ($ow > 0 && $warehouses->contains('id', $ow)) {
                $selectedWarehouseId = $ow;
            }
        }
        if ($selectedWarehouseId === 0 && $fromQuery > 0 && $warehouses->contains('id', $fromQuery)) {
            $selectedWarehouseId = $fromQuery;
        }
        if ($selectedWarehouseId === 0 || ! $warehouses->contains('id', $selectedWarehouseId)) {
            $selectedWarehouseId = (int) ($defaultWarehouseId ?? 0);
        }
        if ($warehouses->isNotEmpty() && $selectedWarehouseId <= 0) {
            $selectedWarehouseId = (int) $warehouses->first()->id;
        }

        $linesOld = old('lines');
        $whForCart = $selectedWarehouseId > 0 ? $selectedWarehouseId : null;
        $initialCart = $this->buildPosCartFromLineInput(
            $branchId,
            is_array($linesOld) ? $linesOld : [],
            $whForCart
        );

        $paymentAccounts = OrganizationBankAccount::query()
            ->whereHas('organization', fn ($q) => $q->where('branch_id', $branchId))
            ->with('organization:id,name,sort_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->sortBy(function (OrganizationBankAccount $a) {
                $org = $a->organization;

                return [
                    $org?->sort_order ?? 0,
                    $org?->name ?? '',
                    $a->sort_order,
                    $a->id,
                ];
            })
            ->values();

        $paymentAccountsPayload = $paymentAccounts->map(fn (OrganizationBankAccount $a) => [
            'id' => $a->id,
            'label' => $a->labelWithoutAccountNumber(),
            'organization' => $a->organization?->name ?? '—',
            'type' => $a->account_type,
        ])->all();

        $defaultAccountId = $paymentAccounts->firstWhere('is_default', true)?->id
            ?? $paymentAccounts->firstWhere('account_type', OrganizationBankAccount::TYPE_CASH)?->id
            ?? $paymentAccounts->first()?->id;

        return view('admin.retail-sales.pos', [
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'paymentAccountsPayload' => $paymentAccountsPayload,
            'defaultAccountId' => $defaultAccountId,
            'goodsSearchUrl' => route('admin.goods.search'),
            'defaultDocumentDate' => now()->toDateString(),
            'checkoutDraftUrl' => route('admin.retail-sales.checkout-draft'),
            'initialCart' => $initialCart,
        ]);
    }

    public function saveCheckoutDraft(StoreRetailCheckoutDraftRequest $request): RedirectResponse
    {
        $request->session()->put('retail_checkout_draft', [
            'warehouse_id' => (int) $request->validated('warehouse_id'),
            'document_date' => (string) $request->validated('document_date'),
            'lines' => $request->input('lines', []),
        ]);

        return redirect()->route('admin.retail-sales.checkout');
    }

    public function checkout(Request $request): View|RedirectResponse
    {
        $draft = $request->session()->get('retail_checkout_draft');
        if (! is_array($draft) || ! is_array($draft['lines'] ?? null)) {
            return redirect()
                ->route('admin.retail-sales.index')
                ->withErrors(['checkout' => 'Сначала добавьте позиции в корзину и нажмите «К оплате».']);
        }

        $branchId = (int) auth()->user()->branch_id;

        $paymentAccounts = OrganizationBankAccount::query()
            ->whereHas('organization', fn ($q) => $q->where('branch_id', $branchId))
            ->with('organization:id,name,sort_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->sortBy(function (OrganizationBankAccount $a) {
                $org = $a->organization;

                return [
                    $org?->sort_order ?? 0,
                    $org?->name ?? '',
                    $a->sort_order,
                    $a->id,
                ];
            })
            ->values();

        $paymentAccountsPayload = $paymentAccounts->map(fn (OrganizationBankAccount $a) => [
            'id' => $a->id,
            'label' => $a->labelWithoutAccountNumber(),
            'organization' => $a->organization?->name ?? '—',
            'type' => $a->account_type,
        ])->all();

        $defaultAccountId = $paymentAccounts->firstWhere('is_default', true)?->id
            ?? $paymentAccounts->firstWhere('account_type', OrganizationBankAccount::TYPE_CASH)?->id
            ?? $paymentAccounts->first()?->id;

        $warehouseId = (int) ($draft['warehouse_id'] ?? 0);
        $documentDate = (string) ($draft['document_date'] ?? now()->toDateString());
        $lines = is_array($draft['lines'] ?? null) ? $draft['lines'] : [];
        $draftTotal = $this->computeDraftLinesTotal($branchId, $warehouseId, $lines);

        return view('admin.retail-sales.checkout', [
            'paymentAccountsPayload' => $paymentAccountsPayload,
            'defaultAccountId' => $defaultAccountId,
            'warehouseId' => $warehouseId,
            'documentDate' => $documentDate,
            'draftTotal' => $draftTotal,
            'storeUrl' => route('admin.retail-sales.store'),
            'posUrl' => route('admin.retail-sales.index', array_filter(['warehouse_id' => $warehouseId], static fn ($v) => (int) $v > 0)),
            'debtorHintsUrl' => route('admin.retail-sales.debtor-hints'),
            'counterpartySearchUrl' => route('admin.counterparties.search', ['for' => 'sale']),
        ]);
    }

    /**
     * Подсказки ФИО/телефона для долга: прошлые розничные долги филиала + (на клиенте) справочник покупателей.
     */
    public function debtorHints(Request $request): JsonResponse
    {
        $branchId = auth()->user()->branch_id;
        if ($branchId === null) {
            return response()->json([]);
        }

        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $like = '%'.$term.'%';

        $rows = RetailSale::query()
            ->where('branch_id', (int) $branchId)
            ->whereNotNull('debtor_name')
            ->where('debtor_name', '!=', '')
            ->where(function ($q) use ($like) {
                $q->where('debtor_name', 'like', $like)
                    ->orWhere('debtor_phone', 'like', $like);
            })
            ->orderByDesc('id')
            ->limit(40)
            ->get(['id', 'debtor_name', 'debtor_phone']);

        $seen = [];
        $out = [];
        foreach ($rows as $r) {
            $name = trim((string) $r->debtor_name);
            if ($name === '') {
                continue;
            }
            $phone = trim((string) ($r->debtor_phone ?? ''));
            $key = mb_strtolower($name, 'UTF-8').'|'.$phone;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'debtor_name' => $name,
                'debtor_phone' => $phone,
            ];
            if (count($out) >= 18) {
                break;
            }
        }

        return response()->json($out);
    }

    /**
     * Сумма черновика для экрана оплаты (без проведения).
     */
    private function computeDraftLinesTotal(int $branchId, int $warehouseId, array $lines): string
    {
        $total = '0';
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
            $price = $this->openingBalanceService->parseOptionalMoney($line['unit_price'] ?? null);
            if ($qty === null || $price === null) {
                continue;
            }
            $lineSum = bcmul((string) $qty, (string) $price, 2);
            $total = bcadd($total, $lineSum, 2);
        }

        return $total;
    }

    public function debts(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;

        $limit = (int) $request->integer('limit') ?: 100;
        if (! in_array($limit, [50, 100, 200, 500], true)) {
            $limit = 100;
        }

        $paymentAccounts = OrganizationBankAccount::query()
            ->whereHas('organization', fn ($q) => $q->where('branch_id', $branchId))
            ->with('organization:id,name,sort_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->sortBy(function (OrganizationBankAccount $a) {
                $org = $a->organization;

                return [
                    $org?->sort_order ?? 0,
                    $org?->name ?? '',
                    $a->sort_order,
                    $a->id,
                ];
            })
            ->values();

        $paymentAccountsPayload = $paymentAccounts->map(fn (OrganizationBankAccount $a) => [
            'id' => $a->id,
            'label' => $a->labelWithoutAccountNumber(),
            'organization' => $a->organization?->name ?? '—',
            'type' => $a->account_type,
        ])->all();

        $defaultAccountId = $paymentAccounts->firstWhere('is_default', true)?->id
            ?? $paymentAccounts->firstWhere('account_type', OrganizationBankAccount::TYPE_CASH)?->id
            ?? $paymentAccounts->first()?->id;

        $sales = RetailSale::query()
            ->where('branch_id', $branchId)
            ->where('debt_amount', '>', 0)
            ->with(['warehouse', 'lines'])
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $debtGroups = $sales
            ->groupBy(fn (RetailSale $s) => $this->debtorGroupKey($s))
            ->sortByDesc(function ($group) {
                /** @var \Illuminate\Support\Collection<int, RetailSale> $group */
                return $group->max(fn (RetailSale $s) => $s->document_date !== null ? $s->document_date->getTimestamp() : 0);
            });

        $payUrls = $sales->mapWithKeys(fn (RetailSale $s) => [$s->id => route('admin.retail-sales.pay-debt', $s)])->all();

        return view('admin.retail-sales.debts', [
            'debtGroups' => $debtGroups,
            'paymentAccountsPayload' => $paymentAccountsPayload,
            'defaultAccountId' => (int) ($defaultAccountId ?? 0),
            'limit' => $limit,
            'payUrls' => $payUrls,
            'groupPayUrl' => route('admin.retail-sales.pay-debt-group'),
        ]);
    }

    public function payDebt(StoreRetailDebtPaymentRequest $request, RetailSale $retailSale): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;
        if ((int) $retailSale->branch_id !== $branchId) {
            abort(403);
        }

        if (bccomp((string) $retailSale->debt_amount, '0', 2) <= 0) {
            return $this->redirectToRetailDebts($request)
                ->withErrors(['pay' => 'По этой продаже нет долга для погашения.']);
        }

        $amountStr = $this->openingBalanceService->parseOptionalMoney($request->input('amount'));
        if ($amountStr === null || bccomp($amountStr, '0', 2) <= 0) {
            return $this->redirectToRetailDebts($request)
                ->withErrors(['pay' => 'Укажите сумму больше ноля.']);
        }

        if (bccomp($amountStr, (string) $retailSale->debt_amount, 2) > 0) {
            return $this->redirectToRetailDebts($request)
                ->withErrors(['pay' => 'Сумма не может превышать остаток долга по чеку № '.$retailSale->id.'.']);
        }

        $accountId = (int) $request->validated('organization_bank_account_id');

        try {
            DB::transaction(function () use ($retailSale, $amountStr, $accountId) {
                RetailSalePayment::query()->create([
                    'retail_sale_id' => $retailSale->id,
                    'organization_bank_account_id' => $accountId,
                    'amount' => $amountStr,
                ]);
                $retailSale->refresh();
                $retailSale->load('payments');
                $sumPaid = '0';
                foreach ($retailSale->payments as $p) {
                    $sumPaid = bcadd($sumPaid, (string) $p->amount, 2);
                }
                $newDebt = bcsub((string) $retailSale->total_amount, $sumPaid, 2);
                if (bccomp($newDebt, '0', 2) < 0) {
                    throw new RuntimeException('Сумма оплат превышает сумму чека.');
                }
                $retailSale->update(['debt_amount' => $newDebt]);
            });
        } catch (RuntimeException $e) {
            return $this->redirectToRetailDebts($request)
                ->withErrors(['pay' => $e->getMessage()]);
        }

        $statusMsg = 'Оплата по чеку № '.$retailSale->id.': '.InvoiceNakladnayaFormatter::formatMoney((float) $amountStr).' сом.';

        return $this->redirectToRetailDebts($request)->with('status', $statusMsg);
    }

    public function payDebtGroup(StoreRetailDebtGroupPaymentRequest $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;
        $ids = array_values(array_unique(array_map('intval', $request->validated('sale_ids'))));
        sort($ids);

        $amountStr = $this->openingBalanceService->parseOptionalMoney($request->input('amount'));
        if ($amountStr === null || bccomp($amountStr, '0', 2) <= 0) {
            return $this->redirectToRetailDebts($request)
                ->withErrors(['pay' => 'Укажите сумму больше ноля.']);
        }

        $accountId = (int) $request->validated('organization_bank_account_id');

        try {
            DB::transaction(function () use ($branchId, $ids, $amountStr, $accountId) {
                $sales = RetailSale::query()
                    ->where('branch_id', $branchId)
                    ->whereIn('id', $ids)
                    ->orderBy('document_date')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($sales->count() !== count($ids)) {
                    throw new RuntimeException('Не все указанные чеки найдены или доступны.');
                }

                if ($sales->isEmpty()) {
                    throw new RuntimeException('Нет чеков для оплаты.');
                }

                $firstKey = $this->debtorGroupKey($sales->first());
                foreach ($sales as $sale) {
                    if ($this->debtorGroupKey($sale) !== $firstKey) {
                        throw new RuntimeException('Чеки относятся к разным должникам. Обновите страницу.');
                    }
                }

                $sumDebt = '0';
                foreach ($sales as $sale) {
                    $sumDebt = bcadd($sumDebt, (string) $sale->debt_amount, 2);
                }

                if (bccomp($sumDebt, '0', 2) <= 0) {
                    throw new RuntimeException('По выбранным чекам нет долга.');
                }

                if (bccomp($amountStr, $sumDebt, 2) > 0) {
                    throw new RuntimeException('Сумма не может превышать суммарный долг по клиенту.');
                }

                $remaining = $amountStr;

                foreach ($sales as $sale) {
                    if (bccomp($remaining, '0', 2) <= 0) {
                        break;
                    }

                    $sale->refresh();
                    $currentDebt = (string) $sale->debt_amount;
                    if (bccomp($currentDebt, '0', 2) <= 0) {
                        continue;
                    }

                    $take = bccomp($remaining, $currentDebt, 2) <= 0 ? $remaining : $currentDebt;

                    RetailSalePayment::query()->create([
                        'retail_sale_id' => $sale->id,
                        'organization_bank_account_id' => $accountId,
                        'amount' => $take,
                    ]);

                    $sale->refresh();
                    $sale->load('payments');
                    $sumPaid = '0';
                    foreach ($sale->payments as $p) {
                        $sumPaid = bcadd($sumPaid, (string) $p->amount, 2);
                    }
                    $newDebt = bcsub((string) $sale->total_amount, $sumPaid, 2);
                    if (bccomp($newDebt, '0', 2) < 0) {
                        throw new RuntimeException('Ошибка расчёта остатка по чеку № '.$sale->id.'.');
                    }
                    $sale->update(['debt_amount' => $newDebt]);
                    $remaining = bcsub($remaining, $take, 2);
                }
            });
        } catch (RuntimeException $e) {
            return $this->redirectToRetailDebts($request)
                ->withErrors(['pay' => $e->getMessage()]);
        }

        $statusMsg = 'Единая оплата '.InvoiceNakladnayaFormatter::formatMoney((float) $amountStr).' сом распределена по чекам (сначала более ранние).';

        return $this->redirectToRetailDebts($request)->with('status', $statusMsg);
    }

    private function redirectToRetailDebts(Request $request): RedirectResponse
    {
        $lim = (int) $request->input('limit', 0);

        return redirect()->route('admin.retail-sales.debts', array_filter(
            ['limit' => $lim > 0 ? $lim : null],
            static fn ($v) => $v !== null && $v !== 0
        ));
    }

    private function debtorGroupKey(RetailSale $sale): string
    {
        $name = mb_strtolower(trim((string) ($sale->debtor_name ?? '')), 'UTF-8');
        $phone = preg_replace('/\D+/', '', (string) ($sale->debtor_phone ?? ''));

        return $name.'|'.$phone;
    }

    public function receipt(RetailSale $retailSale): View
    {
        $branchId = (int) auth()->user()->branch_id;
        if ((int) $retailSale->branch_id !== $branchId) {
            abort(403);
        }

        $retailSale->load(['lines', 'payments.organizationBankAccount', 'warehouse', 'user']);

        return view('admin.retail-sales.receipt', [
            'sale' => $retailSale,
        ]);
    }

    public function history(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $selectedWarehouseId = (int) $request->integer('warehouse_id') ?: 0;
        $defaultWarehouseId = $warehouses->firstWhere('is_default')?->id ?? $warehouses->first()?->id;
        if ($selectedWarehouseId === 0 || ! $warehouses->contains('id', $selectedWarehouseId)) {
            $selectedWarehouseId = (int) ($defaultWarehouseId ?? 0);
        }

        $limit = (int) $request->integer('limit') ?: 100;
        if (! in_array($limit, [50, 100, 200, 500], true)) {
            $limit = 100;
        }

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $dateFromNorm = is_string($dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1 ? $dateFrom : null;
        $dateToNorm = is_string($dateTo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1 ? $dateTo : null;

        $filterGoodId = (int) $request->integer('good_id');
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

        $sales = RetailSale::query()
            ->where('branch_id', $branchId)
            ->when($selectedWarehouseId > 0, fn ($q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->when($dateFromNorm !== null, fn ($q) => $q->whereDate('document_date', '>=', $dateFromNorm))
            ->when($dateToNorm !== null, fn ($q) => $q->whereDate('document_date', '<=', $dateToNorm))
            ->when($filterGoodId > 0, fn ($q) => $q->whereHas('lines', fn ($lq) => $lq->where('good_id', $filterGoodId)))
            ->with(['warehouse', 'organizationBankAccount.organization', 'payments.organizationBankAccount', 'lines'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $filterGoodSummary = $filterGood instanceof Good
            ? trim(
                (($c = trim((string) ($filterGood->article_code ?? ''))) !== '' ? $c.' · ' : '')
                .trim((string) ($filterGood->name ?? ''))
            )
            : '';

        [$paymentAccountsPayload, $defaultPaymentAccountId] = $this->branchPaymentAccountsPayloadAndDefault($branchId);

        return view('admin.retail-sales.history', [
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'limit' => $limit,
            'dateFrom' => $dateFromNorm,
            'dateTo' => $dateToNorm,
            'sales' => $sales,
            'filterGoodId' => $filterGoodId,
            'filterGoodSummary' => $filterGoodSummary,
            'paymentAccountsPayload' => $paymentAccountsPayload,
            'defaultPaymentAccountId' => $defaultPaymentAccountId,
        ]);
    }

    public function returnData(RetailSale $retailSale): JsonResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        if ((int) $retailSale->branch_id !== $branchId) {
            abort(403);
        }

        $retailSale->load('lines');

        $alreadyReturned = CustomerReturnLine::query()
            ->whereHas('customerReturn', fn ($q) => $q->where('retail_sale_id', $retailSale->id))
            ->whereNotNull('source_retail_sale_line_id')
            ->selectRaw('source_retail_sale_line_id, SUM(quantity) as sq')
            ->groupBy('source_retail_sale_line_id')
            ->pluck('sq', 'source_retail_sale_line_id');

        $lines = $retailSale->lines->map(function ($l) use ($alreadyReturned) {
            $sold = (string) $l->quantity;
            $prev = (string) ($alreadyReturned[$l->id] ?? '0');
            $avail = bcsub($sold, $prev, 4);

            return [
                'id' => $l->id,
                'article_code' => $l->article_code,
                'name' => $l->name,
                'unit' => $l->unit ?? 'шт.',
                'quantity_sold' => $sold,
                'quantity_available' => $avail,
                'unit_price' => (string) ($l->unit_price ?? '0'),
            ];
        })->values();

        [$accounts, $defaultAccountId] = $this->branchPaymentAccountsPayloadAndDefault($branchId);

        return response()->json([
            'sale' => [
                'id' => $retailSale->id,
                'document_date' => $retailSale->document_date->toDateString(),
            ],
            'lines' => $lines,
            'accounts' => $accounts,
            'defaultAccountId' => $defaultAccountId,
        ]);
    }

    public function returnFromSale(StoreRetailSaleReturnRequest $request, RetailSale $retailSale): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;
        if ((int) $retailSale->branch_id !== $branchId) {
            abort(403);
        }

        $validated = $request->validated();
        $accountId = (int) $validated['organization_bank_account_id'];
        $docDate = (string) $validated['document_date'];
        $lineInputs = $validated['lines'];

        $retailSale->load('lines');

        try {
            DB::transaction(function () use ($branchId, $retailSale, $lineInputs, $accountId, $docDate) {
                $buyer = trim((string) ($retailSale->debtor_name ?? ''));

                $doc = CustomerReturn::query()->create([
                    'branch_id' => $branchId,
                    'warehouse_id' => (int) $retailSale->warehouse_id,
                    'retail_sale_id' => $retailSale->id,
                    'buyer_name' => $buyer,
                    'document_date' => $docDate,
                ]);

                $totalRefund = '0';
                $warehouseId = (int) $retailSale->warehouse_id;

                foreach ($lineInputs as $row) {
                    $lineId = (int) $row['retail_sale_line_id'];
                    $saleLine = $retailSale->lines->firstWhere('id', $lineId);
                    if ($saleLine === null) {
                        throw new RuntimeException('Строка чека не найдена.');
                    }

                    $qty = $this->openingBalanceService->parseDecimal($row['quantity'] ?? null);
                    if ($qty === null || bccomp($qty, '0', 4) <= 0) {
                        continue;
                    }

                    $this->openingBalanceService->reverseOutboundSaleLine(
                        $branchId,
                        $warehouseId,
                        (int) $saleLine->good_id,
                        $qty
                    );

                    $priceStr = $this->openingBalanceService->parseOptionalMoney($saleLine->unit_price);
                    if ($priceStr === null && $saleLine->line_sum !== null && bccomp((string) $saleLine->quantity, '0', 4) > 0) {
                        $priceStr = bcdiv((string) $saleLine->line_sum, (string) $saleLine->quantity, 2);
                    }

                    $lineSum = $priceStr !== null ? bcmul($qty, $priceStr, 2) : null;
                    if ($lineSum !== null) {
                        $totalRefund = bcadd($totalRefund, $lineSum, 2);
                    }

                    CustomerReturnLine::query()->create([
                        'customer_return_id' => $doc->id,
                        'source_retail_sale_line_id' => $saleLine->id,
                        'good_id' => $saleLine->good_id,
                        'article_code' => $saleLine->article_code,
                        'name' => $saleLine->name,
                        'unit' => $saleLine->unit ?? 'шт.',
                        'quantity' => $qty,
                        'unit_price' => $priceStr,
                        'line_sum' => $lineSum,
                    ]);
                }

                if (bccomp($totalRefund, '0', 2) <= 0) {
                    throw new RuntimeException('Сумма возврата должна быть больше ноля.');
                }

                RetailSaleRefund::query()->create([
                    'customer_return_id' => $doc->id,
                    'retail_sale_id' => $retailSale->id,
                    'organization_bank_account_id' => $accountId,
                    'amount' => $totalRefund,
                ]);
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.retail-sales.history', $this->historyRedirectQuery($request))
                ->withErrors(['return' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.retail-sales.history', $this->historyRedirectQuery($request))
            ->with('status', 'Возврат по чеку № '.$retailSale->id.' проведён: товар вернули на склад, деньги списаны с выбранного счёта.');
    }

    public function edit(RetailSale $retailSale): View
    {
        $branchId = (int) auth()->user()->branch_id;

        $paymentAccounts = OrganizationBankAccount::query()
            ->whereHas('organization', fn ($q) => $q->where('branch_id', $branchId))
            ->with('organization:id,name,sort_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->sortBy(function (OrganizationBankAccount $a) {
                $org = $a->organization;

                return [
                    $org?->sort_order ?? 0,
                    $org?->name ?? '',
                    $a->sort_order,
                    $a->id,
                ];
            })
            ->values();

        $paymentAccountsPayload = $paymentAccounts->map(fn (OrganizationBankAccount $a) => [
            'id' => $a->id,
            'label' => $a->labelWithoutAccountNumber(),
            'organization' => $a->organization?->name ?? '—',
            'type' => $a->account_type,
        ])->all();

        $retailSale->load(['lines', 'warehouse']);

        $linesForJs = $this->retailCartLinesForJs($retailSale, $branchId, old('lines'));

        return view('admin.retail-sales.edit', [
            'retailSale' => $retailSale,
            'paymentAccountsPayload' => $paymentAccountsPayload,
            'goodsSearchUrl' => route('admin.goods.search'),
            'linesForJs' => $linesForJs,
        ]);
    }

    public function update(UpdateRetailSaleRequest $request, RetailSale $retailSale): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;
        $accountId = (int) $request->validated('organization_bank_account_id');
        $documentDate = (string) $request->validated('document_date');
        $lines = $request->input('lines', []);
        $warehouseId = (int) $retailSale->warehouse_id;

        try {
            DB::transaction(function () use ($retailSale, $branchId, $warehouseId, $accountId, $documentDate, $lines) {
                $sale = RetailSale::query()
                    ->whereKey($retailSale->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $oldLines = $sale->lines()->orderBy('id')->get();
                foreach ($oldLines as $oldLine) {
                    $this->openingBalanceService->reverseOutboundSaleLine(
                        $branchId,
                        $warehouseId,
                        (int) $oldLine->good_id,
                        $oldLine->quantity
                    );
                }

                RetailSaleLine::query()->where('retail_sale_id', $sale->id)->delete();

                $sale->update([
                    'organization_bank_account_id' => $accountId,
                    'document_date' => $documentDate,
                ]);

                $totalAmount = $this->appendLines($sale->fresh(), $branchId, $warehouseId, $lines);
                RetailSalePayment::query()->where('retail_sale_id', $sale->id)->delete();
                RetailSalePayment::query()->create([
                    'retail_sale_id' => $sale->id,
                    'organization_bank_account_id' => $accountId,
                    'amount' => $totalAmount,
                ]);
                $sale->update([
                    'total_amount' => $totalAmount,
                    'debt_amount' => '0.00',
                    'debtor_name' => null,
                    'debtor_phone' => null,
                    'debtor_comment' => null,
                ]);
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.retail-sales.history', array_filter([
                'warehouse_id' => $warehouseId,
            ], static fn ($v) => $v !== null && $v > 0))
            ->with('status', 'Продажа обновлена, остатки и сумма пересчитаны.');
    }

    public function destroy(Request $request, RetailSale $retailSale): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;
        if ((int) $retailSale->branch_id !== $branchId) {
            abort(403);
        }

        $warehouseId = (int) $retailSale->warehouse_id;

        try {
            DB::transaction(function () use ($retailSale, $branchId) {
                $sale = RetailSale::query()
                    ->whereKey($retailSale->id)
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

                RetailSaleLine::query()->where('retail_sale_id', $sale->id)->delete();
                $sale->delete();
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withErrors(['delete' => $e->getMessage()]);
        }

        $historyQuery = array_filter([
            'warehouse_id' => $request->integer('return_warehouse_id') ?: null,
            'limit' => $request->integer('return_limit') ?: null,
            'date_from' => $request->input('return_date_from'),
            'date_to' => $request->input('return_date_to'),
            'good_id' => $request->integer('return_good_id') ?: null,
        ], static fn ($v) => $v !== null && $v !== '');

        return redirect()
            ->route('admin.retail-sales.history', $historyQuery)
            ->with('status', 'Продажа удалена, остатки восстановлены.');
    }

    public function store(StoreRetailSaleCheckoutRequest $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $draft = $request->session()->get('retail_checkout_draft');
        if (! is_array($draft)) {
            return redirect()
                ->route('admin.retail-sales.index')
                ->withErrors(['checkout' => 'Сессия оформления истекла.']);
        }

        $branchId = (int) auth()->user()->branch_id;
        $warehouseId = (int) ($draft['warehouse_id'] ?? 0);
        $lines = $draft['lines'] ?? [];
        if (! is_array($lines)) {
            $lines = [];
        }

        $documentDate = (string) $request->validated('document_date');
        $paymentsInput = $request->input('payments', []);
        if (! is_array($paymentsInput)) {
            $paymentsInput = [];
        }

        $defaultAccountId = $this->defaultPaymentAccountId($branchId);

        try {
            $totalAmount = '0';
            $saleId = null;
            DB::transaction(function () use ($branchId, $warehouseId, $documentDate, $lines, $paymentsInput, $request, $defaultAccountId, &$totalAmount, &$saleId) {
                $primaryAccountId = $this->firstPositivePaymentAccountId($paymentsInput, $defaultAccountId);

                $sale = RetailSale::query()->create([
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'organization_bank_account_id' => $primaryAccountId,
                    'document_date' => $documentDate,
                    'user_id' => auth()->id(),
                    'total_amount' => '0.00',
                    'debt_amount' => '0.00',
                ]);

                $totalAmount = $this->appendLines($sale, $branchId, $warehouseId, $lines);
                $sumPaid = $this->replacePayments($sale, $paymentsInput);
                $debt = bcsub($totalAmount, $sumPaid, 2);

                $sale->update([
                    'total_amount' => $totalAmount,
                    'debt_amount' => $debt,
                    'debtor_name' => bccomp($debt, '0', 2) > 0 ? trim((string) $request->input('debtor_name')) : null,
                    'debtor_phone' => bccomp($debt, '0', 2) > 0 ? trim((string) $request->input('debtor_phone')) : null,
                    'debtor_comment' => bccomp($debt, '0', 2) > 0 ? trim((string) $request->input('debtor_comment')) : null,
                ]);
                $saleId = (int) $sale->id;
            });
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['lines' => $e->getMessage()]);
        }

        if ($saleId === null) {
            return redirect()
                ->route('admin.retail-sales.checkout')
                ->withErrors(['checkout' => 'Не удалось сохранить продажу. Повторите попытку.']);
        }

        $request->session()->forget('retail_checkout_draft');

        $sale = RetailSale::query()->findOrFail($saleId);

        $statusMsg = 'Продажа № '.$sale->id.' на сумму '.InvoiceNakladnayaFormatter::formatMoney((float) $totalAmount).' сом.';

        $action = $request->input('checkout_action', 'with_receipt');
        if ($action === 'without_receipt') {
            return redirect()
                ->route('admin.retail-sales.index', array_filter([
                    'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
                ], static fn ($v) => (int) $v > 0))
                ->with('status', $statusMsg.' Чек не открывался.');
        }

        return redirect()
            ->route('admin.retail-sales.receipt', $sale)
            ->with('status', $statusMsg);
    }

    /**
     * Корзина для POS из массива полей формы (article_code, quantity, unit_price).
     *
     * @param  list<mixed>  $linesInput
     * @return list<array{good_id: int, article_code: string, name: string, quantity: string, unit_price: string, stock_quantity: string|null, is_service: bool}>
     */
    private function buildPosCartFromLineInput(int $branchId, array $linesInput, ?int $warehouseId = null): array
    {
        $warehouseId = $warehouseId !== null && $warehouseId > 0 ? $warehouseId : null;

        $pairs = [];
        foreach ($linesInput as $line) {
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
            $pairs[] = ['line' => $line, 'good' => $good];
        }

        if ($pairs === []) {
            return [];
        }

        $stockByGoodId = [];
        if ($warehouseId !== null) {
            $goodIds = collect($pairs)->pluck('good.id')->map(fn ($id) => (int) $id)->unique()->values();
            $stockByGoodId = OpeningStockBalance::query()
                ->where('warehouse_id', $warehouseId)
                ->whereIn('good_id', $goodIds)
                ->get()
                ->mapWithKeys(fn (OpeningStockBalance $b) => [(int) $b->good_id => (string) $b->quantity])
                ->all();
        }

        $out = [];
        foreach ($pairs as $pair) {
            $line = $pair['line'];
            $good = $pair['good'];
            $code = trim((string) ($line['article_code'] ?? ''));
            $isService = (bool) $good->is_service;
            $stockQuantity = null;
            if (! $isService && $warehouseId !== null) {
                $gId = (int) $good->id;
                $stockQuantity = array_key_exists($gId, $stockByGoodId) ? $stockByGoodId[$gId] : '0';
            }
            $out[] = [
                'good_id' => (int) $good->id,
                'article_code' => $code,
                'name' => (string) $good->name,
                'quantity' => (string) ($line['quantity'] ?? '1'),
                'unit_price' => isset($line['unit_price']) && (string) $line['unit_price'] !== ''
                    ? (string) $line['unit_price']
                    : '',
                'stock_quantity' => $stockQuantity,
                'is_service' => $isService,
            ];
        }

        return $out;
    }

    /**
     * @param  list<mixed>|null  $oldLines
     * @return list<array{good_id: int, article_code: string, name: string, quantity: string, unit_price: string, stock_quantity: string|null, is_service: bool}>
     */
    private function retailCartLinesForJs(RetailSale $retailSale, int $branchId, ?array $oldLines): array
    {
        if (is_array($oldLines) && $oldLines !== []) {
            $ow = old('warehouse_id');
            $wid = null;
            if ($ow !== null && $ow !== '' && (int) $ow > 0) {
                $wid = (int) $ow;
            } elseif ((int) ($retailSale->warehouse_id ?? 0) > 0) {
                $wid = (int) $retailSale->warehouse_id;
            }
            $built = $this->buildPosCartFromLineInput($branchId, $oldLines, $wid);
            if ($built !== []) {
                return $built;
            }
        }

        $retailSale->loadMissing('lines');
        $warehouseId = (int) ($retailSale->warehouse_id ?? 0);
        $goodIds = $retailSale->lines->pluck('good_id')->unique()->filter()->values();
        $serviceFlags = $goodIds->isEmpty()
            ? collect()
            : Good::query()
                ->where('branch_id', $branchId)
                ->whereIn('id', $goodIds)
                ->pluck('is_service', 'id');

        $stockByGoodId = [];
        if ($warehouseId > 0 && $goodIds->isNotEmpty()) {
            $stockByGoodId = OpeningStockBalance::query()
                ->where('warehouse_id', $warehouseId)
                ->whereIn('good_id', $goodIds)
                ->get()
                ->mapWithKeys(fn (OpeningStockBalance $b) => [(int) $b->good_id => (string) $b->quantity])
                ->all();
        }

        return $retailSale->lines->map(function ($l) use ($serviceFlags, $warehouseId, $stockByGoodId) {
            $gid = (int) $l->good_id;
            $isService = (bool) ($serviceFlags[$gid] ?? false);
            $stockQuantity = null;
            if (! $isService && $warehouseId > 0) {
                $stockQuantity = $stockByGoodId[$gid] ?? '0';
            }

            return [
                'good_id' => $gid,
                'article_code' => (string) $l->article_code,
                'name' => (string) $l->name,
                'quantity' => (string) $l->quantity,
                'unit_price' => $l->unit_price !== null ? (string) $l->unit_price : '',
                'stock_quantity' => $stockQuantity,
                'is_service' => $isService,
            ];
        })->values()->all();
    }

    /**
     * @return string Total as decimal string
     */
    private function appendLines(RetailSale $sale, int $branchId, int $warehouseId, array $lines): string
    {
        $total = '0';
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
                $total = bcadd($total, $lineSum, 2);
            }

            RetailSaleLine::query()->create([
                'retail_sale_id' => $sale->id,
                'good_id' => $good->id,
                'article_code' => $code,
                'name' => $good->name,
                'unit' => $good->unit ?? 'шт.',
                'quantity' => $qty ?? '0',
                'unit_price' => $price,
                'line_sum' => $lineSum,
            ]);
        }

        return $total;
    }

    /**
     * @param  list<mixed>  $paymentsInput
     */
    private function replacePayments(RetailSale $sale, array $paymentsInput): string
    {
        RetailSalePayment::query()->where('retail_sale_id', $sale->id)->delete();
        $sum = '0';
        foreach ($paymentsInput as $row) {
            if (! is_array($row)) {
                continue;
            }
            $amt = $this->openingBalanceService->parseOptionalMoney($row['amount'] ?? null);
            if ($amt === null || bccomp($amt, '0', 2) <= 0) {
                continue;
            }
            $accId = (int) ($row['organization_bank_account_id'] ?? 0);
            if ($accId <= 0) {
                continue;
            }
            RetailSalePayment::query()->create([
                'retail_sale_id' => $sale->id,
                'organization_bank_account_id' => $accId,
                'amount' => $amt,
            ]);
            $sum = bcadd($sum, $amt, 2);
        }

        return $sum;
    }

    /**
     * @return array{0: list<array{id: int, label: string, organization: string, type: string}>, 1: int}
     */
    private function branchPaymentAccountsPayloadAndDefault(int $branchId): array
    {
        $paymentAccounts = OrganizationBankAccount::query()
            ->whereHas('organization', fn ($q) => $q->where('branch_id', $branchId))
            ->with('organization:id,name,sort_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->sortBy(function (OrganizationBankAccount $a) {
                $org = $a->organization;

                return [
                    $org?->sort_order ?? 0,
                    $org?->name ?? '',
                    $a->sort_order,
                    $a->id,
                ];
            })
            ->values();

        $payload = $paymentAccounts->map(fn (OrganizationBankAccount $a) => [
            'id' => $a->id,
            'label' => $a->labelWithoutAccountNumber(),
            'organization' => $a->organization?->name ?? '—',
            'type' => $a->account_type,
        ])->all();

        $defaultId = (int) ($paymentAccounts->firstWhere('is_default', true)?->id
            ?? $paymentAccounts->firstWhere('account_type', OrganizationBankAccount::TYPE_CASH)?->id
            ?? $paymentAccounts->first()?->id
            ?? 0);

        return [$payload, $defaultId];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function historyRedirectQuery(Request $request): array
    {
        return array_filter([
            'warehouse_id' => $request->integer('return_warehouse_id') ?: null,
            'limit' => $request->integer('return_limit') ?: null,
            'date_from' => $request->input('return_date_from'),
            'date_to' => $request->input('return_date_to'),
            'good_id' => $request->integer('return_good_id') ?: null,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    private function defaultPaymentAccountId(int $branchId): int
    {
        [, $id] = $this->branchPaymentAccountsPayloadAndDefault($branchId);

        return $id;
    }

    /**
     * @param  list<mixed>  $paymentsInput
     */
    private function firstPositivePaymentAccountId(array $paymentsInput, int $fallbackAccountId): int
    {
        foreach ($paymentsInput as $row) {
            if (! is_array($row)) {
                continue;
            }
            $amt = $this->openingBalanceService->parseOptionalMoney($row['amount'] ?? null);
            if ($amt === null || bccomp($amt, '0', 2) <= 0) {
                continue;
            }
            $accId = (int) ($row['organization_bank_account_id'] ?? 0);
            if ($accId > 0) {
                return $accId;
            }
        }

        return $fallbackAccountId > 0 ? $fallbackAccountId : $this->defaultPaymentAccountId((int) auth()->user()->branch_id);
    }
}
