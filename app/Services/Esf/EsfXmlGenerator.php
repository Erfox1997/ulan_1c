<?php

namespace App\Services\Esf;

use App\Models\LegalEntitySale;
use App\Models\Organization;
use App\Models\OrganizationBankAccount;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Формат как в рабочем образце test_esf_realization_real.xml (портал ГНС КР).
 */
class EsfXmlGenerator
{
    private const XSI_NS = 'http://www.w3.org/2001/XMLSchema-instance';

    /**
     * @param  'cash'|'bank'  $paymentKind
     * @param  string|null  $exchangeCode  Уникальный код обмена (ГНС); если null — новый UUID (лучше задавать из контроллера).
     * @param  'all'|'goods'|'services'  $linesKind  Какие строки включить (товары: is_service=false, услуги: true).
     * @param  bool  $splitReceiptNoteAndCrm  Если true — в note и ownedCrmReceiptCode добавляется пометка товары/услуги (документ со смешанными строками).
     */
    public function build(
        LegalEntitySale $sale,
        Organization $seller,
        string $paymentKind,
        ?OrganizationBankAccount $paymentAccount = null,
        ?string $exchangeCode = null,
        string $linesKind = 'all',
        bool $splitReceiptNoteAndCrm = false
    ): string {
        $dom = $this->createEmptyDocument();
        $receipts = $dom->getElementsByTagName('receipts')->item(0);
        if (! $receipts instanceof DOMElement) {
            throw new \RuntimeException('Неверная структура DOM ЭСФ.');
        }

        $lines = $this->collectLinesForKind($sale, $linesKind);
        if ($lines->isEmpty()) {
            throw new \InvalidArgumentException('Нет строк для выгрузки ЭСФ по выбранному виду (товары или услуги).');
        }
        if ($linesKind === 'all') {
            $profile = $sale->esfGoodsServicesLinesProfile();
            $splitReceiptNoteAndCrm = $profile['mixed'];
        }

        $ex = ($exchangeCode !== null && trim($exchangeCode) !== '')
            ? trim($exchangeCode)
            : (string) Str::uuid();

        $this->appendOneReceipt(
            $dom,
            $receipts,
            $sale,
            $paymentKind,
            $paymentAccount,
            $ex,
            $lines,
            $linesKind,
            $splitReceiptNoteAndCrm
        );

        return $this->saveDocumentXml($dom);
    }

    /**
     * Несколько чеков в одном файле: один &lt;receipts&gt;, несколько &lt;receipt&gt; (та же схема, что и одна реализация).
     * У каждого чека свой exchangeCode.
     *
     * @param  list<LegalEntitySale>  $sales
     * @param  'goods'|'services'  $linesKind
     */
    public function buildMany(
        array $sales,
        Organization $seller,
        string $paymentKind,
        ?OrganizationBankAccount $paymentAccount,
        string $linesKind
    ): string {
        if (count($sales) < 2) {
            throw new \InvalidArgumentException('Для объединённого XML нужно не меньше двух документов.');
        }
        if (! in_array($linesKind, ['goods', 'services'], true)) {
            throw new \InvalidArgumentException('Один файл — только товары или только услуги.');
        }

        $dom = $this->createEmptyDocument();
        $receipts = $dom->getElementsByTagName('receipts')->item(0);
        if (! $receipts instanceof DOMElement) {
            throw new \RuntimeException('Неверная структура DOM ЭСФ.');
        }

        foreach ($sales as $sale) {
            $profile = $sale->esfGoodsServicesLinesProfile();
            $splitReceiptNoteAndCrm = $profile['mixed'];
            $lines = $this->collectLinesForKind($sale, $linesKind);
            if ($lines->isEmpty()) {
                throw new \InvalidArgumentException(
                    'Документ № '.(int) $sale->id.': нет строк выбранного вида (товары или услуги).'
                );
            }
            $ex = (string) Str::uuid();
            $this->appendOneReceipt(
                $dom,
                $receipts,
                $sale,
                $paymentKind,
                $paymentAccount,
                $ex,
                $lines,
                $linesKind,
                $splitReceiptNoteAndCrm
            );
        }

        return $this->saveDocumentXml($dom);
    }

    private function createEmptyDocument(): DOMDocument
    {
        $cfg = config('esf');
        $encoding = $cfg['encoding'] ?? 'UTF-8';

        $dom = new DOMDocument('1.0', $encoding);
        if (property_exists($dom, 'xmlStandalone')) {
            $dom->xmlStandalone = true;
        }
        $dom->formatOutput = true;
        $dom->preserveWhiteSpace = false;

        $receipts = $dom->createElement('receipts');
        $dom->appendChild($receipts);

        return $dom;
    }

    private function saveDocumentXml(DOMDocument $dom): string
    {
        $xml = $dom->saveXML();
        if ($xml === false) {
            throw new \RuntimeException('Не удалось сформировать XML ЭСФ.');
        }

        return $xml;
    }

