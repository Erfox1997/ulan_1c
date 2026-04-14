<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CounterpartyRequest;
use App\Http\Requests\ImportCounterpartiesRequest;
use App\Models\Counterparty;
use App\Services\CounterpartyImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CounterpartyController extends Controller
{
    public function index(): View
    {
        $branchId = (int) auth()->user()->branch_id;

        $counterparties = Counterparty::query()
            ->where('branch_id', $branchId)
            ->withCount('bankAccounts')
            ->orderBy('kind')
            ->orderBy('name')
            ->get();

        return view('admin.counterparties.index', compact('counterparties'));
    }

    public function create(): View
    {
        return view('admin.counterparties.create', $this->formData(new Counterparty([
            'kind' => Counterparty::KIND_SUPPLIER,
            'legal_form' => Counterparty::LEGAL_OSOO,
            'name' => '',
        ]), [[
            'id' => null,
            'account_type' => 'bank',
            'account_number' => '',
            'bank_name' => '',
            'bik' => '',
            'currency' => 'KGS',
        ]]));
    }

    public function store(CounterpartyRequest $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $bankRows = $this->normalizeBankAccounts($request);

        DB::transaction(function () use ($request, $branchId, $bankRows) {
            $counterparty = Counterparty::query()->create(array_merge(
                ['branch_id' => $branchId],
                $request->payload()
            ));
            $this->syncBankAccounts($counterparty, $bankRows, (int) $request->input('default_bank_index', 0));
        });

        return redirect()->route('admin.counterparties.index')
            ->with('status', 'Контрагент добавлен.');
    }

    public function quickStore(Request $request): JsonResponse
    {
        $branchId = auth()->user()->branch_id;
        if ($branchId === null) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:500'],
            'legal_form' => ['required', 'string', Rule::in([
                Counterparty::LEGAL_IP,
                Counterparty::LEGAL_OSOO,
                Counterparty::LEGAL_INDIVIDUAL,
                Counterparty::LEGAL_OTHER,
            ])],
            'kind' => ['sometimes', 'string', Rule::in([
                Counterparty::KIND_SUPPLIER,
                Counterparty::KIND_BUYER,
                Counterparty::KIND_OTHER,
            ])],
        ]);

        $name = trim($validated['name']);
        if ($name === '') {
            return response()->json(['message' => 'Укажите наименование.'], 422);
        }

        $legalForm = $validated['legal_form'];
        $kind = $validated['kind'] ?? Counterparty::KIND_SUPPLIER;

        $counterparty = Counterparty::query()->create([
            'branch_id' => (int) $branchId,
            'kind' => $kind,
            'name' => $name,
            'legal_form' => $legalForm,
            'full_name' => Counterparty::buildFullName($legalForm, $name),
            'inn' => null,
            'phone' => null,
            'address' => null,
        ]);

        return response()->json([
            'id' => $counterparty->id,
            'name' => $counterparty->name,
            'full_name' => $counterparty->full_name,
            'legal_form' => $counterparty->legal_form,
            'kind' => $counterparty->kind,
        ]);
    }

    public function edit(Counterparty $counterparty): View
    {
        $this->authorizeCounterparty($counterparty);

        $rows = $counterparty->bankAccounts->map(fn ($a) => [
            'id' => $a->id,
            'account_type' => $a->account_type ?? 'bank',
            'account_number' => $a->account_number ?? '',
            'bank_name' => $a->bank_name ?? '',
            'bik' => $a->bik,
            'currency' => $a->currency,
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
            ]];
        }

        return view('admin.counterparties.edit', $this->formData($counterparty, $rows));
    }

    public function update(CounterpartyRequest $request, Counterparty $counterparty): RedirectResponse
    {
        $this->authorizeCounterparty($counterparty);

        $bankRows = $this->normalizeBankAccounts($request);

        DB::transaction(function () use ($request, $counterparty, $bankRows) {
            $counterparty->update($request->payload());
            $this->syncBankAccounts($counterparty, $bankRows, (int) $request->input('default_bank_index', 0));
        });

        return redirect()->route('admin.counterparties.index')
            ->with('status', 'Контрагент сохранён.');
    }

    public function destroy(Counterparty $counterparty): RedirectResponse
    {
        $this->authorizeCounterparty($counterparty);
        $counterparty->delete();

        return redirect()->route('admin.counterparties.index')
            ->with('status', 'Контрагент удалён.');
    }

    public function import(ImportCounterpartiesRequest $request, CounterpartyImportService $importService): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $path = $request->file('file')->getRealPath();
        if ($path === false) {
            return back()->withErrors(['file' => 'Не удалось прочитать файл.']);
        }

        $result = $importService->importFromFile($path, $branchId);

        $message = 'Импортировано контрагентов: '.$result['imported'].'.';
        if ($result['skipped'] > 0) {
            $message .= ' Пропущено пустых строк: '.$result['skipped'].'.';
        }

        if ($result['errors'] !== []) {
            session()->flash('import_errors', array_slice($result['errors'], 0, 40));
        }

        return redirect()->route('admin.counterparties.index')
            ->with('status', $message);
    }

    public function sampleImport(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Наименование *', 'Тип', 'Правовая форма', 'Начальный долг', 'ИНН', 'Телефон', 'Адрес'],
            ['Альфа', 'Поставщик', 'ОсОО', '1000.00', '12345678901234', '+996 555 000000', 'г. Бишкек'],
            ['Иванов', 'Покупатель', 'ИП', '0', '', '', ''],
            ['Розница', 'Прочее', 'Физ. лицо', '500,50', '', '', ''],
        ], null, 'A1', true);

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'obrazec_kontragentov.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $bankRows
     * @return array<string, mixed>
     */
    private function formData(Counterparty $counterparty, array $bankRows): array
    {
        $useOld = session('errors')?->any() ?? false;

        if ($useOld) {
            $bankAccounts = old('bank_accounts', $bankRows);
            $defaultBankIndex = (int) old('default_bank_index', $this->defaultBankIndexFromRows(
                is_array($bankAccounts) ? $bankAccounts : $bankRows
            ));
        } else {
            $bankAccounts = $bankRows;
            $defaultBankIndex = $this->defaultBankIndexFromRows($bankRows);
        }

        return [
            'counterparty' => $counterparty,
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
     * @return list<array{id?: int, account_type: string, account_number: ?string, bank_name: ?string, bik: ?string, currency: string}>
     */
    private function normalizeBankAccounts(CounterpartyRequest $request): array
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

    /**
     * @param  list<array{id?: int, account_type: string, account_number: ?string, bank_name: ?string, bik: ?string, currency: string}>  $rows
     */
    private function syncBankAccounts(Counterparty $counterparty, array $rows, int $defaultBankIndex): void
    {
        $idsToKeep = collect($rows)->pluck('id')->filter()->all();
        $counterparty->bankAccounts()->whereNotIn('id', $idsToKeep)->delete();

        foreach ($rows as $i => $row) {
            $isDefault = $i === $defaultBankIndex && count($rows) > 0;
            $payload = [
                'account_type' => $row['account_type'],
                'account_number' => $row['account_number'],
                'bank_name' => $row['bank_name'],
                'bik' => $row['bik'],
                'currency' => $row['currency'],
                'is_default' => $isDefault,
                'sort_order' => $i,
            ];

            if (! empty($row['id'])) {
                $account = $counterparty->bankAccounts()->where('id', $row['id'])->first();
                if ($account) {
                    $account->update($payload);
                }
            } else {
                $counterparty->bankAccounts()->create($payload);
            }
        }

        if ($counterparty->bankAccounts()->exists() && $counterparty->bankAccounts()->where('is_default', true)->doesntExist()) {
            $counterparty->bankAccounts()->orderBy('sort_order')->first()?->update(['is_default' => true]);
        }
    }

    private function authorizeCounterparty(Counterparty $counterparty): void
    {
        if ((int) $counterparty->branch_id !== (int) auth()->user()->branch_id) {
            abort(403);
        }
    }
}
