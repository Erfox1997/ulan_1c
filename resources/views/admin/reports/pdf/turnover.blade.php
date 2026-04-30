@php
    use App\Support\InvoiceNakladnayaFormatter;

    /** @var callable(float,float): array{dt:string,ct:string,dtNeg:bool,ctNeg:bool} $saldoCells */
    $saldoCells = static function (float $dt, float $ct): array {
        $net = round((float) $dt - (float) $ct, 2);
        if (abs($net) < 0.005) {
            return ['dt' => '—', 'ct' => '—', 'dtNeg' => false, 'ctNeg' => false];
        }
        if ($net > 0) {
            return [
                'dt' => InvoiceNakladnayaFormatter::formatMoney($net),
                'ct' => '—',
                'dtNeg' => false,
                'ctNeg' => false,
            ];
        }

        return [
            'dt' => InvoiceNakladnayaFormatter::formatMoney($net),
            'ct' => '—',
            'dtNeg' => true,
            'ctNeg' => false,
        ];
    };

    $saldoPassive = static function (float $dt, float $ct): array {
        $netCred = round((float) $ct - (float) $dt, 2);
        if (abs($netCred) < 0.005) {
            return ['dt' => '—', 'ct' => '—', 'dtNeg' => false, 'ctNeg' => false];
        }
        if ($netCred > 0) {
            return [
                'dt' => '—',
                'ct' => InvoiceNakladnayaFormatter::formatMoney($netCred),
                'dtNeg' => false,
                'ctNeg' => false,
            ];
        }

        return [
            'dt' => InvoiceNakladnayaFormatter::formatMoney($netCred),
            'ct' => '—',
            'dtNeg' => true,
            'ctNeg' => false,
        ];
    };

    $turnCells = static function (float $dt, float $ct): array {
        $f = static fn (float $v): string => abs($v) < 0.005 ? '—' : InvoiceNakladnayaFormatter::formatMoney($v);

        return ['dt' => $f($dt), 'ct' => $f($ct)];
    };

    $sumCells = static function (float $dt, float $ct): array {
        $f = static fn (float $v): string => abs($v) < 0.005 ? '—' : InvoiceNakladnayaFormatter::formatMoney($v);

        return ['dt' => $f($dt), 'ct' => $f($ct)];
    };

    $pickSaldoCells = static function (array $row) use ($saldoCells, $saldoPassive): callable {
        if (($row['balance_kind'] ?? '') === 'passive') {
            return $saldoPassive;
        }

        return $saldoCells;
    };

    $sections = $osv['sections'] ?? [];
    $currencyCodes = $osv['currency_codes'] ?? [];
    $multiCurrency = count($currencyCodes) > 1;
    $dash = $osv['dashboard'] ?? [];
    $dClose = $dash['closing'] ?? [];
    $dTurn = $dash['turnover'] ?? [];

    $payNet = round((float) ($dClose['payables'] ?? 0), 2);
    $recNet = round((float) ($dClose['receivables'] ?? 0), 2);

    $currencySuffix = ($currencyCodes[0] ?? '') !== '' ? ' '.($currencyCodes[0]) : '';
