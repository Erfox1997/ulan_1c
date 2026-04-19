<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeePenalty;
use App\Models\RetailSale;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PayrollCalculationService
{
    /**
     * Расчёт начислений за период: оклад и проценты из карточки сотрудника;
     * % с товаров — от суммы розничных чеков, оформленных пользователем, привязанным к сотруднику;
     * % с услуг — от сумм строк заявок, где сотрудник указан исполнителем (оформление позиций на /sell/…/lines), по проведённым заявкам;
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

            $goodsTurnover = 0.0;
            if ($userId !== null && $userId > 0) {
                $goodsTurnover = (float) RetailSale::query()
                    ->where('branch_id', $branchId)
                    ->where('user_id', $userId)
                    ->whereBetween('document_date', [$fromStr, $toStr])
                    ->sum('total_amount');
            }

            $servicesTurnover = (float) ServiceOrderLine::query()
                ->where('performer_employee_id', $employee->id)
                ->whereHas('serviceOrder', function ($q) use ($branchId, $fromStr, $toStr): void {
                    $q->where('branch_id', $branchId)
                        ->where('status', ServiceOrder::STATUS_FULFILLED)
                        ->whereBetween('document_date', [$fromStr, $toStr]);
                })
                ->sum('line_sum');

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

    /**
     * Розничные чеки за период, оформленные учётной записью сотрудника (для карточки зарплаты).
     *
     * @return Collection<int, RetailSale>
     */
    public function retailSalesForEmployeePeriod(int $branchId, ?int $userId, Carbon $from, Carbon $to, int $limit = 80): Collection
    {
        if ($userId === null || $userId <= 0) {
            return collect();
        }

        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        return RetailSale::query()
            ->where('branch_id', $branchId)
            ->where('user_id', $userId)
            ->whereBetween('document_date', [$fromStr, $toStr])
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Строки заявок услуг за период, где сотрудник — исполнитель (проведённые заявки).
     *
     * @return Collection<int, ServiceOrderLine>
     */
    public function serviceLinesForEmployeePeriod(int $branchId, int $employeeId, Carbon $from, Carbon $to, int $limit = 120): Collection
    {
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        return ServiceOrderLine::query()
            ->where('performer_employee_id', $employeeId)
            ->whereHas('serviceOrder', function ($q) use ($branchId, $fromStr, $toStr): void {
                $q->where('branch_id', $branchId)
                    ->where('status', ServiceOrder::STATUS_FULFILLED)
                    ->whereBetween('document_date', [$fromStr, $toStr]);
            })
            ->with(['serviceOrder:id,document_date,status'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