    private function collectLinesForKind(LegalEntitySale $sale, string $linesKind): Collection
    {
        $sale->loadMissing('lines.good');
        $lines = $sale->lines;
        if ($linesKind === 'goods') {
            return $lines->filter(fn ($l) => $l->good && ! $l->good->is_service)->values();
        }
        if ($linesKind === 'services') {
            return $lines->filter(fn ($l) => $l->good && $l->good->is_service)->values();
        }

        return $lines->values();
    }

    /**
     * @param  Collection<int, \App\Models\LegalEntitySaleLine>  $lines
     * @param  'all'|'goods'|'services'  $linesKind
     * @param  bool  $splitReceiptNoteAndCrm
     */
    private function appendOneReceipt(
        DOMDocument $dom,
        DOMElement $receipts,
        LegalEntitySale $sale,
        string $paymentKind,
        ?OrganizationBankAccount $paymentAccount,
        string $exchangeCode,
        Collection $lines,
        string $linesKind,
        bool $splitReceiptNoteAndCrm
    ): void {
        $cfg = config('esf');
        $tz = (string) ($cfg['timezone'] ?? 'Asia/Bishkek');

        $receipt = $dom->createElement('receipt');
        $receipts->appendChild($receipt);

        $docDay = CarbonImmutable::parse($sale->document_date->format('Y-m-d'), $tz);
        $createdDate = $docDay->format('Y-m-d');
        $deliveryDate = $createdDate;
        $deliveryContractDate = $docDay->startOfDay()->format('c');

        $paymentTypeCode = (string) (($cfg['payment_type_code'][$paymentKind] ?? $cfg['payment_type_code']['cash']) ?? '10');
        $receiptTypeCode = (string) ($cfg['receipt_type_code'] ?? '10');
        $documentTypeCode = (string) ($cfg['document_type_code'] ?? $receiptTypeCode);

        $bankAccountValue = null;
        if ($paymentKind === 'bank' && $paymentAccount !== null && ! $paymentAccount->isCash()) {
            $acc = trim((string) ($paymentAccount->account_number ?? ''));
            $bankAccountValue = $acc !== '' ? $acc : null;
        }

        $contractorBankAccountValue = null;
        if ($paymentKind === 'bank') {
            $contractorBankAccountValue = $sale->resolvedBuyerBankAccountNumberForEsf();
        }

        $this->appendNil($dom, $receipt, 'amountToBePaid');
        $this->appendNil($dom, $receipt, 'assessedContributionsAmount');
        if ($bankAccountValue !== null) {
            $this->appendText($dom, $receipt, 'bankAccount', $bankAccountValue);
        } else {
            $this->appendNil($dom, $receipt, 'bankAccount');
        }
        $this->appendNil($dom, $receipt, 'closingBalances');
        if ($contractorBankAccountValue !== null) {
            $this->appendText($dom, $receipt, 'contractorBankAccount', $contractorBankAccountValue);
        } else {
            $this->appendNil($dom, $receipt, 'contractorBankAccount');
        }

        $buyerName = (string) ($sale->buyer_name ?? '');
        $contractorPin = $sale->resolvedBuyerPinForEsf();
        $this->appendText($dom, $receipt, 'contractorName', $buyerName);
        $this->appendText($dom, $receipt, 'contractorPin', $contractorPin);

        $this->appendNil($dom, $receipt, 'correctedReceiptCode');
        $this->appendNil($dom, $receipt, 'correctedReceiptCreationDate');
        $this->appendNil($dom, $receipt, 'correctionReasonCode');
        $this->appendNil($dom, $receipt, 'correctionReasonName');

        $this->appendText($dom, $receipt, 'createdDate', $createdDate);
        $this->appendText($dom, $receipt, 'currencyCode', (string) ($cfg['currency_code'] ?? '417'));
        $this->appendText($dom, $receipt, 'currencyName', (string) ($cfg['currency_name'] ?? 'Сом'));
        $this->appendText($dom, $receipt, 'deliveryContractDate', $deliveryContractDate);
        $this->appendText($dom, $receipt, 'deliveryContractNumber', '');
        $this->appendText($dom, $receipt, 'deliveryDate', $deliveryDate);
        $this->appendText($dom, $receipt, 'documentStatusName', (string) ($cfg['document_status_name'] ?? 'Новый'));
        $this->appendText($dom, $receipt, 'exchangeCode', $exchangeCode);
        $this->appendText($dom, $receipt, 'exchangeRate', (string) ($cfg['exchange_rate'] ?? '1.0000'));

        $this->appendNil($dom, $receipt, 'finesAmount');
        $this->appendText($dom, $receipt, 'foreignName', $buyerName);

        $goods = $dom->createElement('goods');
        $receipt->appendChild($goods);

        $stCode = (string) ($cfg['st_code_default'] ?? '50');
        $vatRate = (float) ($cfg['vat_rate_percent'] ?? 0);
        $totalCost = '0';

        foreach ($lines as $line) {
            $good = $dom->createElement('good');
            $goods->appendChild($good);

            $qty = $this->normalizeDecimal((string) $line->quantity);
            $price = $this->normalizeDecimal((string) ($line->unit_price ?? '0'));
            $lineSum = $this->normalizeDecimal((string) ($line->line_sum ?? '0'));
            $totalCost = bcadd($totalCost, $lineSum, 2);

            $this->appendText($dom, $good, 'baseCount', $this->formatDecimals($qty, 5));
            $this->appendText($dom, $good, 'goodsName', (string) $line->name);

            $stAmount = '0.00';
            $vatAmount = '0.00';
            if ($vatRate > 0) {
                $vatAmount = bcmul($lineSum, (string) ($vatRate / (100 + $vatRate)), 2);
                $stAmount = bcsub($lineSum, $vatAmount, 2);
            }

            $this->appendText($dom, $good, 'price', $this->formatDecimals($price, 5));
            $this->appendText($dom, $good, 'stAmount', $this->formatDecimals($stAmount, 2));
            $this->appendText($dom, $good, 'stCode', $stCode);
            $this->appendText($dom, $good, 'vatAmount', $this->formatDecimals($vatAmount, 2));
        }

        $this->appendNil($dom, $receipt, 'invoiceDate');
        $this->appendText($dom, $receipt, 'invoiceDeliveryTypeCode', (string) ($cfg['invoice_delivery_type_code'] ?? '399'));
        $this->appendNil($dom, $receipt, 'invoiceNumber');

        $this->appendText($dom, $receipt, 'isIndustry', (string) ($cfg['is_industry'] ?? 'false'));
        $this->appendText($dom, $receipt, 'isPriceWithoutTaxes', (string) ($cfg['is_price_without_taxes'] ?? 'false'));
        $this->appendText($dom, $receipt, 'isResident', (string) ($cfg['is_resident'] ?? 'true'));

        $this->appendNil($dom, $receipt, 'markGoods');

        $note = trim((string) ($cfg['note'] ?? ''));
        if ($note === '') {
            $note = sprintf((string) ($cfg['note_template'] ?? 'Реализация №%d'), (int) $sale->id);
        }
        if ($splitReceiptNoteAndCrm) {
            $note .= $linesKind === 'goods' ? ' (товары)' : ' (услуги)';
        }
        $this->appendText($dom, $receipt, 'note', $note);

        $this->appendNil($dom, $receipt, 'openingBalances');

        $crmPrefix = trim((string) ($cfg['owned_crm_receipt_code_prefix'] ?? 'LES'));
        $crmPrefix = $crmPrefix !== '' ? $crmPrefix : 'LES';
        $crmSuffix = '';
        if ($splitReceiptNoteAndCrm) {
            $crmSuffix = $linesKind === 'goods' ? '-G' : '-S';
        }
        $this->appendText($dom, $receipt, 'ownedCrmReceiptCode', $crmPrefix.'-'.$sale->id.$crmSuffix);

        $this->appendNil($dom, $receipt, 'paidAmount');

        $this->appendText($dom, $receipt, 'paymentTypeCode', $paymentTypeCode);
        $this->appendNil($dom, $receipt, 'personalAccountNumber');
        $this->appendText($dom, $receipt, 'receiptTypeCode', $receiptTypeCode);
        $this->appendNil($dom, $receipt, 'sellerBranchPin');

        $this->appendText($dom, $receipt, 'totalCost', $this->formatDecimals($totalCost, 2));
        $this->appendText($dom, $receipt, 'type', $documentTypeCode);
        $this->appendText($dom, $receipt, 'vatCode', (string) ($cfg['vat_code'] ?? '100'));
        $this->appendText($dom, $receipt, 'vatDeliveryTypeCode', (string) ($cfg['vat_delivery_type_code'] ?? '102'));
    }

    /** Как в test_esf_realization_real.xml: xsi:nil + xmlns:xsi на каждом пустом поле. */
    private function appendNil(DOMDocument $dom, DOMElement $parent, string $tag): void
    {
        $el = $dom->createElement($tag);
        $el->setAttributeNS(self::XSI_NS, 'xsi:nil', 'true');
        $el->setAttribute('xmlns:xsi', self::XSI_NS);
        $parent->appendChild($el);
    }

    private function appendText(DOMDocument $dom, DOMElement $parent, string $tag, string $value): void
    {
        $el = $dom->createElement($tag);
        $el->appendChild($dom->createTextNode($value));
        $parent->appendChild($el);
    }

    private function formatDecimals(string $value, int $decimals): string
    {
        $n = $this->normalizeDecimal($value);

        return number_format((float) $n, $decimals, '.', '');
    }

    private function normalizeDecimal(string $value): string
    {
        $value = str_replace(',', '.', trim($value));
        if ($value === '' || ! is_numeric($value)) {
            return '0';
        }

        return $value;
    }
}
