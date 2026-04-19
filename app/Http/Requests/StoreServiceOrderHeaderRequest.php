<?php

namespace App\Http\Requests;

use App\Models\CustomerVehicle;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceOrderHeaderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->branch_id !== null;
    }

    public function rules(): array
    {
        $branchId = (int) $this->user()->branch_id;

        return [
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('branch_id', $branchId)),
            ],
            'document_date' => ['required', 'date'],
            'counterparty_id' => [
                'required',
                'integer',
                Rule::exists('counterparties', 'id')->where(fn ($q) => $q->where('branch_id', $branchId)),
            ],
            'contact_name' => ['required', 'string', 'max:255'],
            'customer_vehicle_id' => ['required', 'integer'],
            'mileage_km' => ['required', 'numeric', 'min:0'],
            'lead_master_employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where(fn ($q) => $q->where('branch_id', $branchId)->where('job_type', Employee::JOB_MASTER)),
            ],
            'deadline_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $branchId = (int) $this->user()->branch_id;
            $cpId = (int) $this->input('counterparty_id');
            $vehId = (int) $this->input('customer_vehicle_id');

            $veh = CustomerVehicle::query()
                ->where('branch_id', $branchId)
                ->where('counterparty_id', $cpId)
                ->whereKey($vehId)
                ->first();

            if ($veh === null) {
                $v->errors()->add('customer_vehicle_id', 'Выберите автомобиль из списка или добавьте новый.');
            }
        });
    }
}
