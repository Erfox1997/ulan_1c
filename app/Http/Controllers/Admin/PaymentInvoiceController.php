<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Counterparty;
use App\Models\LegalEntitySale;
use App\Models\Organization;
use App\Models\OrganizationBankAccount;
use App\Models\Warehouse;
use App\Support\InvoiceNakladnayaFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use InvalidArgumentException;

class PaymentInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $selectedWarehouseId = (int) $request->integer('warehouse_id') ?: 0;
        $defaultId = $warehouses->firstWhere('is_default')?->id ?? $warehouses->first()?->id;

        if ($selectedWarehouseId === 0 || ! $warehouses->contains('id', $selectedWarehouseId)) {
            $selectedWarehouseId = (int) ($defaultId ?? 0);
        }

        $counterpartyId = (int) $request->integer('counterparty_id') ?: 0;
        $counterparty = null;
        if ($counterpartyId > 0) {
            $counterparty = Counterparty::query()
                ->where('branch_id', $branchId)
                ->whereKey($counterpartyId)
                ->first();
            if ($counterparty === null) {
                $counterpartyId = 0;
            }
        }

        $buyerFilterAliases = $counterparty !== null ? $counterparty->legalSaleBuyerNameAliases() : [];

        $invoiceSentTab = $request->query('sent') === '1' ? 'sent' : 'pending';

        $sales = LegalEntitySale::query()
            ->where('branch_id', $branchId)
            ->where('payment_invoice_sent', $invoiceSentTab === 'sent')
            ->when($selectedWarehouseId > 0, fn ($q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->when($buyerFilterAliases !== [], fn ($q) => $q->whereIn('buyer_name', $buyerFilterAliases))
            ->with(['warehouse', 'lines'])
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $selectedCounterpartyLabel = '';
        if ($counterparty !== null) {
            $fn = trim((string) $counterparty->full_name);
            $selectedCounterpartyLabel = $fn !== '' ? $fn : trim((string) $counterparty->name);
        }

        $organizations = Organization::query()
            ->where('branch_id', $branchId)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.trade-invoices.index', [
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'selectedCounterpartyId' => $counterpartyId,
            'selectedCounterpartyLabel' => $selectedCounterpartyLabel,
            'invoiceSentTab' => $invoiceSentTab,
            'sales' => $sales,
            'organizations' => $organizations,
            'defaultPrintOrganizationId' => $organizations->first()?->id,
        ]);
    }

    public function updateInvoiceSent(Request $request, LegalEntitySale $legalEntitySale): RedirectResponse
    {
        $legalEntitySale->update([
            'payment_invoice_sent' => $request->boolean('payment_invoice_sent'),
        ]);

        $redirectQuery = $this->tradeInvoiceIndexRedirectQuery($request);

        return redirect()
            ->route('admin.trade-invoices.index', $redirectQuery)
            ->with('status', 'Отметка сохранена.');
    }

    public function bulkUpdateInvoiceSent(Request $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;

        $ids = collect($request->input('sale_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->withErrors(['sale_ids' => 'Отметьте хотя бы одну продажу в списке.']);
        }

        $count = LegalEntitySale::query()
            ->where('branch_id', $branchId)
            ->whereIn('id', $ids)
            ->count();

        if ($count !== $ids->count()) {
            return back()->withErrors(['sale_ids' => 'Часть документов не найдена или недоступна.']);
        }

        LegalEntitySale::query()
            ->where('branch_id', $branchId)
            ->whereIn('id', $ids)
            ->update(['payment_invoice_sent' => $request->boolean('payment_invoice_sent')]);

        $redirectQuery = $this->tradeInvoiceIndexRedirectQuery($request);

        return redirect()
            ->route('admin.trade-invoices.index', $redirectQuery)
            ->with('status', 'Обновлено отметок: '.$ids->count().' шт.');
    }

    public function mergedPrint(Request $request): View
    {
        try {
            $orgId = $this->parseOrganizationQueryId($request);

            return view('admin.trade-invoices.invoice-merged', array_merge(
                $this->mergedInvoiceViewData($request, $orgId),
                ['forPdf' => false]
            ));
        } catch (InvalidArgumentException $e) {
            return response()->view('admin.trade-invoices.merge-invoice-error', [
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function mergedPdf(Request $request)
    {
        try {
            $orgId = $this->parseOrganizationQueryId($request);
            $data = array_merge($this->mergedInvoiceViewData($request, $orgId), ['forPdf' => true]);
            $ids = $data['saleIds'];
            sort($ids);
            $filename = 'schet-obedinennyj-'.$ids[0].'-'.$ids[count($ids) - 1].'.pdf';

            return Pdf::loadView('admin.trade-invoices.invoice-merged', $data)
                ->setPaper('a4', 'portrait')
                ->download($filename);
        } catch (InvalidArgumentException $e) {
            return response()->view('admin.trade-invoices.merge-invoice-error', [
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function print(Request $request, LegalEntitySale $legalEntitySale): View
    {
        $orgId = $this->parseOrganizationQueryId($request);

        return view('admin.trade-invoices.invoice', array_merge(
            $this->invoiceViewData($legalEntitySale, $orgId),
            ['forPdf' => false]
        ));
    }

    public function pdf(Request $request, LegalEntitySale $legalEntitySale)
    {
        $orgId = $this->parseOrganizationQueryId($request);
        $data = array_merge($this->invoiceViewData($legalEntitySale, $orgId), ['forPdf' => true]);
        $filename = 'schet-'.$legalEntitySale->id.'-'.$legalEntitySale->document_date->format('Y-m-d').'.pdf';

        return Pdf::loadView('admin.trade-invoices.invoice', $data)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    /**
     * @return array<string, int|string>
     */
    private function tradeInvoiceIndexRedirectQuery(Request $request): array
    {
        $redirectQuery = array_filter([
            'warehouse_id' => (int) $request->input('return_warehouse_id') ?: null,
            'counterparty_id' => (int) $request->input('return_counterparty_id') ?: null,
        ], static fn ($v) => $v !== null && $v > 0);
        if ($request->input('return_sent') === '1' || $request->input('return_sent') === 1) {
            $redirectQuery['sent'] = 1;
        }

        return $redirectQuery;
    }

    private function parseOrganizationQueryId(Request $request): ?int
    {
        $raw = $request->query('organization_id');
        if ($raw === null || $raw === '') {
            return null;
        }
        $id = (int) $raw;

        return $id > 0 ? $id : null;
    }

    /**
     * @return Collection<int, int>
     */
    private function parseSaleIdsFromRequest(Request $request): Collection
    {
        $raw = $request->query('sale_ids');
        if (is_string($raw) && str_contains($raw, ',')) {
            return collect(explode(',', $raw))
                ->map(fn ($v) => (int) trim((string) $v))
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values();
        }
        if (is_string($raw) && preg_match('/^\d+$/', trim($raw)) === 1) {
            $one = (int) trim($raw);

            return $one > 0 ? collect([$one]) : collect();
        }
        $arr = $request->query('sale_ids', []);
        if (! is_array($arr)) {
            $arr = [];
        }

        return collect($arr)
            ->map(fn ($v) => (int) $v)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function mergedInvoiceViewData(Request $request, ?int $organizationId = null): array
    {
        $branchId = (int) auth()->user()->branch_id;
        $ids = $this->parseSaleIdsFromRequest($request);

        if ($ids->count() < 2) {
            throw new InvalidArgumentException('Для объединённого счёта отметьте в таблице не менее двух реализаций.');
        }

        $sales = LegalEntitySale::query()
            ->where('branch_id', $branchId)
            ->whereIn('id', $ids)
            ->with(['lines', 'warehouse'])
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->get();

        if ($sales->count() !== $ids->count()) {
            throw new InvalidArgumentException('Часть документов не найдена или недоступна.');
        }

        $buyerKeys = $sales->map(fn (LegalEntitySale $s) => mb_strtolower(trim((string) $s->buyer_name)))->unique()->values();
        if ($buyerKeys->count() > 1) {
            throw new InvalidArgumentException('Объединение возможно только если у всех выбранных реализаций один и тот же покупатель в поле «Покупатель».');
        }

        $buyerName = (string) ($sales->first()->buyer_name ?? '');

        $salesOrdered = $sales->sortBy('id')->values();

        $totalSum = '0';
        foreach ($salesOrdered as $sale) {
            foreach ($sale->lines as $line) {
                if ($line->line_sum !== null) {
                    $totalSum = bcadd($totalSum, (string) $line->line_sum, 2);
                }
            }
        }

        $branch = auth()->user()->branch;
        if ($branch === null) {
            abort(403);
        }

        $printOrganizations = Organization::query()
            ->where('branch_id', $branch->id)
            ->with(['bankAccounts' => fn ($q) => $q->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id')])
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $organization = null;
        if ($organizationId !== null) {
            $organization = $printOrganizations->firstWhere('id', $organizationId);
        }
        if ($organization === null) {
            $organization = $printOrganizations->first();
        }

        $bankAccount = null;
        if ($organization !== null) {
            $bankAccount = $organization->bankAccounts->first(fn (OrganizationBankAccount $a) => $a->isBank())
                ?? $organization->bankAccounts->first();
        }

        /** @var \Carbon\Carbon $latestDate */
        $latestDate = $sales->max('document_date');
        $saleIdsSorted = $ids->sort()->values()->all();

        $documentTitle = InvoiceNakladnayaFormatter::mergedPaymentInvoiceDocumentTitle($latestDate, $saleIdsSorted);

        return [
            'salesOrdered' => $salesOrdered,
            'saleIds' => $saleIdsSorted,
            'buyerName' => $buyerName,
            'documentTitle' => $documentTitle,
            'branch' => $branch,
            'organization' => $organization,
            'bankAccount' => $bankAccount,
            'printOrganizations' => $printOrganizations,
            'totalSum' => $totalSum,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceViewData(LegalEntitySale $legalEntitySale, ?int $organizationId = null): array
    {
        $legalEntitySale->load(['lines.good', 'warehouse', 'branch']);

        $branch = auth()->user()->branch;
        if ($branch === null) {
            abort(403);
        }

        $printOrganizations = Organization::query()
            ->where('branch_id', $branch->id)
            ->with(['bankAccounts' => fn ($q) => $q->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id')])
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $organization = null;
        if ($organizationId !== null) {
            $organization = $printOrganizations->firstWhere('id', $organizationId);
        }
        if ($organization === null) {
            $organization = $printOrganizations->first();
        }

        $bankAccount = null;
        if ($organization !== null) {
            $bankAccount = $organization->bankAccounts->first(fn (OrganizationBankAccount $a) => $a->isBank())
                ?? $organization->bankAccounts->first();
        }

        $lines = $legalEntitySale->lines;
        $totalSum = '0';
        foreach ($lines as $line) {
            if ($line->line_sum !== null) {
                $totalSum = bcadd($totalSum, (string) $line->line_sum, 2);
            }
        }

        return [
            'legalEntitySale' => $legalEntitySale,
            'branch' => $branch,
            'organization' => $organization,
            'bankAccount' => $bankAccount,
            'printOrganizations' => $printOrganizations,
            'lines' => $lines,
            'totalSum' => $totalSum,
        ];
    }
}
