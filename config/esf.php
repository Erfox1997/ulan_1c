<?php

/**
 * Выгрузка XML ЭСФ в формате, совместимом с порталом ГНС КР (структура как в экспорте «receipt»).
 * Коды при необходимости уточните по актуальным справочникам / XSD.
 */
return [

    'encoding' => 'UTF-8',

    /**
     * true — новый UUID в поле exchangeCode при каждой выгрузке (удобно для тестов и повторной загрузки в ГНС).
     * false — один код на документ (поле esf_exchange_code) до правки реализации или снятия отметки «Записано в ЭСФ».
     * Переключатель: ESF_RANDOM_EXCHANGE_CODE в .env или этот ключ.
     */
    'random_exchange_code_each_download' => env('ESF_RANDOM_EXCHANGE_CODE', true),

    /** Часовой пояс для deliveryContractDate (смещение в дате) */
    'timezone' => 'Asia/Bishkek',

    /** ISO 4217 numeric — сом (как в примере с портала) */
    'currency_code' => '417',

    'currency_name' => 'Сом',

    'document_status_name' => 'Новый',

    'exchange_rate' => '1.0000',

    'invoice_delivery_type_code' => '399',

    'vat_code' => '100',

    'vat_delivery_type_code' => '102',

    /** Код вида чека / документа (поля receiptTypeCode и type в примере) */
    'receipt_type_code' => '10',

    'document_type_code' => '10',

    /**
     * Код способа оплаты (paymentTypeCode) — справочник ГНС «Вид оплаты».
     * Нал и безнал должны быть разными кодами, иначе в кабинете отображается одна форма (например, всегда «наличная»).
     * Если портал ругается или показывает неверный вид — откройте актуальный справочник в личном кабинете и подставьте свои значения.
     */
    'payment_type_code' => [
        'cash' => '10',
        'bank' => '20',
    ],

    /** Код налоговой классификации строки (stCode), по умолчанию как в примере */
    'st_code_default' => '50',

    'is_industry' => 'false',

    'is_price_without_taxes' => 'false',

    'is_resident' => 'true',

    /**
     * Ставка НДС, % — при 0 в строках stAmount/vatAmount остаются 0.00 (как в примере).
     */
    'vat_rate_percent' => 0.0,

    /**
     * Примечание в XML (note). Пустая строка — подставится note_template.
     */
    'note' => '',

    /** Если note пусто: sprintf, один аргумент — id реализации (например «Реализация №%d»). */
    'note_template' => 'Реализация №%d',

    /** Префикс кода в ownedCrmReceiptCode (как в рабочем примере: LES-123). */
    'owned_crm_receipt_code_prefix' => 'LES',
];
