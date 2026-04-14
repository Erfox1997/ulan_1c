<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalEntitySale;
use App\Models\Organization;
use App\Models\OrganizationBankAccount;
use App\Services\Esf\EsfXmlGenerator;
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

    public function index(): View
    {
        $branchId = (int) auth()->user()->branch_id;

        $sales = LegalEntitySale::query()
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

        $salesPending = $sales->filter(fn (LegalEntitySale $s) => $s->esf_submitted_at === null)->values();
        $salesRecorded = $sales->filter(fn (LegalEntitySale $s) => $s->esf_submitted_at !== null)->values();
        $esfTabDefault = $salesPending->isNotEmpty() ? 'pending' : 'recorded';

        return view('admin.esf.index', [
            'sales' => $sales,
            'salesPending' => $salesPending,
            'salesRecorded' => $salesRecorded,
            'esfTabDefault' => $esfTabDefault,
            'organizations' => $organizations,
            'orgsPayload' => $orgsPayload,
        ]);
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

    public function markSubmitted(LegalEntitySale $legalEntitySale): RedirectResponse
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
                ->with('status', 'Эта реализация уже отмечена как записанная в ЭСФ.');
        }

        $legalEntitySale->forceFill(['esf_submitted_at' => now()])->save();

        return redirect()
            ->route('admin.esf.index')
            ->with('status', 'Отмечено: ЭСФ записана в налоговой по документу № '.$legalEntitySale->id.'.');
    }

    public function unmarkSubmitted(LegalEntitySale $legalEntitySale): RedirectResponse
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

        return redirect()
            ->route('admin.esf.index')
            ->with('status', 'Отметка «записано в ЭСФ» снята. При следующей выгрузке будет новый код обмена (exchangeCode).');
    }
}
