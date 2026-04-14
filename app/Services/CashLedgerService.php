<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\OrganizationBankAccount;
use App\Models\RetailSale;
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

        $r = (float) (RetailSale::query()
            ->where('branch_id', $branchId)
            ->where('organization_bank_account_id', $accountId)
            ->where('document_date', '<', $dateStr)
            ->sum('total_amount'));

        $inc = (float) (CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_INCOME_CLIENT)
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

        return $opening + $r + $inc + $tIn - $outS - $outO - $tOut;
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

        $retail = RetailSale::query()
            ->where('branch_id', $branchId)
            ->with('organizationBankAccount')
            ->when($from, fn ($q) => $q->whereDate('document_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('document_date', '<=', $to))
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->get();

        $rows = collect();

        foreach ($manual as $m) {
            $rows->push($this->rowFromCashMovement($m));
        }

        foreach ($retail as $sale) {
            $acc = $sale->organizationBankAccount;
            $rows->push([
                'sort_at' => $sale->document_date->copy()->startOfDay()->timestamp,
                'sort_bucket' => 0,
                'sort_id' => (int) $sale->id,
                'date' => $sale->document_date->format('d.m.Y'),
                'title' => 'Розничная продажа (ФЛ)',
                'account_label' => $acc?->movementReportLabel() ?? '—',
                'in' => (float) $sale->total_amount,
                'out' => 0.0,
                'detail' => 'Документ № '.$sale->id,
                'source' => 'retail_sale',
                'source_id' => $sale->id,
                'affected_account_ids' => $acc ? [$acc->id] : [],
                'delta_by_account' => $acc ? [$acc->id => (float) $sale->total_amount] : [],
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
     * Сводка движения по дням: приход (клиент + розница), расход поставщику, прочие расходы, переводы между счетами.
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
            } elseif ($m->kind === CashMovement::KIND_EXPENSE_SUPPLIER) {
                $bucket[$d]['expense_supplier'] += $amt;
            } elseif ($m->kind === CashMovement::KIND_EXPENSE_OTHER) {
                $bucket[$d]['expense_other'] += $amt;
            } elseif ($m->kind === CashMovement::KIND_TRANSFER) {
                $bucket[$d]['transfer'] += $amt;
            }
        }

        $retail = RetailSale::query()
            ->where('branch_id', $branchId)
            ->whereDate('document_date', '>=', $fromStr)
            ->whereDate('document_date', '<=', $toStr)
            ->get();

        foreach ($retail as $sale) {
            $d = $sale->document_date->format('Y-m-d');
            $ensure($d);
            $bucket[$d]['income'] += (float) $sale->total_amount;
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

        $retailRows = RetailSale::query()
            ->where('branch_id', $branchId)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $until)
            ->whereNotNull('organization_bank_account_id')
            ->when($cashierUserId !== null, fn ($q) => $q->where('user_id', $cashierUserId))
            ->selectRaw('organization_bank_account_id as aid, SUM(total_amount) as s')
            ->groupBy('organization_bank_account_id')
            ->get();

        foreach ($retailRows as $row) {
            $add((int) $row->aid, (float) $row->s);
        }

        $movements = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $until)
            ->when($cashierUserId !== null, fn ($q) => $q->where('user_id', $cashierUserId))
            ->get();

        foreach ($movements as $m) {
            if ($m->kind === CashMovement::KIND_INCOME_CLIENT) {
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
