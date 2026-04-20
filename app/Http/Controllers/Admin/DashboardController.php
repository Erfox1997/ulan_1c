<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashShift;
use App\Models\OrganizationBankAccount;
use App\Services\CashLedgerService;
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
            ? $cashLedger->shiftAccountClosingTable($openShift, $branchId)
            : null;

        return view('admin.dashboard', compact(
            'openShift',
            'accountsForOpenShift',
            'accountsForCloseShift',
            'closingExpectedByAccount',
        ));
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
