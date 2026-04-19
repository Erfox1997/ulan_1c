<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Counterparty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CounterpartySearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
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

        /** Только покупатели (без поставщиков и «прочих») — для оформления заявок юрлицу */
        if ($request->boolean('buyers_only')) {
            $rows = Counterparty::query()
                ->where('branch_id', (int) $branchId)
                ->where('kind', Counterparty::KIND_BUYER)
                ->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhere('full_name', 'like', $like);
                })
                ->orderBy('name')
                ->limit(25)
                ->get(['id', 'kind', 'name', 'full_name', 'legal_form', 'inn', 'phone']);

            return response()->json($rows);
        }

        /** @var string $for sale = покупатели у нас; purchase = поставщики нам; пусто = все (редко) */
        $for = (string) $request->query('for', '');

        $query = Counterparty::query()
            ->where('branch_id', (int) $branchId)
            ->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('full_name', 'like', $like);
            });

        if ($for === 'sale') {
            $query->whereIn('kind', [Counterparty::KIND_BUYER, Counterparty::KIND_OTHER]);
            $orderSql = 'CASE WHEN kind = ? THEN 0 WHEN kind = ? THEN 1 ELSE 2 END';
            $orderBindings = [Counterparty::KIND_BUYER, Counterparty::KIND_OTHER];
        } elseif ($for === 'purchase') {
            $query->whereIn('kind', [Counterparty::KIND_SUPPLIER, Counterparty::KIND_OTHER]);
            $orderSql = 'CASE WHEN kind = ? THEN 0 WHEN kind = ? THEN 1 ELSE 2 END';
            $orderBindings = [Counterparty::KIND_SUPPLIER, Counterparty::KIND_OTHER];
        } else {
            $orderSql = 'CASE WHEN kind = ? THEN 0 WHEN kind = ? THEN 1 ELSE 2 END';
            $orderBindings = [Counterparty::KIND_BUYER, Counterparty::KIND_OTHER];
        }

        $rows = $query
            ->orderByRaw($orderSql, $orderBindings)
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'kind', 'name', 'full_name', 'legal_form', 'inn', 'phone']);

        return response()->json($rows);
    }
}
