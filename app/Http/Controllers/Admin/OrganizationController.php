<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizationRequest;
use App\Models\Organization;
use App\Services\OpeningBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function __construct(
        private readonly OpeningBalanceService $openingBalanceService
    ) {}

    public function index(): View
    {
        $branchId = auth()->user()->branch_id;
        $organizations = Organization::query()
            ->where('branch_id', $branchId)
            ->with('bankAccounts')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.organizations.index', compact('organizations'));
    }

    public function create(): View
    {
        return view('admin.organizations.create', $this->formData(new Organization, [
            [
                'id' => null,
                'account_type' => 'bank',
                'account_number' => '',
                'bank_name' => '',
                'bik' => '',
                'currency' => 'KGS',
                'opening_balance' => '',
            ],
        ]));
    }

    public function store(OrganizationRequest $request): RedirectResponse
    {
        $branchId = auth()->user()->branch_id;
        $data = $this->organizationPayload($request);
        $bankRows = $this->normalizeBankAccounts($request);

        DB::transaction(function () use ($branchId, $data, $bankRows, $request) {
            $data['branch_id'] = $branchId;
            $org = Organization::create($data);
            $this->applyDefaultOrganizationFlag($org, $request->boolean('is_default'));
            $this->syncBankAccounts($org, $bankRows, (int) $request->input('default_bank_index', 0));
        });

        return redirect()->route('admin.organizations.index')
            ->with('status', 'Организация добавлена.');
    }

    public function edit(Organization $organization): View
    {
        $rows = $organization->bankAccounts->map(fn ($a) => [
            'id' => $a->id,
            'account_type' => $a->account_type ?? 'bank',
            'account_number' => $a->account_number ?? '',
            'bank_name' => $a->bank_name ?? '',
            'bik' => $a->bik,
            'currency' => $a->currency,
            'opening_balance' => $a->opening_balance !== null ? (string) $a->opening_balance : '',
            'is_default' => $a->is_default,
        ])->values()->all();

        if ($rows === []) {
            $rows = [[
                'id' => null,
                'account_type' => 'bank',
                'account_number' => '',
                'bank_name' => '',
                'bik' => '',
                'currency' => 'KGS',
                'opening_balance' => '',
            ]];
        }

        return view('admin.organizations.edit', $this->formData($organization, $rows));
    }

    public function update(OrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $data = $this->organizationPayload($request);
        $bankRows = $this->normalizeBankAccounts($request);

        DB::transaction(function () use ($organization, $data, $bankRows, $request) {
            $organization->update($data);
            $this->applyDefaultOrganizationFlag($organization, $request->boolean('is_default'));
            $this->syncBankAccounts($organization, $bankRows, (int) $request->input('default_bank_index', 0));
        });

        return redirect()->route('admin.organizations.index')
            ->with('status', 'Данные организации сохранены.');
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $organization->delete();

        return redirect()->route('admin.organizations.index')
            ->with('status', 'Организация удалена.');
    }

    /**
     * @param  list<array<string, mixed>>  $bankRows
     * @return array<string, mixed>
     */
    private function formData(Organization $organization, array $bankRows): array
    {
        $bankAccounts = old('bank_accounts', $bankRows);

        $defaultBankIndex = (int) old('default_bank_index', $this->defaultBankIndexFromRows(
            is_array($bankAccounts) ? $bankAccounts : $bankRows
        ));

        return [
            'organization' => $organization,
            'bankAccounts' => is_array($bankAccounts) ? $bankAccounts : $bankRows,
            'defaultBankIndex' => $defaultBankIndex,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function defaultBankIndexFromRows(array $rows): int
    {
        foreach ($rows as $i => $row) {
            if (! empty($row['is_default'])) {
                return $i;
            }
        }

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationPayload(OrganizationRequest $request): array
    {
        return [
            'name' => $request->input('name'),
            'short_name' => $request->input('short_name'),
            'legal_form' => $request->input('legal_form'),
            'inn' => $request->input('inn'),
            'legal_address' => $request->input('legal_address'),
            'phone' => $request->input('phone'),
            'notes' => $request->input('notes'),
            'is_default' => $request->boolean('is_default'),
            'sort_order' => (int) $request->input('sort_order', 0),
        ];
    }

    /**
     * @return list<array{id?: int, account_type: string, account_number: ?string, bank_name: ?string, bik: ?string, currency: string}>
     */
    private function normalizeBankAccounts(OrganizationRequest $request): array
    {
        $rows = $request->input('bank_accounts', []);
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = ($row['account_type'] ?? 'bank') === 'cash' ? 'cash' : 'bank';

            if ($type === 'cash') {
                $label = trim((string) ($row['account_number'] ?? ''));
                $item = [
                    'account_type' => 'cash',
                    'account_number' => $label !== '' ? $label : null,
                    'bank_name' => null,
                    'bik' => null,
                    'currency' => strtoupper((string) ($row['currency'] ?? 'KGS')) ?: 'KGS',
                    'opening_balance' => $this->parseOpeningBalanceForRow($row['opening_balance'] ?? null),
                ];
                if (! empty($row['id']) && (int) $row['id'] > 0) {
                    $item['id'] = (int) $row['id'];
                }
                $out[] = $item;

                continue;
            }

            $accountNumber = trim((string) ($row['account_number'] ?? ''));
            $bankName = trim((string) ($row['bank_name'] ?? ''));
            if ($accountNumber === '' && $bankName === '') {
                continue;
            }
            $item = [
                'account_type' => 'bank',
                'account_number' => $accountNumber,
                'bank_name' => $bankName,
                'bik' => $this->nullableString($row['bik'] ?? null),
                'currency' => strtoupper((string) ($row['currency'] ?? 'KGS')) ?: 'KGS',
                'opening_balance' => $this->parseOpeningBalanceForRow($row['opening_balance'] ?? null),
            ];
            if (! empty($row['id']) && (int) $row['id'] > 0) {
                $item['id'] = (int) $row['id'];
            }
            $out[] = $item;
        }

        return $out;
    }

    private function nullableString(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return (string) $v;
    }

    private function parseOpeningBalanceForRow(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return $this->openingBalanceService->parseOptionalMoney($raw);
    }

    private function applyDefaultOrganizationFlag(Organization $org, bool $isDefault): void
    {
        if (! $isDefault) {
            return;
        }

        Organization::query()
            ->where('branch_id', $org->branch_id)
            ->where('id', '!=', $org->id)
            ->update(['is_default' => false]);

        $org->update(['is_default' => true]);
    }

    /**
     * @param  list<array{id?: int, account_type: string, account_number: ?string, bank_name: ?string, bik: ?string, currency: string}>  $rows
     */
    private function syncBankAccounts(Organization $org, array $rows, int $defaultBankIndex): void
    {
        $idsToKeep = collect($rows)->pluck('id')->filter()->all();
        $org->bankAccounts()->whereNotIn('id', $idsToKeep)->delete();

        foreach ($rows as $i => $row) {
            $isDefault = $i === $defaultBankIndex && count($rows) > 0;
            $payload = [
                'account_type' => $row['account_type'],
                'account_number' => $row['account_number'],
                'bank_name' => $row['bank_name'],
                'bik' => $row['bik'],
                'currency' => $row['currency'],
                'opening_balance' => $row['opening_balance'] ?? null,
                'is_default' => $isDefault,
                'sort_order' => $i,
            ];

            if (! empty($row['id'])) {
                $account = $org->bankAccounts()->where('id', $row['id'])->first();
                if ($account) {
                    $account->update($payload);
                }
            } else {
                $org->bankAccounts()->create($payload);
            }
        }

        if ($org->bankAccounts()->exists() && $org->bankAccounts()->where('is_default', true)->doesntExist()) {
            $org->bankAccounts()->orderBy('sort_order')->first()?->update(['is_default' => true]);
        }
    }
}
