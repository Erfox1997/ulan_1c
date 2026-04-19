<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ArticleSequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ArticleSequenceController extends Controller
{
    public function __construct(
        private readonly ArticleSequenceService $articleSequence
    ) {}

    public function reserve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:500'],
        ]);
        $branchId = (int) $request->user()->branch_id;

        try {
            $codes = $this->articleSequence->reserveNextArticleCodes($branchId, (int) $data['count']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['codes' => $codes]);
    }
}
