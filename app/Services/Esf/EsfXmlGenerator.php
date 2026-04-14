<?php

namespace App\Services\Esf;

use App\Models\LegalEntitySale;
use App\Models\Organization;
use App\Models\OrganizationBankAccount;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
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
     */
    public function build(
        LegalEntitySale $sale,
        Organization $seller,
        string $paymentKind,
        ?OrganizationBankAccount $paymentAccount = null,
        ?string $exchangeCode = null
    ): string {
        $cfg = config('esf');
        $encoding = $cfg['encoding'] ?? 'UTF-8';
        $tz = (string) ($cfg['timezone'] ?? 'Asia/Bishkek');

        $sale->loadMissing('lines');

        $dom = new DOMDocument('1.0', $encoding);
        if (property_exists($dom, 'xmlStandalone')) {
            $dom->xmlStandalone = true;
        }
        $dom->formatOutput = true;
        $dom->preserveWhiteSpace = false;

        $receipts = $dom->createElement('receipts');
        $dom->appendChild($receipts);

        $receipt = $dom->createElement('receipt');
        $receipts->appendChild($receipt);

        $docDay = CarbonImmutable::parse($sale->document_date->format('Y-m-d'), $tz);
        $createdDate = $docDay->format('Y-m-d');
        $deliveryDate = $createdDate;
        $deliveryContractDate = $docDay->startOfDay()->format('c');
        $exchangeCode = ($exchangeCode !== null && trim($exchangeCode) !== '')
            ? trim($exchangeCode)
            : (string) Str::uuid();

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

        // Порядок и набор узлов как в test_esf_realization_real.xml (без contractorBranchName / citizenship перед именем).
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

        foreach ($sale->lines as $line) {
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
        $this->appendText($dom, $receipt, 'note', $note);

        $this->appendNil($dom, $receipt, 'openingBalances');

        $crmPrefix = trim((string) ($cfg['owned_crm_receipt_code_prefix'] ?? 'LES'));
        $crmPrefix = $crmPrefix !== '' ? $crmPrefix : 'LES';
        $this->appendText($dom, $receipt, 'ownedCrmReceiptCode', $crmPrefix.'-'.$sale->id);

        $this->appendNil($dom, $receipt, 'paidAmount');

        $this->appendText($dom, $receipt, 'paymentTypeCode', $paymentTypeCode);
        $this->appendNil($dom, $receipt, 'personalAccountNumber');
        $this->appendText($dom, $receipt, 'receiptTypeCode', $receiptTypeCode);
        $this->appendNil($dom, $receipt, 'sellerBranchPin');

        $this->appendText($dom, $receipt, 'totalCost', $this->formatDecimals($totalCost, 2));
        $this->appendText($dom, $receipt, 'type', $documentTypeCode);
        $this->appendText($dom, $receipt, 'vatCode', (string) ($cfg['vat_code'] ?? '100'));
        $this->appendText($dom, $receipt, 'vatDeliveryTypeCode', (string) ($cfg['vat_delivery_type_code'] ?? '102'));

        $xml = $dom->saveXML();
        if ($xml === false) {
            throw new \RuntimeException('Не удалось сформировать XML ЭСФ.');
        }

        return $xml;
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
