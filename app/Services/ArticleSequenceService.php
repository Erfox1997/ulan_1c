<?php

namespace App\Services;

use App\Models\BranchArticleCounter;
use App\Models\Good;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ArticleSequenceService
{
    private const WIDTH = 6;

    private const MAX_VALUE = 999_999;

    /**
     * Уникальные 6-значные числовые артикулы по порядку для филиала (000001 …).
     *
     * @return list<string>
     */
    public function reserveNextArticleCodes(int $branchId, int $count): array
    {
        if ($count < 1) {
            return [];
        }

        if ($count > 500) {
            throw new RuntimeException('За один раз можно запросить не более 500 артикулов.');
        }

        return DB::transaction(function () use ($branchId, $count): array {
            $goodsMax = $this->maxNumericArticleFromGoods($branchId);

            $counter = BranchArticleCounter::query()
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->first();

            if ($counter === null) {
                try {
                    $counter = BranchArticleCounter::query()->create([
                        'branch_id' => $branchId,
                        'next_num' => max(1, $goodsMax + 1),
                    ]);
                } catch (QueryException $e) {
                    $counter = BranchArticleCounter::query()
                        ->where('branch_id', $branchId)
                        ->lockForUpdate()
                        ->first();
                    if ($counter === null) {
                        throw $e;
                    }
                }
            }

            $start = max((int) $counter->next_num, $goodsMax + 1);
            if ($start > self::MAX_VALUE) {
                throw new RuntimeException('Исчерпан диапазон числовых артикулов (максимум 999999).');
            }
            if ($start + $count - 1 > self::MAX_VALUE) {
                throw new RuntimeException('Недостаточно свободных номеров в диапазоне до 999999.');
            }

            $codes = [];
            for ($i = 0; $i < $count; $i++) {
                $codes[] = str_pad((string) ($start + $i), self::WIDTH, '0', STR_PAD_LEFT);
            }

            $counter->next_num = $start + $count;
            $counter->save();

            return $codes;
        });
    }

    /**
     * Максимум среди существующих артикулов, состоящих только из цифр длиной 1–6 символов.
     */
    public function maxNumericArticleFromGoods(int $branchId): int
    {
        $castExpr = $this->articleCodeIntegerCastSql();

        $max = Good::query()
            ->where('branch_id', $branchId)
            ->tap(fn ($q) => $this->scopeNumericArticleCodes($q))
            ->selectRaw("MAX({$castExpr}) as max_num")
            ->value('max_num');

        return (int) ($max ?? 0);
    }

    private function articleCodeIntegerCastSql(): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => 'CAST(article_code AS UNSIGNED)',
            default => 'CAST(article_code AS INTEGER)',
        };
    }

    /**
     * @param  Builder<Good>  $query
     */
    private function scopeNumericArticleCodes(Builder $query): void
    {
        $driver = DB::connection()->getDriverName();

        match ($driver) {
            'mysql', 'mariadb' => $query->whereRaw("article_code REGEXP '^[0-9]{1,6}$'"),
            'pgsql' => $query->whereRaw("article_code ~ '^[0-9]{1,6}$'"),
            default => $query
                ->whereRaw("TRIM(article_code, '0123456789') = ''")
                ->whereRaw('LENGTH(article_code) >= 1')
                ->whereRaw('LENGTH(article_code) <= 6'),
        };
    }
}
