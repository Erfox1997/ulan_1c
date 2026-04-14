<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Good;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategorySuggestController extends Controller
{
    /**
     * Distinct category names for the current branch (from goods). Optional ?q= filter.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $branchId = auth()->user()->branch_id;
        if ($branchId === null) {
            return response()->json([]);
        }

        $term = trim((string) $request->query('q', ''));

        $query = Good::query()
            ->where('goods.branch_id', (int) $branchId)
            ->whereNotNull('goods.category')
            ->where('goods.category', '!=', '');

        if ($term !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
            $query->where('goods.category', 'like', '%'.$escaped.'%');
        }

        $categories = $query
            ->select('goods.category')
            ->distinct()
            ->orderBy('goods.category')
            ->limit(40)
            ->pluck('category')
            ->values()
            ->all();

        return response()->json($categories);
    }
}
