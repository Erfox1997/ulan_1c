<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalEntitySale;
use App\Models\Organization;
use App\Models\OrganizationBankAccount;
use App\Services\Esf\EsfXmlGenerator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EsfController extends Controller
{
    public function __construct(
        private readonly EsfXmlGenerator $esfXmlGenerator
    ) {}

    public function index(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $availableFilterError = null;
        if (($dateFrom && ! $dateTo) || (! $dateFrom && $dateTo)) {
            $availableFilterError = 'Укажите обе даты периода или оставьте поля пустыми (тогда показываются последние 50 документов).';
            $dateFrom = null;
            $dateTo = null;
        }
        if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
            $availableFilterError = 'Дата «с» не может быть позже даты «по».';
            $dateFrom = null;
            $dateTo = null;
        }

        $availableQuery = LegalEntitySale::query()
            ->where('branch_id', $branchId)
            ->where('issue_esf', false)
            ->with(['warehouse', 'lines']);

        if ($dateFrom && $dateTo) {
            $availableQuery
                ->whereDate('document_date', '>=', $dateFrom)
                ->whereDate('document_date', '<=', $dateTo)
                ->orderByDesc('document_date')
                ->orderByDesc('id')
                ->limit(500);
            $availableListHint = 'Период: с '.Carbon::parse($dateFrom)->format('d.m.Y').' по '.Carbon::parse($dateTo)->format('d.m.Y').', не более 500 строк.';
        } else {
            $availableQuery
                ->orderByDesc('document_date')
                ->orderByDesc('id')
                ->limit(50);
            $availableListHint = 'Без периода: последние 50 реализаций по дате документа.';
        }

        $salesAvailable = $availableQuery->get();

        $salesInEsfQueue = LegalEntitySale::query()
            ->where('branch_id', $branchId)
            ->where('issue_esf', true)
            ->with(['warehouse', 'lines'])
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->get();

        $organizations = Organization::query()
            ->where('branch_id', $branchId)
            ->with(['bankAccounts' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $orgsPayload = $organizations->map(fn (Organization $o) => [
            'id' => $o->id,
            'name' => $o->name,
            'accounts' => $o->bankAccounts->map(fn (OrganizationBankAccount $a) => [
                'id' => $a->id,
                'type' => $a->account_type,
                'label' => $a->summaryLabel(),
            ])->values()->all(),
        ])->values()->all();

        $salesPending = $salesInEsfQueue->filter(fn (LegalEntitySale $s) => $s->esf_submitted_at === null)->values();
        $salesRecorded = $salesInEsfQueue->filter(fn (LegalEntitySale $s) => $s->esf_submitted_at !== null)->values();
        $esfTabDefault = $salesPending->isNotEmpty() ? 'pending' : 'recorded';

        $hasAnyLegalSales = LegalEntitySale::query()
            ->where('branch_id', $branchId)
            ->exists();

        return view('admin.esf.index', [
            'salesAvailable' => $salesAvailable,
            'salesPending' => $salesPending,
            'salesRecorded' => $salesRecorded,
            'hasAnyLegalSales' => $hasAnyLegalSales,
            'esfTabDefault' => $esfTabDefault,
            'organizations' => $organizations,
            'orgsPayload' => $orgsPayload,
            'availableFilterDateFrom' => $dateFrom,
            'availableFilterDateTo' => $dateTo,
            'availableFilterError' => $availableFilterError,
            'availableListHint' => $availableListHint,
            'esfFilter' => [
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
            ],
        ]);
    }

    public function queueBulk(Request $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => [
                'integer',
                'distinct',
                Rule::exists('legal_entity_sales', 'id')->where(fn ($q) => $q
                    ->where('branch_id', $branchId)
                    ->where('issue_esf', false)),
            ],
            'date_from' => ['nullable', 'string'],
            'date_to' => ['nullable', 'string'],
        ]);

        $ids = array_values(array_map('intval', $validated['ids']));

        $affected = LegalEntitySale::query()
            ->where('branch_id', $branchId)
            ->where('issue_esf', false)
            ->whereIn('id', $ids)
            ->update(['issue_esf' => true]);

        return $this->redirectToEsfIndex($request)->with(
            'status',
            'Отмечено для ЭСФ документов: '.$affected.'.'
        );
    }

    public function queueForEsf(Request $request, LegalEntitySale $legalEntitySale): RedirectResponse
    {
        if ((int) $legalEntitySale->branch_id !== (int) auth()->user()->branch_id) {
            abort(403);
        }

        if ($legalEntitySale->issue_esf) {
            return $this->redirectToEsfIndex($request)->with('status', 'По этому документу уже отмечено: требуется ЭСФ.');
        }

        $legalEntitySale->forceFill(['issue_esf' => true])->save();

        return $this->redirectToEsfIndex($request)->with(
            'status',
            'Документ № '.$legalEntitySale->id.' отмечен: нужно выписать ЭСФ. Перейдите на вкладку «Нужно записать».'
        );
    }

    public function unqueueFromEsf(Request $request, LegalEntitySale $legalEntitySale): RedirectResponse
    {
        if ((int) $legalEntitySale->branch_id !== (int) auth()->user()->branch_id) {
            abort(403);
        }

        if (! $legalEntitySale->issue_esf) {
            return $this->redirectToEsfIndex($request)->with('error', 'Документ не был в очереди на ЭСФ.');
        }

        if ($legalEntitySale->esf_submitted_at !== null) {
            return $this->redirectToEsfIndex($request)->with(
                'error',
                'Нельзя убрать из очереди: ЭСФ уже отмечена как записанная в налоговой. Сначала снимите отметку «записано» на вкладке «Записано в ЭСФ».'
            );
        }

        $legalEntitySale->forceFill([
            'issue_esf' => false,
            'esf_exchange_code' => null,
        ])->save();

        return $this->redirectToEsfIndex($request)->with(
            'status',
            'Документ № '.$legalEntitySale->id.' убран из очереди на ЭСФ.'
        );
    }

    private function redirectToEsfIndex(Request $request): RedirectResponse
    {
        $q = array_filter([
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ], static fn ($v) => $v !== null && $v !== '');

        return redirect()->route('admin.esf.index', $q);
    }

    public function downloadXml(Request $request, LegalEntitySale $legalEntitySale): Response|RedirectResponse
    {
        if ((int) $legalEntitySale->branch_id !== (int) auth()->user()->branch_id) {
            abort(403);
        }

        if (! $legalEntitySale->issue_esf) {
            abort(404);
        }

        if ($legalEntitySale->esf_submitted_at !== null) {
            return redirect()
                ->route('admin.esf.index')
                ->with('error', 'По этой реализации ЭСФ уже отмечена как записанная. Снимите отметку, если нужно сформировать файл заново.');
        }

        if (strlen($legalEntitySale->resolvedBuyerPinForEsf()) < 10) {
            return redirect()
                ->route('admin.esf.index')
                ->with(
                    'error',
                    'Не удалось определить ИНН/ПИН покупателя для XML (contractorPin). Укажите ИНН в карточке контрагента в «Контрагенты» и совпадающее наименование покупателя в реализации, либо введите ИНН в поле «ИНН/ПИН покупателя» в документе.'
                );
        }

        $branchId = (int) auth()->user()->branch_id;

        $validated = $request->validate([
            'organization_id' => [
                'required',
                'integer',
                Rule::exists('organizations', 'id')->where(fn ($q) => $q->where('branch_id', $branchId)),
            ],
            'payment_kind' => ['required', Rule::in(['cash', 'bank'])],
            'organization_bank_account_id' => ['nullable', 'integer'],
        ]);

        /** @var Organization $seller */
        $seller = Organization::query()
            ->where('branch_id', $branchId)
            ->whereKey($validated['organization_id'])
            ->firstOrFail();

        $paymentKind = $validated['payment_kind'];
        $accountId = $validated['organization_bank_account_id'] ?? null;

        $paymentAccount = null;
        if ($paymentKind === 'bank') {
            if ($accountId === null) {
                return redirect()
                    ->route('admin.esf.index')
                    ->with('error', 'Для безнала выберите банковский счёт организации.');
            }
            $paymentAccount = OrganizationBankAccount::query()
                ->where('organization_id', $seller->id)
                ->whereKey($accountId)
                ->first();
            if ($paymentAccount === null) {
                return redirect()
                    ->route('admin.esf.index')
                    ->with('error', 'Указанный счёт не принадлежит выбранной организации.');
            }
            if ($paymentAccount->isCash()) {
                return redirect()
                    ->route('admin.esf.index')
                    ->with('error', 'Для безнала выберите банковский счёт (не «наличные»).');
            }

            if ($legalEntitySale->resolvedBuyerBankAccountNumberForEsf() === null) {
                return redirect()
                    ->route('admin.esf.index')
                    ->with(
                        'error',
                        'Для безнала в XML нужен банковский счёт покупателя (поле contractorBankAccount). Добавьте счёт в карточке контрагента или выберите покупателя из подсказки, чтобы документ был связан с контрагентом.'
                    );
            }
        }

        if ($paymentKind === 'cash' && $accountId !== null) {
            $paymentAccount = OrganizationBankAccount::query()
                ->where('organization_id', $seller->id)
                ->whereKey($accountId)
                ->first();
            if ($paymentAccount === null) {
                return redirect()
                    ->route('admin.esf.index')
                    ->with('error', 'Указанный счёт не принадлежит выбранной организации.');
            }
            if (! $paymentAccount->isCash()) {
                return redirect()
                    ->route('admin.esf.index')
                    ->with('error', 'Для наличных выберите счёт с типом «наличные» или оставьте счёт пустым.');
            }
        }

        $legalEntitySale->load('lines');

        $randomExchange = (bool) config('esf.random_exchange_code_each_download', true);
        if ($randomExchange) {
            $exchangeCode = (string) Str::uuid();
        } else {
            if (blank($legalEntitySale->esf_exchange_code)) {
                $legalEntitySale->forceFill(['esf_exchange_code' => (string) Str::uuid()])->save();
                $legalEntitySale->refresh();
            }
            $exchangeCode = (string) $legalEntitySale->esf_exchange_code;
        }

        $xml = $this->esfXmlGenerator->build(
            $legalEntitySale,
            $seller,
            $paymentKind,
            $paymentAccount,
            $exchangeCode
        );

        $fileName = sprintf(
            'ESF_%d_%s.xml',
            $legalEntitySale->id,
            $legalEntitySale->document_date->format('Y-m-d')
        );

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    public function markSubmitted(Request $request, LegalEntitySale $legalEntitySale): RedirectResponse
    {
        if ((int) $legalEntitySale->branch_id !== (int) auth()->user()->branch_id) {
            abort(403);
        }

        if (! $legalEntitySale->issue_esf) {
            abort(404);
        }

        if ($legalEntitySale->esf_submitted_at !== null) {
            return $this->redirectToEsfIndex($request)->with('status', 'Эта реализация уже отмечена как записанная в ЭСФ.');
        }

        $legalEntitySale->forceFill(['esf_submitted_at' => now()])->save();

        return $this->redirectToEsfIndex($request)->with(
            'status',
            'Отмечено: ЭСФ записана в налоговой по документу № '.$legalEntitySale->id.'.'
        );
    }

    public function unmarkSubmitted(Request $request, LegalEntitySale $legalEntitySale): RedirectResponse
    {
        if ((int) $legalEntitySale->branch_id !== (int) auth()->user()->branch_id) {
            abort(403);
        }

        if (! $legalEntitySale->issue_esf) {
            abort(404);
        }

        $legalEntitySale->forceFill([
            'esf_submitted_at' => null,
            'esf_exchange_code' => null,
        ])->save();

        return $this->redirectToEsfIndex($request)->with(
            'status',
            'Отметка «записано в ЭСФ» снята. При следующей выгрузке будет новый код обмена (exchangeCode).'
        );
    }
}
