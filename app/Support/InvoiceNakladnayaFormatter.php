<?php

namespace App\Support;

use Carbon\CarbonInterface;
use NumberFormatter;

class InvoiceNakladnayaFormatter
{
    /**
     * «Накладная на поступление № N от D месяца Y г.»
     */
    public static function documentTitle(CarbonInterface $date, int $documentNumber): string
    {
        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля', 5 => 'мая', 6 => 'июня',
            7 => 'июля', 8 => 'августа', 9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
        ];
        $m = (int) $date->month;

        return sprintf(
            'Накладная на поступление № %d от %d %s %d г.',
            $documentNumber,
            $date->day,
            $months[$m] ?? '',
            $date->year
        );
    }

    /**
     * «Накладная на возврат поставщику № N от D месяца Y г.»
     */
    public static function purchaseReturnDocumentTitle(CarbonInterface $date, int $documentNumber): string
    {
        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля', 5 => 'мая', 6 => 'июня',
            7 => 'июля', 8 => 'августа', 9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
        ];
        $m = (int) $date->month;

        return sprintf(
            'Накладная на возврат поставщику № %d от %d %s %d г.',
            $documentNumber,
            $date->day,
            $months[$m] ?? '',
            $date->year
        );
    }

    /**
     * «Накладная на реализацию № N от D месяца Y г.»
     */
    public static function legalEntitySaleDocumentTitle(CarbonInterface $date, int $documentNumber): string
    {
        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля', 5 => 'мая', 6 => 'июня',
            7 => 'июля', 8 => 'августа', 9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
        ];
        $m = (int) $date->month;

        return sprintf(
            'Накладная на реализацию № %d от %d %s %d г.',
            $documentNumber,
            $date->day,
            $months[$m] ?? '',
            $date->year
        );
    }

    /**
     * «Счёт на оплату № N от D месяца Y г.» (к реализации юрлицу).
     */
    public static function paymentInvoiceDocumentTitle(CarbonInterface $date, int $documentNumber): string
    {
        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля', 5 => 'мая', 6 => 'июня',
            7 => 'июля', 8 => 'августа', 9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
        ];
        $m = (int) $date->month;

        return sprintf(
            'Счёт на оплату № %d от %d %s %d г.',
            $documentNumber,
            $date->day,
            $months[$m] ?? '',
            $date->year
        );
    }

    /**
     * Заголовок объединённого счёта (несколько реализаций, один покупатель).
     *
     * @param  list<int>  $saleIds
     */
    public static function mergedPaymentInvoiceDocumentTitle(CarbonInterface $date, array $saleIds): string
    {
        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля', 5 => 'мая', 6 => 'июня',
            7 => 'июля', 8 => 'августа', 9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
        ];
        $m = (int) $date->month;
        $ids = array_values(array_unique(array_map('intval', $saleIds)));
        sort($ids);
        $nums = implode(', ', $ids);

        return sprintf(
            'Объединённый счёт на оплату от %d %s %d г. (реализации № %s)',
            $date->day,
            $months[$m] ?? '',
            $date->year,
            $nums
        );
    }

    public static function customerReturnDocumentTitle(CarbonInterface $date, int $documentNumber): string
    {
        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля', 5 => 'мая', 6 => 'июня',
            7 => 'июля', 8 => 'августа', 9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
        ];
        $m = (int) $date->month;

        return sprintf(
            'Накладная на возврат от клиента № %d от %d %s %d г.',
            $documentNumber,
            $date->day,
            $months[$m] ?? '',
            $date->year
        );
    }

    /**
     * «Заказ-наряд №000123 от 01.04.2025г.» (номер — с ведущими нулями до 6 знаков).
     */
    public static function serviceWorkOrderTitle(CarbonInterface $date, int $documentNumber): string
    {
        $num = str_pad((string) $documentNumber, 6, '0', STR_PAD_LEFT);

        return sprintf('Заказ-наряд №%s от %sг.', $num, $date->format('d.m.Y'));
    }

    /**
     * Количество с единицей (как в накладных: «1 000 шт» или «1,5 шт»).
     */
    public static function formatQuantityWithUnit(string|float|null $quantity, ?string $unit): string
    {
        $u = trim((string) ($unit ?? '')) ?: 'шт.';
        $q = (float) $quantity;
        if (! is_finite($q)) {
            return '— '.$u;
        }
        if (abs($q - round($q)) < 0.00001) {
            $s = number_format(round($q), 0, '.', ',');
        } else {
            $s = rtrim(rtrim(number_format($q, 3, ',', ' '), '0'), ',');
        }

        return $s.' '.$u;
    }

    public static function formatMoney(float $v): string
    {
        return number_format($v, 2, ',', ' ');
    }

    /**
     * Сумма прописью для КР (сом / тыйын). Число целой части — через intl spellout, при отсутствии — упрощённо.
     */
    public static function amountInWordsKgs(float $amount): string
    {
        $amount = max(0, $amount);
        $int = (int) floor($amount + 1e-9);
        $tyiyn = (int) round(($amount - $int) * 100);
        if ($tyiyn >= 100) {
            $tyiyn = 0;
            $int++;
        }

        $words = null;
        if (class_exists(NumberFormatter::class)) {
            $nf = new NumberFormatter('ru_RU', NumberFormatter::SPELLOUT);
            $words = $nf->format($int);
            if ($words === false) {
                $words = null;
            }
        }
        if ($words === null || $words === '') {
            $words = (string) $int;
        }
        $words = mb_strtoupper(mb_substr($words, 0, 1)).mb_substr($words, 1);

        return sprintf('%s сом %s тыйын', $words, str_pad((string) $tyiyn, 2, '0', STR_PAD_LEFT));
    }
}