@endphp
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>{{ $pageTitle }}</title>
    <style>
        @page { margin: 8mm 8mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 7.5pt;
            color: #000;
            margin: 0;
            line-height: 1.35;
        }
        h1 { font-size: 12pt; margin: 0 0 4px 0; text-align: center; }
        .meta { text-align: center; font-size: 8pt; margin-bottom: 8px; color: #333; }
        h2 { font-size: 9pt; margin: 10px 0 6px 0; border-bottom: 1px solid #8eaad8; padding-bottom: 3px; }
        .dash-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 7pt; }
        .dash-table th, .dash-table td { border: 1px solid #8eaad8; padding: 4px 5px; vertical-align: top; }
        .dash-table th { background: #ddebf7; font-weight: bold; }
        .dash-table .num { text-align: right; white-space: nowrap; font-weight: 600; }
        .c1c-neg { color: #c00000; font-weight: bold; }
        .hint { font-size: 7pt; color: #444; margin: 6px 0; }
        .c1c-table { width: 100%; border-collapse: collapse; font-size: 6.5pt; }
        .c1c-table th, .c1c-table td { border: 1px solid #8eaad8; padding: 3px 4px; vertical-align: middle; }
        .c1c-table thead th { background: #ddebf7; font-weight: bold; text-align: center; }
        .c1c-num { text-align: right; white-space: nowrap; background: #fafbfd; }
        .c1c-section td { background: #c5d9f0; font-weight: bold; }
        .c1c-org td { background: #d8e6f7; font-weight: bold; }
        .c1c-subtotal td { background: #e1eaf7; font-weight: 600; }
        .c1c-grand td { background: #ddebf7; font-weight: bold; }
        .c1c-code { font-weight: bold; display: block; }
        .c1c-acct-name { font-size: 6pt; display: block; margin-top: 2px; color: #333; }
        .c1c-ind { text-align: center; }
    </style>
</head>
<body>
    <h1>Оборотно-сальдовая ведомость</h1>
    <div class="meta">
        Период: {{ $periodLabel }}
        @if (! empty($branchName))
            <br>Филиал: {{ $branchName }}
        @endif
    </div>

    <h2>1. Сальдо на конец периода</h2>
    <table class="dash-table">
        <tr>
            <th>Касса</th>
            <th>Расчётные счета</th>
            <th>Дебиторская задолженность</th>
            <th>Кредиторская задолженность</th>
            <th>Товар на складе</th>
        </tr>
        <tr>
            <td class="num @if (($dClose['cash'] ?? 0) < -0.005) c1c-neg @endif">{{ InvoiceNakladnayaFormatter::formatMoney((float) ($dClose['cash'] ?? 0)) }}{{ $currencySuffix }}</td>
            <td class="num @if (($dClose['bank'] ?? 0) < -0.005) c1c-neg @endif">{{ InvoiceNakladnayaFormatter::formatMoney((float) ($dClose['bank'] ?? 0)) }}{{ $currencySuffix }}</td>
            <td class="num @if ($recNet < -0.005) c1c-neg @endif">{{ InvoiceNakladnayaFormatter::formatMoney($recNet) }}{{ $currencySuffix }}</td>
            <td class="num">
                @if ($payNet >= -0.005)
                    {{ InvoiceNakladnayaFormatter::formatMoney(max(0.0, $payNet)) }}{{ $currencySuffix }}
                @else
                    <span class="c1c-neg">{{ InvoiceNakladnayaFormatter::formatMoney($payNet) }}{{ $currencySuffix }}</span>
                @endif
            </td>
            <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney((float) ($dClose['inventory'] ?? 0)) }}{{ $currencySuffix }}</td>
        </tr>
    </table>
    @if ($multiCurrency)
        <p class="hint">Несколько валют ({{ implode(', ', $currencyCodes) }}): суммы «Деньги» не пересчитываются по курсу.</p>
    @endif

    <h2>2. Обороты за период</h2>
    <table class="dash-table">
        <tr>
            <th>Дебет — поступления</th>
            <th>Кредит — списания</th>
        </tr>
        <tr>
            <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney((float) ($dTurn['debit'] ?? 0)) }}{{ $currencySuffix }}</td>
            <td class="num">{{ InvoiceNakladnayaFormatter::formatMoney((float) ($dTurn['credit'] ?? 0)) }}{{ $currencySuffix }}</td>
        </tr>
    </table>

    <h2>Детализация по счетам</h2>
    @if (empty($sections))
        <p>Нет данных для отчёта.</p>
    @else
        <table class="c1c-table" cellspacing="0" cellpadding="0">
            <thead>
                <tr>
                    <th rowspan="2">Счёт</th>
                    <th rowspan="2">Показ.</th>
                    <th colspan="2">Сальдо на начало</th>
                    <th colspan="2">Обороты</th>
                    <th colspan="2">Сальдо на конец</th>
                </tr>
                <tr>
                    <th>Дебет</th>
                    <th>Кредит</th>
                    <th>Дебет</th>
                    <th>Кредит</th>
                    <th>Дебет</th>
                    <th>Кредит</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sections as $sec)
                    <tr class="c1c-section">
                        <td colspan="8">{{ $sec['title'] }}</td>
                    </tr>
                    @if (($sec['mode'] ?? '') === 'money')
                        @foreach (($sec['groups'] ?? []) as $g)
                            <tr class="c1c-org">
                                <td colspan="8">{{ $g['organization_name'] }}</td>
                            </tr>
                            @foreach ($g['accounts'] as $row)
                                @php
                                    $code = $row['register_code'] ?? str_pad((string) (($row['id'] ?? 0) % 10000), 4, '0', STR_PAD_LEFT);
                                    $scf = $pickSaldoCells($row);
                                    $sn = $scf((float) $row['sn_debit'], (float) $row['sn_credit']);
                                    $tn = $turnCells((float) $row['to_debit'], (float) $row['to_credit']);
                                    $sk = $scf((float) $row['sk_debit'], (float) $row['sk_credit']);
                                    $canDrill = (($row['drill'] ?? true) !== false) && (int) $row['id'] !== 0;
                                @endphp
                                @include('admin.reports.partials.turnover-osv-row', [
                                    'code' => $code,
                                    'accountLabel' => $row['account_label'],
                                    'sn' => $sn,
                                    'tn' => $tn,
                                    'sk' => $sk,
                                    'canDrill' => $canDrill,
                                    'rowId' => (int) $row['id'],
                                    'forPdf' => true,
                                ])
                            @endforeach
                            @php
                                $sub = $g['subtotal'];
                                $sSn = $sumCells((float) $sub['sn_debit'], (float) $sub['sn_credit']);
                                $sTn = $turnCells((float) $sub['to_debit'], (float) $sub['to_credit']);
                                $sSk = $sumCells((float) $sub['sk_debit'], (float) $sub['sk_credit']);
                            @endphp
                            <tr class="c1c-subtotal">
                                <td colspan="2" style="text-align:right">Итого по организации</td>
                                <td class="c1c-num">{{ $sSn['dt'] }}</td>
                                <td class="c1c-num">{{ $sSn['ct'] }}</td>
                                <td class="c1c-num">{{ $sTn['dt'] }}</td>
                                <td class="c1c-num">{{ $sTn['ct'] }}</td>
                                <td class="c1c-num">{{ $sSk['dt'] }}</td>
                                <td class="c1c-num">{{ $sSk['ct'] }}</td>
                            </tr>
                        @endforeach
                        @if (empty($sec['groups'] ?? []) || count($sec['groups'] ?? []) === 0)
                            <tr class="c1c-main">
                                <td colspan="8" style="padding:8px;color:#475569;">
                                    Денежных счетов пока нет.
                                </td>
                            </tr>
                        @else
                            @php
                                $grand = $sec['grand'];
                                $gSn = $sumCells((float) $grand['sn_debit'], (float) $grand['sn_credit']);
                                $gTn = $turnCells((float) $grand['to_debit'], (float) $grand['to_credit']);
                                $gSk = $sumCells((float) $grand['sk_debit'], (float) $grand['sk_credit']);
                            @endphp
                            <tr class="c1c-grand">
                                <td colspan="2" style="text-align:right;text-transform:uppercase;">Итого по разделу «деньги»</td>
                                <td class="c1c-num">{{ $gSn['dt'] }}</td>
                                <td class="c1c-num">{{ $gSn['ct'] }}</td>
                                <td class="c1c-num">{{ $gTn['dt'] }}</td>
                                <td class="c1c-num">{{ $gTn['ct'] }}</td>
                                <td class="c1c-num">{{ $gSk['dt'] }}</td>
                                <td class="c1c-num">{{ $gSk['ct'] }}</td>
                            </tr>
                        @endif
                    @else
                        @foreach (($sec['accounts'] ?? []) as $row)
                            @php
                                $code = $row['register_code'] ?? '—';
                                $scf = $pickSaldoCells($row);
                                $sn = $scf((float) $row['sn_debit'], (float) $row['sn_credit']);
                                $tn = $turnCells((float) $row['to_debit'], (float) $row['to_credit']);
                                $sk = $scf((float) $row['sk_debit'], (float) $row['sk_credit']);
                                $canDrill = (($row['drill'] ?? true) !== false) && (int) $row['id'] !== 0;
                            @endphp
                            @include('admin.reports.partials.turnover-osv-row', [
                                'code' => $code,
                                'accountLabel' => $row['account_label'],
                                'sn' => $sn,
                                'tn' => $tn,
                                'sk' => $sk,
                                'canDrill' => $canDrill,
                                'rowId' => (int) $row['id'],
                                'forPdf' => true,
                            ])
                        @endforeach
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
