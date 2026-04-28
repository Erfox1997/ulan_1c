<?php

namespace App\Support;

/** Определяет «пустые» строки табличной части поступления в форме. */
final class PurchaseReceiptLineDraft
{
    /** Строка не участвует в документе (все значимые поля пустые). */
    public static function isGhost(array $line): bool
    {
        $t = static fn (?string $v): string => trim((string) ($v ?? ''));

        if ($t($line['name'] ?? '') !== '') {
            return false;
        }
        if ($t($line['article_code'] ?? '') !== '') {
            return false;
        }
        $qtyRaw = $line['quantity'] ?? null;
        if ($qtyRaw !== null && $qtyRaw !== '' && $t((string) $qtyRaw) !== '') {
            return false;
        }
        $price = $line['unit_price'] ?? null;
        if ($price !== null && $price !== '' && $t((string) $price) !== '') {
            return false;
        }
        $sale = $line['sale_price'] ?? null;
        if ($sale !== null && $sale !== '' && $t((string) $sale) !== '') {
            return false;
        }
        if ($t($line['barcode'] ?? '') !== '') {
            return false;
        }
        if ($t($line['markup_percent'] ?? '') !== '') {
            return false;
        }

        return true;
    }
}
