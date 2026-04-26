@php
    $fmt = static fn ($v) => number_format((float) $v, 2, ',', ' ');
    $periodLabel = $periodFrom->format('d.m.Y').' — '.$periodTo->format('d.m.Y');
@endphp
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Расписка — {{ $employee->full_name }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #0f172a;
            line-height: 1.5;
            max-width: 21cm;
            margin: 0 auto;
            padding: 1.25rem 1.5rem 2rem;
            font-size: 13px;
        }
        h1 {
            font-size: 1.1rem;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin: 0 0 0.75rem;
        }
        .meta {
            text-align: center;
            color: #475569;
            font-size: 12px;
            margin-bottom: 1.25rem;
        }
        .preview {
            border: 1px dashed #94a3b8;
            background: #fffbeb;
            color: #92400e;
            padding: 0.5rem 0.75rem;
            text-align: center;
            font-size: 12px;
            margin-bottom: 1rem;
        }
        .sum {
            text-align: center;
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            margin: 1rem 0;
            color: #047857;
        }
        .text {
            margin: 1rem 0;
            text-align: justify;
            hyphens: auto;
        }
        .grid {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            font-size: 12px;
        }
        .grid th,
        .grid td {
            border: 1px solid #cbd5e1;
            padding: 0.35rem 0.5rem;
        }
        .grid th { background: #f1f5f9; font-weight: 600; text-align: left; }
        .grid td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .sign-block {
            margin-top: 2.5rem;
            padding-top: 0.5rem;
        }
        .sign-line {
            margin-top: 2rem;
            border-top: 1px solid #0f172a;
            width: 70%;
            padding-top: 0.35rem;
            font-size: 11px;
            color: #475569;
        }
        .row-sign {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem 3rem;
            margin-top: 1.5rem;
        }
        .row-sign > div {
            flex: 1;
            min-width: 10rem;
        }
        .no-print {
            margin-bottom: 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }
        .btn {
            display: inline-block;
            padding: 0.45rem 0.9rem;
            background: #047857;
            color: #fff;
            border: none;
            border-radius: 0.35rem;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .btn:hover { background: #065f46; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0.75rem; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" class="btn" onclick="window.print()">Печать</button>
        <a href="{{ route('admin.payroll.show', $employee) }}?{{ http_build_query(['period_from' => $periodFrom->toDateString(), 'period_to' => $periodTo->toDateString()]) }}" class="btn" style="background:#334155;">Закрыть</a>
    </div>

    <p class="meta">{{ $branchName }}</p>

    <h1>Расписка о получении заработной платы</h1>

    @if ($isPreview)
        <div class="preview">Черновик по расчёту: выплата через кассу ещё не оформлена в программе.</div>
    @endif

    <p class="meta">Период начисления: {{ $periodLabel }}</p>

    <table class="grid">
        <tbody>
            <tr>
                <th>Работник</th>
                <td>{{ $employee->full_name }}</td>
            </tr>
            <tr>
                <th>Должность / роль</th>
                <td>{{ $employee->jobTypeLabel() }}</td>
            </tr>
            @if ($payoutRecord)
                <tr>
                    <th>Дата оформления выплаты</th>
                    <td>{{ $payoutRecord->created_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') ?? '—' }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <p class="text">
        Настоящим подтверждаю получение денежных средств в счёт заработной платы за указанный период в размере:
    </p>

    <p class="sum">{{ $fmt($amount) }} сом</p>

    <table class="grid">
        <thead>
            <tr>
                <th>Показатель</th>
                <th class="num" style="width: 7rem;">Сумма, сом</th>
            </tr>
        </thead>
        <tbody>
            @if (($cr['manual_contract'] ?? 0) > 0)
                <tr><td>В т.ч. по договору (за период)</td><td class="num">{{ $fmt($cr['manual_contract']) }}</td></tr>
            @endif
            <tr><td>Начислено всего</td><td class="num">{{ $fmt($cr['accrual']) }}</td></tr>
            <tr><td>Удержано (авансы)</td><td class="num">− {{ $fmt($cr['advances']) }}</td></tr>
            <tr><td>Удержано (штрафы)</td><td class="num">− {{ $fmt($cr['penalties']) }}</td></tr>
            <tr><th>К выплате</th><td class="num"><strong>{{ $fmt($cr['net']) }}</strong></td></tr>
        </tbody>
    </table>

    <p class="text">
        Денежные средства получены полностью, претензий к сумме и расчёту не имею.
    </p>

    <div class="row-sign">
        <div>
            <div class="sign-line">Подпись работника (получил деньги)</div>
        </div>
        <div>
            <div class="sign-line">Дата</div>
        </div>
    </div>

    <div class="sign-block">
        <div class="sign-line" style="width: 70%;">ФИО и подпись лица, выдавшего деньги / главного бухгалтера</div>
    </div>
</body>
</html>
