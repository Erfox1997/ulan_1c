<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerVehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerVehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $branchId = auth()->user()->branch_id;
        if ($branchId === null) {
            return response()->json([]);
        }

        $counterpartyId = (int) $request->query('counterparty_id', 0);
        if ($counterpartyId <= 0) {
            return response()->json([]);
        }

        $rows = CustomerVehicle::query()
            ->where('branch_id', (int) $branchId)
            ->where('counterparty_id', $counterpartyId)
            ->orderBy('id')
            ->get(['id', 'vehicle_brand', 'vin', 'vehicle_year', 'engine_volume', 'plate_number']);

        return response()->json($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = auth()->user()->branch_id;
        if ($branchId === null) {
            abort(403);
        }

        $validated = $request->validate([
            'counterparty_id' => [
                'required',
                'integer',
                Rule::exists('counterparties', 'id')->where(fn ($q) => $q->where('branch_id', (int) $branchId)),
            ],
            'vehicle_brand' => ['nullable', 'string', 'max:120'],
            'vin' => ['nullable', 'string', 'max:32'],
            'vehicle_year' => ['nullable', 'integer', 'min:1950', 'max:'.((int) date('Y') + 1)],
            'engine_volume' => ['nullable', 'string', 'max:64'],
            'plate_number' => ['nullable', 'string', 'max:32'],
        ]);

        $brandRaw = isset($validated['vehicle_brand']) ? trim((string) $validated['vehicle_brand']) : '';
        $brand = $brandRaw === '' ? null : $brandRaw;

        $vinRaw = isset($validated['vin']) ? trim((string) $validated['vin']) : '';
        $vin = $vinRaw === '' ? null : $vinRaw;

        if ($vin !== null) {
            $dup = CustomerVehicle::query()
                ->where('branch_id', (int) $branchId)
                ->where('vin', $vin)
                ->first();
            if ($dup !== null) {
                return response()->json([
                    'id' => $dup->id,
                    'vehicle_brand' => $dup->vehicle_brand,
                    'vin' => $dup->vin,
                    'vehicle_year' => $dup->vehicle_year,
                    'engine_volume' => $dup->engine_volume,
                    'plate_number' => $dup->plate_number,
                    'existing' => true,
                ]);
            }
        }

        $vehicle = CustomerVehicle::query()->create([
            'branch_id' => (int) $branchId,
            'counterparty_id' => (int) $validated['counterparty_id'],
            'vehicle_brand' => $brand,
            'vin' => $vin,
            'vehicle_year' => $validated['vehicle_year'] ?? null,
            'engine_volume' => isset($validated['engine_volume']) ? trim((string) $validated['engine_volume']) : null,
            'plate_number' => isset($validated['plate_number']) ? trim((string) $validated['plate_number']) : null,
        ]);

        return response()->json([
            'id' => $vehicle->id,
            'vehicle_brand' => $vehicle->vehicle_brand,
            'vin' => $vehicle->vin,
            'vehicle_year' => $vehicle->vehicle_year,
            'engine_volume' => $vehicle->engine_volume,
            'plate_number' => $vehicle->plate_number,
        ], 201);
    }
}
