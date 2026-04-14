<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WarehouseRequest;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(): View
    {
        $branchId = auth()->user()->branch_id;
        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.warehouses.index', compact('warehouses'));
    }

    public function create(): View
    {
        return view('admin.warehouses.create', [
            'warehouse' => new Warehouse([
                'sort_order' => 0,
                'is_default' => false,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(WarehouseRequest $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;

        DB::transaction(function () use ($request, $branchId) {
            $data = $this->payload($request);
            $data['branch_id'] = $branchId;
            $warehouse = Warehouse::create($data);
            if ($request->boolean('is_default')) {
                $this->setAsOnlyDefault($warehouse);
            }
        });

        return redirect()->route('admin.warehouses.index')
            ->with('status', 'Склад добавлен.');
    }

    public function edit(Warehouse $warehouse): View
    {
        $this->authorizeWarehouse($warehouse);

        return view('admin.warehouses.edit', compact('warehouse'));
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $this->authorizeWarehouse($warehouse);

        DB::transaction(function () use ($request, $warehouse) {
            $warehouse->update($this->payload($request));
            if ($request->boolean('is_default')) {
                $this->setAsOnlyDefault($warehouse);
            }
        });

        return redirect()->route('admin.warehouses.index')
            ->with('status', 'Склад сохранён.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        $this->authorizeWarehouse($warehouse);
        $warehouse->delete();

        return redirect()->route('admin.warehouses.index')
            ->with('status', 'Склад удалён.');
    }

    private function authorizeWarehouse(Warehouse $warehouse): void
    {
        if ($warehouse->branch_id !== auth()->user()->branch_id) {
            abort(403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(WarehouseRequest $request): array
    {
        return [
            'name' => $request->input('name'),
            'code' => $request->filled('code') ? trim((string) $request->input('code')) : null,
            'address' => $request->filled('address') ? trim((string) $request->input('address')) : null,
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_default' => $request->boolean('is_default'),
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    private function setAsOnlyDefault(Warehouse $warehouse): void
    {
        Warehouse::query()
            ->where('branch_id', $warehouse->branch_id)
            ->whereKeyNot($warehouse->id)
            ->update(['is_default' => false]);

        $warehouse->update(['is_default' => true]);
    }
}
