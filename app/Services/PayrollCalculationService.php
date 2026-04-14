<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeePenalty;
use App\Models\RetailSale;
use App\Models\ServiceOrder;
use Illuminate\Support\Carbon;

class PayrollCalculationService
{
    /**
     * Расчёт начислений за период: оклад и проценты из карточки сотрудника,
     * оборот розницы (товары) и заказов услуг по полю «автор документа» (user_id),
     * минус авансы и штрафы с датой в периоде.
     *
     * @return list<array{
     *   employee: Employee,
     *   goods_turnover: float,
     *   services_turnover: float,
     *   fixed: float,
     *   goods_commission: float,
     *   services_commission: float,
     *   advances: float,
     *   penalties: float,
     *   accrual: float,
     *   net: float
     * }>
     */
    public function linesForPeriod(int $branchId, Carbon $from, Carbon $to): array
    {
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        $employees = Employee::query()
            ->where('branch_id', $branchId)
            ->with('user')
            ->orderBy('full_name')
            ->get();

        $out = [];

        foreach ($employees as $employee) {
            $userId = $employee->user_id;

            $goodsTurnover = (float) RetailSale::query()
                ->where('branch_id', $branchId)
                ->where('user_id', $userId)
                ->whereBetween('document_date', [$fromStr, $toStr])
                ->sum('total_amount');

            $servicesTurnover = (float) ServiceOrder::query()
                ->where('branch_id', $branchId)
                ->where('user_id', $userId)
                ->where('status', '!=', ServiceOrder::STATUS_CANCELLED)
                ->whereBetween('document_date', [$fromStr, $toStr])
                ->sum('total_amount');

            $fixed = (float) ($employee->salary_fixed ?? 0);
            $pctGoods = (float) ($employee->salary_percent_goods ?? 0);
            $pctServices = (float) ($employee->salary_percent_services ?? 0);

            $goodsCommission = round($goodsTurnover * $pctGoods / 100, 2);
            $servicesCommission = round($servicesTurnover * $pctServices / 100, 2);

            $advances = (float) EmployeeAdvance::query()
                ->where('branch_id', $branchId)
                ->where('employee_id', $employee->id)
                ->whereBetween('entry_date', [$fromStr, $toStr])
                ->sum('amount');

            $penalties = (float) EmployeePenalty::query()
                ->where('branch_id', $branchId)
                ->where('employee_id', $employee->id)
                ->whereBetween('entry_date', [$fromStr, $toStr])
                ->sum('amount');

            $accrual = round($fixed + $goodsCommission + $servicesCommission, 2);
            $net = round($accrual - $advances - $penalties, 2);

            $out[] = [
                'employee' => $employee,
                'goods_turnover' => round($goodsTurnover, 2),
                'services_turnover' => round($servicesTurnover, 2),
                'fixed' => round($fixed, 2),
                'goods_commission' => $goodsCommission,
                'services_commission' => $servicesCommission,
                'advances' => round($advances, 2),
                'penalties' => round($penalties, 2),
                'accrual' => $accrual,
                'net' => $net,
            ];
        }

        return $out;
    }
}
