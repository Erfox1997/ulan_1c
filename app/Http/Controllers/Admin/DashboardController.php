<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashShift;
use App\Models\OrganizationBankAccount;
use App\Services\CashLedgerService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(CashLedgerService $cashLedger): View
    {
        $branchId = (int) auth()->user()->branch_id;

        $openShift = CashShift::query()
            ->where('branch_id', $branchId)
            ->where('user_id', auth()->id())
            ->open()
            ->with('user')
            ->first();

        $accountsForOpenShift = $openShift === null
            ? $this->branchBankAccountsForLabels($branchId)
            : [];

        $accountsForCloseShift = $openShift !== null
            ? $this->branchBankAccountsForLabels($branchId)
            : [];

        $closingExpectedByAccount = $openShift !== null
            ? $this->buildClosingExpectedByAccount($openShift, $branchId, $cashLedger)
            : null;

        return view('admin.dashboard', compact(
            'openShift',
            'accountsForOpenShift',
            'accountsForCloseShift',
            'closingExpectedByAccount',
        ));
    }

    /**
     * Ориентир при закрытии: по каждому счёту — на начало смены + денежные операции этого кассира с открытия (розница, банк/касса, переводы).
     *
     * @return array{has_per_account_opening: bool, rows: list<array{label: string, opening: ?float, movement: float, expected: ?float}>, totals: array{opening: float, movement: float, expected: float}}
     */
    private function buildClosingExpectedByAccount(CashShift $shift, int $branchId, CashLedgerService $cashLedger): array
    {
        $openedAt = $shift->opened_at;
        $until = Carbon::now();

        $openingMap = [];
        $hasPerAccountOpen = is_array($shift->opening_by_account) && $shift->opening_by_account !== [];
        if ($hasPerAccountOpen) {
            foreach ($shift->opening_by_account as $id => $amt) {
                $openingMap[(int) $id] = (float) $amt;
            }
        }

        $movementByAccount = $cashLedger->netCashChangeByAccountInShiftWindow(
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
     * Счета филиала для формы открытия смены (только подписи; суммы из карточек организации не показываем).
     *
     * @return list<array{id: int, label: string}>
     */
    private function branchBankAccountsForLabels(int $branchId): array
    {
        $accounts = OrganizationBankAccount::query()
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

        $out = [];
        foreach ($accounts as $account) {
            $out[] = [
                'id' => (int) $account->id,
                'label' => trim(($account->organization?->name ?? '—').' — '.$account->labelWithoutAccountNumber()),
            ];
        }

        return $out;
    }
}
