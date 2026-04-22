<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CashShift;
use App\Models\OrganizationBankAccount;
use App\Models\RetailSale;
use App\Models\RetailSalePayment;
use App\Models\RetailSaleRefund;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CashLedgerService
{
    /**
     * @return EloquentCollection<int, OrganizationBankAccount>
     */
    public function accountsForBranch(int $branchId): EloquentCollection
    {
        return OrganizationBankAccount::query()
            ->whereHas('organization', fn ($q) => $q->where('branch_id', $branchId))
            ->with('organization')
            ->orderBy('organization_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function balanceAtDateExclusive(int $branchId, int $accountId, CarbonInterface $at): float
    {
        $acc = OrganizationBankAccount::query()
            ->where('id', $accountId)
            ->whereHas('organization', fn ($q) => $q->where('branch_id', $branchId))
            ->firstOrFail();

        $opening = (float) $acc->opening_balance;
        $dateStr = $at->copy()->startOfDay()->toDateString();

        $r = (float) (RetailSalePayment::query()
            ->whereHas('retailSale', function ($q) use ($branchId, $dateStr) {
                $q->where('branch_id', $branchId)
                    ->where('document_date', '<', $dateStr);
            })
            ->where('organization_bank_account_id', $accountId)
            ->sum('amount'));

        $retailRefundOut = (float) (RetailSaleRefund::query()
            ->join('customer_returns as cr', 'cr.id', '=', 'retail_sale_refunds.customer_return_id')
            ->where('cr.branch_id', $branchId)
            ->whereDate('cr.document_date', '<', $dateStr)
            ->where('retail_sale_refunds.organization_bank_account_id', $accountId)
            ->sum('retail_sale_refunds.amount'));

        $inc = (float) (CashMovement::query()
            ->where('branch_id', $branchId)
            ->whereIn('kind', [CashMovement::KIND_INCOME_CLIENT, CashMovement::KIND_INCOME_OTHER])
            ->where('our_account_id', $accountId)
            ->where('occurred_on', '<', $dateStr)
            ->sum('amount'));

        $outS = (float) (CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_EXPENSE_SUPPLIER)
            ->where('our_account_id', $accountId)
            ->where('occurred_on', '<', $dateStr)
            ->sum('amount'));

        $outO = (float) (CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_EXPENSE_OTHER)
            ->where('our_account_id', $accountId)
            ->where('occurred_on', '<', $dateStr)
            ->sum('amount'));

        $tOut = (float) (CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_TRANSFER)
            ->where('from_account_id', $accountId)
            ->where('occurred_on', '<', $dateStr)
            ->sum('amount'));

        $tIn = (float) (CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_TRANSFER)
            ->where('to_account_id', $accountId)
            ->where('occurred_on', '<', $dateStr)
            ->sum('amount'));

        return $opening + $r - $retailRefundOut + $inc + $tIn - $outS - $outO - $tOut;
    }

    /**
     * Единая лента операций (ручные + розница) за интервал дат включительно.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function historyRows(int $branchId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): Collection
    {
        $manual = CashMovement::query()
            ->where('branch_id', $branchId)
            ->with(['ourAccount', 'fromAccount', 'toAccount', 'counterparty'])
            ->when($from, fn ($q) => $q->whereDate('occurred_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('occurred_on', '<=', $to))
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();

        $retailPayments = RetailSalePayment::query()
            ->join('retail_sales as rs', 'rs.id', '=', 'retail_sale_payments.retail_sale_id')
            ->where('rs.branch_id', $branchId)
            ->when($from, fn ($q) => $q->whereDate('rs.document_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('rs.document_date', '<=', $to))
            ->orderByDesc('rs.document_date')
            ->orderByDesc('rs.id')
            ->orderByDesc('retail_sale_payments.id')
            ->select('retail_sale_payments.*')
            ->with(['retailSale', 'organizationBankAccount'])
            ->get();

        $rows = collect();

        foreach ($manual as $m) {
            $rows->push($this->rowFromCashMovement($m));
        }

        foreach ($retailPayments as $payment) {
            $sale = $payment->retailSale;
            if ($sale === null) {
                continue;
            }
            $acc = $payment->organizationBankAccount;
            $sortId = (int) ($sale->id * 100000 + $payment->id);
            $rows->push([
                'sort_at' => $sale->document_date->copy()->startOfDay()->timestamp,
                'sort_bucket' => 0,
                'sort_id' => $sortId,
                'date' => $sale->document_date->format('d.m.Y'),
                'title' => 'Розничная продажа (ФЛ)',
                'account_label' => $acc?->movementReportLabel() ?? '—',
                'in' => (float) $payment->amount,
                'out' => 0.0,
                'detail' => 'Документ № '.$sale->id,
                'source' => 'retail_sale_payment',
                'source_id' => $payment->id,
                'affected_account_ids' => $acc ? [$acc->id] : [],
                'delta_by_account' => $acc ? [$acc->id => (float) $payment->amount] : [],
            ]);
        }

        $retailRefunds = RetailSaleRefund::query()
            ->whereHas('customerReturn', function ($q) use ($branchId, $from, $to) {
                $q->where('branch_id', $branchId);
                if ($from) {
                    $q->whereDate('document_date', '>=', $from);
                }
                if ($to) {
                    $q->whereDate('document_date', '<=', $to);
                }
            })
            ->with(['customerReturn', 'retailSale', 'organizationBankAccount'])
            ->orderByDesc('id')
            ->get();

        foreach ($retailRefunds as $refund) {
            $cr = $refund->customerReturn;
            $sale = $refund->retailSale;
            $acc = $refund->organizationBankAccount;
            if ($cr === null || $sale === null) {
                continue;
            }
            $sortId = (int) (300000000000 + $refund->id);
            $amt = (float) $refund->amount;
            $rows->push([
                'sort_at' => $cr->document_date->copy()->startOfDay()->timestamp,
                'sort_bucket' => 0,
                'sort_id' => $sortId,
                'date' => $cr->document_date->format('d.m.Y'),
                'title' => 'Возврат покупателю (розница, ФЛ)',
                'account_label' => $acc?->movementReportLabel() ?? '—',
                'in' => 0.0,
                'out' => $amt,
                'detail' => 'Чек № '.$sale->id.' · возврат от клиента',
                'source' => 'retail_sale_refund',
                'source_id' => $refund->id,
                'affected_account_ids' => $acc ? [$acc->id] : [],
                'delta_by_account' => $acc ? [$acc->id => -$amt] : [],
            ]);
        }

        return $rows
            ->sort(function (array $a, array $b) {
                return [$b['sort_at'], $b['sort_bucket'] ?? 0, $b['sort_id'] ?? 0]
                    <=> [$a['sort_at'], $a['sort_bucket'] ?? 0, $a['sort_id'] ?? 0];
            })
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function rowFromCashMovement(CashMovement $m): array
    {
        $sortId = (int) $m->id;
        $in = 0.0;
        $out = 0.0;
        $detail = trim((string) $m->comment);
        $accountLabel = '—';
        $affected = [];
        $deltas = [];

        if ($m->kind === CashMovement::KIND_INCOME_CLIENT) {
            $in = (float) $m->amount;
            $accountLabel = $m->ourAccount?->movementReportLabel() ?? '—';
            if ($m->counterparty) {
                $detail = 'Клиент: '.$m->counterparty->name.($detail !== '' ? ' · '.$detail : '');
            }
            if ($m->our_account_id) {
                $affected[] = $m->our_account_id;
                $deltas[$m->our_account_id] = $in;
            }
            $title = 'Приход: оплата от клиента';
        } elseif ($m->kind === CashMovement::KIND_INCOME_OTHER) {
            $in = (float) $m->amount;
            $accountLabel = $m->ourAccount?->movementReportLabel() ?? '—';
            $cat = trim((string) $m->expense_category);
            if ($cat !== '') {
                $detail = $cat.($detail !== '' ? ' · '.$detail : '');
            }
            if ($m->our_account_id) {
                $affected[] = $m->our_account_id;
                $deltas[$m->our_account_id] = $in;
            }
            $title = 'Приход: прочие';
        } elseif ($m->kind === CashMovement::KIND_EXPENSE_SUPPLIER) {
            $out = (float) $m->amount;
            $accountLabel = $m->ourAccount?->movementReportLabel() ?? '—';
            if ($m->counterparty) {
                $detail = 'Поставщик: '.$m->counterparty->name.($detail !== '' ? ' · '.$detail : '');
            }
            if ($m->our_account_id) {
                $affected[] = $m->our_account_id;
                $deltas[$m->our_account_id] = -$out;
            }
            $title = 'Расход: оплата поставщику';
        } elseif ($m->kind === CashMovement::KIND_EXPENSE_OTHER) {
            $out = (float) $m->amount;
            $accountLabel = $m->ourAccount?->movementReportLabel() ?? '—';
            $cat = trim((string) $m->expense_category);
            if ($cat !== '') {
                $detail = $cat.($detail !== '' ? ' · '.$detail : '');
            }
            if ($m->our_account_id) {
                $affected[] = $m->our_account_id;
                $deltas[$m->our_account_id] = -$out;
            }
            $title = 'Расход: прочие';
        } else {
            $accountLabel = ($m->fromAccount?->movementReportLabel() ?? '—').' → '.($m->toAccount?->movementReportLabel() ?? '—');
            $detail = 'Перевод '.((string) $m->amount).' '.($m->fromAccount?->currency ?? '');
            $title = 'Перевод между счетами';
            if ($m->from_account_id) {
                $affected[] = $m->from_account_id;
                $deltas[$m->from_account_id] = -(float) $m->amount;
            }
            if ($m->to_account_id) {
                $affected[] = $m->to_account_id;
                $deltas[$m->to_account_id] = ((float) ($deltas[$m->to_account_id] ?? 0)) + (float) $m->amount;
            }
        }

        return [
            'sort_at' => $m->occurred_on->copy()->startOfDay()->timestamp,
            'sort_bucket' => 1,
            'sort_id' => $sortId,
            'date' => $m->occurred_on->format('d.m.Y'),
            'title' => $title,
            'account_label' => $accountLabel,
            'in' => $in,
            'out' => $out,
            'detail' => $detail !== '' ? $detail : '—',
            'source' => 'cash_movement',
            'source_id' => $m->id,
            'affected_account_ids' => $affected,
            'delta_by_account' => $deltas,
        ];
    }

    public function defaultMovementPeriod(): array
    {
        $to = Carbon::now()->startOfDay();
        $from = $to->copy()->startOfMonth();

        return [$from, $to];
    }

    /**
     * Сводка движения по дням: приход (клиент, прочий приход, розница), расход поставщику, прочие расходы, переводы между счетами.
     *
     * @return array{rows: Collection<int, array{date: string, date_sort: string, income: float, expense_supplier: float, expense_other: float, transfer: float}>, totals: array{income: float, expense_supplier: float, expense_other: float, transfer: float}}
     */
    public function movementDailyByKind(int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');

        $bucket = [];

        $ensure = function (string $d) use (&$bucket): void {
            if (! isset($bucket[$d])) {
                $bucket[$d] = [
                    'income' => 0.0,
                    'expense_supplier' => 0.0,
                    'expense_other' => 0.0,
                    'transfer' => 0.0,
                ];
            }
        };

        $manual = CashMovement::query()
            ->where('branch_id', $branchId)
            ->whereDate('occurred_on', '>=', $fromStr)
            ->whereDate('occurred_on', '<=', $toStr)
            ->get();

        foreach ($manual as $m) {
            $d = $m->occurred_on->format('Y-m-d');
            $ensure($d);
            $amt = (float) $m->amount;

            if ($m->kind === CashMovement::KIND_INCOME_CLIENT) {
                $bucket[$d]['income'] += $amt;
            } elseif ($m->kind === CashMovement::KIND_INCOME_OTHER) {
                $bucket[$d]['income'] += $amt;
            } elseif ($m->kind === CashMovement::KIND_EXPENSE_SUPPLIER) {
                $bucket[$d]['expense_supplier'] += $amt;
            } elseif ($m->kind === CashMovement::KIND_EXPENSE_OTHER) {
                $bucket[$d]['expense_other'] += $amt;
            } elseif ($m->kind === CashMovement::KIND_TRANSFER) {
                $bucket[$d]['transfer'] += $amt;
            }
        }

        $retailPayments = RetailSalePayment::query()
            ->whereHas('retailSale', function ($q) use ($branchId, $fromStr, $toStr) {
                $q->where('branch_id', $branchId)
                    ->whereDate('document_date', '>=', $fromStr)
                    ->whereDate('document_date', '<=', $toStr);
            })
            ->with('retailSale')
            ->get();

        foreach ($retailPayments as $payment) {
            $sale = $payment->retailSale;
            if ($sale === null) {
                continue;
            }
            $d = $sale->document_date->format('Y-m-d');
            $ensure($d);
            $bucket[$d]['income'] += (float) $payment->amount;
        }

        $refundSums = RetailSaleRefund::query()
            ->join('customer_returns as cr', 'cr.id', '=', 'retail_sale_refunds.customer_return_id')
            ->where('cr.branch_id', $branchId)
            ->whereDate('cr.document_date', '>=', $fromStr)
            ->whereDate('cr.document_date', '<=', $toStr)
            ->groupBy('cr.document_date')
            ->selectRaw('cr.document_date as d, SUM(retail_sale_refunds.amount) as s')
            ->get();

        foreach ($refundSums as $rs) {
            $d = $rs->d instanceof \Carbon\CarbonInterface ? $rs->d->format('Y-m-d') : (string) $rs->d;
            $ensure($d);
            $bucket[$d]['income'] -= (float) $rs->s;
        }

        ksort($bucket);

        $rows = collect();
        $totals = [
            'income' => 0.0,
            'expense_supplier' => 0.0,
            'expense_other' => 0.0,
            'transfer' => 0.0,
        ];

        foreach ($bucket as $dateSort => $v) {
            $rows->push([
                'date' => Carbon::parse($dateSort)->format('d.m.Y'),
                'date_sort' => $dateSort,
                'income' => round($v['income'], 2),
                'expense_supplier' => round($v['expense_supplier'], 2),
                'expense_other' => round($v['expense_other'], 2),
                'transfer' => round($v['transfer'], 2),
            ]);
            $totals['income'] += $v['income'];
            $totals['expense_supplier'] += $v['expense_supplier'];
            $totals['expense_other'] += $v['expense_other'];
            $totals['transfer'] += $v['transfer'];
        }

        foreach ($totals as $k => $v) {
            $totals[$k] = round($v, 2);
        }

        return [
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /**
     * Изменение остатка по счёту за интервал смены [from, until] (по created_at у розницы и cash_movements).
     * Учитывает: розницу (ФЛ), приход от клиента, расходы поставщику/прочие, переводы между счетами.
     *
     * Если задан $cashierUserId — только операции этого пользователя (смена привязана к кассиру).
     *
     * @return array<int, float> organization_bank_account_id => чистое изменение (приход на счёт положительный)
     */
    public function netCashChangeByAccountInShiftWindow(int $branchId, CarbonInterface $from, ?CarbonInterface $until = null, ?int $cashierUserId = null): array
    {
        $until ??= now();
        $net = [];

        $add = function (int $accountId, float $delta) use (&$net): void {
            if ($accountId <= 0 || abs($delta) < 1e-9) {
                return;
            }
            $net[$accountId] = round(($net[$accountId] ?? 0.0) + $delta, 2);
        };

        $retailRows = RetailSalePayment::query()
            ->join('retail_sales as rs', 'rs.id', '=', 'retail_sale_payments.retail_sale_id')
            ->where('rs.branch_id', $branchId)
            ->where('rs.created_at', '>=', $from)
            ->where('rs.created_at', '<=', $until)
            ->when($cashierUserId !== null, fn ($q) => $q->where('rs.user_id', $cashierUserId))
            ->selectRaw('retail_sale_payments.organization_bank_account_id as aid, SUM(retail_sale_payments.amount) as s')
            ->groupBy('retail_sale_payments.organization_bank_account_id')
            ->get();

        foreach ($retailRows as $row) {
            $add((int) $row->aid, (float) $row->s);
        }

        $refundShift = RetailSaleRefund::query()
            ->join('customer_returns as cr', 'cr.id', '=', 'retail_sale_refunds.customer_return_id')
            ->where('cr.branch_id', $branchId)
            ->where('retail_sale_refunds.created_at', '>=', $from)
            ->where('retail_sale_refunds.created_at', '<=', $until)
            ->when($cashierUserId !== null, fn ($q) => $q->whereHas('retailSale', fn ($rq) => $rq->where('user_id', $cashierUserId)))
            ->selectRaw('retail_sale_refunds.organization_bank_account_id as aid, SUM(retail_sale_refunds.amount) as s')
            ->groupBy('retail_sale_refunds.organization_bank_account_id')
            ->get();

        foreach ($refundShift as $row) {
            $add((int) $row->aid, -(float) $row->s);
        }

        $movements = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $until)
            ->when($cashierUserId !== null, fn ($q) => $q->where('user_id', $cashierUserId))
            ->get();

        foreach ($movements as $m) {
            if ($m->kind === CashMovement::KIND_INCOME_CLIENT || $m->kind === CashMovement::KIND_INCOME_OTHER) {
                if ($m->our_account_id) {
                    $add((int) $m->our_account_id, (float) $m->amount);
                }

                continue;
            }
            if ($m->kind === CashMovement::KIND_EXPENSE_SUPPLIER || $m->kind === CashMovement::KIND_EXPENSE_OTHER) {
                if ($m->our_account_id) {
                    $add((int) $m->our_account_id, -(float) $m->amount);
                }

                continue;
            }
            if ($m->kind === CashMovement::KIND_TRANSFER) {
                if ($m->from_account_id) {
                    $add((int) $m->from_account_id, -(float) $m->amount);
                }
                if ($m->to_account_id) {
                    $add((int) $m->to_account_id, (float) $m->amount);
                }
            }
        }

        return $net;
    }

    /**
     * Таблица для закрытия смены: по счетам — на начало, движение за интервал [opened_at, until], ожидается.
     * Для открытой смены $until обычно now; для закрытой — время закрытия.
     *
     * @return array{has_per_account_opening: bool, rows: list<array{label: string, opening: ?float, movement: float, expected: ?float}>, totals: array{opening: float, movement: float, expected: float}}
     */
    public function shiftAccountClosingTable(CashShift $shift, int $branchId, ?CarbonInterface $untilOverride = null): array
    {
        $openedAt = $shift->opened_at;
        $until = $untilOverride ?? ($shift->closed_at ?? Carbon::now());

        $openingMap = [];
        $hasPerAccountOpen = is_array($shift->opening_by_account) && $shift->opening_by_account !== [];
        if ($hasPerAccountOpen) {
            foreach ($shift->opening_by_account as $id => $amt) {
                $openingMap[(int) $id] = (float) $amt;
            }
        }

        $movementByAccount = $this->netCashChangeByAccountInShiftWindow(
            $branchId,
            $openedAt,
            $until,
            (int) $shift->user_id
        );

        $accountIds = collect(array_keys($openingMap))
            ->merge(array_keys($movementByAccount))
            ->unique()
            ->values()
            ->all();

        if ($accountIds === [] && $hasPerAccountOpen) {
            $accountIds = array_keys($openingMap);
        }

        if ($accountIds === []) {
            $accountIds = OrganizationBankAccount::query()
                ->whereHas('organization', fn ($q) => $q->where('branch_id', $branchId))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $accounts = OrganizationBankAccount::query()
            ->whereIn('id', $accountIds)
            ->whereHas('organization', fn ($q) => $q->where('branch_id', $branchId))
            ->with('organization:id,name,sort_order')
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

        $rows = [];
        foreach ($accounts as $a) {
            $id = (int) $a->id;
            $opening = $hasPerAccountOpen ? ($openingMap[$id] ?? 0.0) : null;
            $movement = (float) ($movementByAccount[$id] ?? 0.0);
            $expected = $opening !== null ? $opening + $movement : null;
            $rows[] = [
                'label' => trim(($a->organization?->name ?? '—').' — '.$a->labelWithoutAccountNumber()),
                'opening' => $opening,
                'movement' => $movement,
                'expected' => $expected,
            ];
        }

        $totalMovement = (float) array_sum($movementByAccount);
        $totalOpening = $hasPerAccountOpen
            ? (float) array_sum($openingMap)
            : (float) $shift->opening_cash;

        return [
            'has_per_account_opening' => $hasPerAccountOpen,
            'rows' => $rows,
            'totals' => [
                'opening' => $totalOpening,
                'movement' => $totalMovement,
                'expected' => $totalOpening + $totalMovement,
            ],
        ];
    }

    /**
     * Сводка по видам операций за интервал смены (тот же кассир и created_at, что и в netCashChangeByAccountInShiftWindow).
     *
     * @return array{retail_checks: int, retail_payments: float, refunds: float, income_client: float, income_other: float, expense_supplier: float, expense_other: float, transfer_volume: float}
     */
    public function shiftMoneyKindBreakdown(int $branchId, CarbonInterface $from, CarbonInterface $until, int $cashierUserId): array
    {
        $retailPayments = (float) (RetailSalePayment::query()
            ->join('retail_sales as rs', 'rs.id', '=', 'retail_sale_payments.retail_sale_id')
            ->where('rs.branch_id', $branchId)
            ->where('rs.created_at', '>=', $from)
            ->where('rs.created_at', '<=', $until)
            ->where('rs.user_id', $cashierUserId)
            ->sum('retail_sale_payments.amount'));

        $retailChecks = (int) RetailSale::query()
            ->where('branch_id', $branchId)
            ->where('user_id', $cashierUserId)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $until)
            ->count();

        $refunds = (float) (RetailSaleRefund::query()
            ->join('customer_returns as cr', 'cr.id', '=', 'retail_sale_refunds.customer_return_id')
            ->where('cr.branch_id', $branchId)
            ->where('retail_sale_refunds.created_at', '>=', $from)
            ->where('retail_sale_refunds.created_at', '<=', $until)
            ->whereHas('retailSale', fn ($q) => $q->where('user_id', $cashierUserId))
            ->sum('retail_sale_refunds.amount'));

        $incomeClient = 0.0;
        $incomeOther = 0.0;
        $expenseSupplier = 0.0;
        $expenseOther = 0.0;
        $transferVolume = 0.0;

        $movements = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $until)
            ->where('user_id', $cashierUserId)
            ->get();

        foreach ($movements as $m) {
            $amt = (float) $m->amount;
            if ($m->kind === CashMovement::KIND_INCOME_CLIENT) {
                $incomeClient += $amt;
            } elseif ($m->kind === CashMovement::KIND_INCOME_OTHER) {
                $incomeOther += $amt;
            } elseif ($m->kind === CashMovement::KIND_EXPENSE_SUPPLIER) {
                $expenseSupplier += $amt;
            } elseif ($m->kind === CashMovement::KIND_EXPENSE_OTHER) {
                $expenseOther += $amt;
            } elseif ($m->kind === CashMovement::KIND_TRANSFER) {
                $transferVolume += $amt;
            }
        }

        return [
            'retail_checks' => $retailChecks,
            'retail_payments' => round($retailPayments, 2),
            'refunds' => round($refunds, 2),
            'income_client' => round($incomeClient, 2),
            'income_other' => round($incomeOther, 2),
            'expense_supplier' => round($expenseSupplier, 2),
            'expense_other' => round($expenseOther, 2),
            'transfer_volume' => round($transferVolume, 2),
        ];
    }

    /**
     * Сводка по счетам за период [from, to]: остаток на начало, на конец, изменение.
     *
     * @return Collection<int, array{id: int, label: string, currency: string, opening: float, closing: float, change: float}>
     */
    public function periodAccountSummary(int $branchId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $accounts = $this->accountsForBranch($branchId);
        $toExclusive = Carbon::parse($to->format('Y-m-d'))->addDay();

        return $accounts->map(function (OrganizationBankAccount $acc) use ($branchId, $from, $toExclusive) {
            $id = $acc->id;
            $opening = $this->balanceAtDateExclusive($branchId, $id, $from);
            $closing = $this->balanceAtDateExclusive($branchId, $id, $toExclusive);

            return [
                'id' => $id,
                'label' => $acc->movementReportLabel(),
                'currency' => $acc->currency,
                'opening' => $opening,
                'closing' => $closing,
                'change' => $closing - $opening,
            ];
        })->values();
    }
}
