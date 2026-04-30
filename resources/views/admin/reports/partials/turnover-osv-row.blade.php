@php
    $forPdf = (bool) ($forPdf ?? false);
    $useDrill = ! $forPdf && ($canDrill ?? false);
@endphp
<tr class="c1c-main">
    <td class="c1c-code-cell">
        <span class="c1c-code">{{ $code }}</span>
        <span class="c1c-acct-name">{{ $accountLabel }}</span>
    </td>
    <td class="c1c-ind">{{ $indicator ?? 'БУ' }}</td>
    <td class="c1c-num @if (! empty($sn['dtNeg'])) c1c-neg @endif">
        @if (($sn['dt'] ?? '—') !== '—')
            @if ($useDrill)
                <button
                    type="button"
                    class="c1c-drill @if (! empty($sn['dtNeg'])) c1c-neg @endif"
                    @click="drill('opening', {{ (int) ($rowId ?? 0) }})"
                >
                    {{ $sn['dt'] }}</button>
            @else
                <span class="tabular-nums">{{ $sn['dt'] }}</span>
            @endif
        @else
            —
        @endif
    </td>
    <td class="c1c-num @if (! empty($sn['ctNeg'])) c1c-neg @endif">
        @if (($sn['ct'] ?? '—') !== '—')
            @if ($useDrill)
                <button
                    type="button"
                    class="c1c-drill @if (! empty($sn['ctNeg'])) c1c-neg @endif"
                    @click="drill('opening', {{ (int) ($rowId ?? 0) }})"
                >
                    {{ $sn['ct'] }}</button>
            @else
                <span class="tabular-nums">{{ $sn['ct'] }}</span>
            @endif
        @else
            —
        @endif
    </td>
    <td class="c1c-num">
        @if (($tn['dt'] ?? '—') !== '—')
            @if ($useDrill)
                <button
                    type="button"
                    class="c1c-drill"
                    @click="drill('turnover_debit', {{ (int) ($rowId ?? 0) }})"
                >
                    {{ $tn['dt'] }}</button>
            @else
                <span class="tabular-nums">{{ $tn['dt'] }}</span>
            @endif
        @else
            —
        @endif
    </td>
    <td class="c1c-num">
        @if (($tn['ct'] ?? '—') !== '—')
            @if ($useDrill)
                <button
                    type="button"
                    class="c1c-drill"
                    @click="drill('turnover_credit', {{ (int) ($rowId ?? 0) }})"
                >
                    {{ $tn['ct'] }}</button>
            @else
                <span class="tabular-nums">{{ $tn['ct'] }}</span>
            @endif
        @else
            —
        @endif
    </td>
    <td class="c1c-num @if (! empty($sk['dtNeg'])) c1c-neg @endif">
        @if (($sk['dt'] ?? '—') !== '—')
            @if ($useDrill)
                <button
                    type="button"
                    class="c1c-drill @if (! empty($sk['dtNeg'])) c1c-neg @endif"
                    @click="drill('closing', {{ (int) ($rowId ?? 0) }})"
                >
                    {{ $sk['dt'] }}</button>
            @else
                <span class="tabular-nums @if (! empty($sk['dtNeg'])) font-semibold @endif">{{ $sk['dt'] }}</span>
            @endif
        @else
            —
        @endif
    </td>
    <td class="c1c-num @if (! empty($sk['ctNeg'])) c1c-neg @endif">
        @if (($sk['ct'] ?? '—') !== '—')
            @if ($useDrill)
                <button
                    type="button"
                    class="c1c-drill @if (! empty($sk['ctNeg'])) c1c-neg @endif"
                    @click="drill('closing', {{ (int) ($rowId ?? 0) }})"
                >
                    {{ $sk['ct'] }}</button>
            @else
                <span class="tabular-nums @if (! empty($sk['ctNeg'])) font-semibold @endif">{{ $sk['ct'] }}</span>
            @endif
        @else
            —
        @endif
    </td>
</tr>
