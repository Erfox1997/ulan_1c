<?php

namespace App\Services\Esf;

/**
 * Сокращение длинных наименований товаров для выгрузок ЭСФ (только товары, не услуги).
 */
final class EsfExportGoodsName
{
    public static function firstTwoWords(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return '';
        }

        $parts = preg_split('/\s+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return $trimmed;
        }
        if (count($parts) === 1) {
            return $parts[0];
        }

        return $parts[0].' '.$parts[1];
    }
}
