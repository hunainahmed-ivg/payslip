<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip — {{ $snapshot['period'] }}</title>
    <style>
        @page { margin: {{ $profile?->page_margin ?? '12mm' }}; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #111;
            font-size: 9.5px;
            line-height: 1.3;
            margin: 0;
        }
        .header-img { width: 100%; margin-bottom: 6px; }
        .footer-img { width: 100%; margin-top: 8px; }
        .bar { height: 3px; background: {{ $profile?->primary_color ?? '#4F46E5' }}; margin-bottom: 8px; }
        h1 { font-size: 13px; margin: 0; color: {{ $profile?->primary_color ?? '#4F46E5' }}; }
        .meta { margin: 6px 0 8px; }
        .meta td { padding: 1px 4px 1px 0; }
        .lbl { color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        .grid td { width: 50%; vertical-align: top; padding-right: 6px; }
        .lines td { padding: 2px 4px; border-bottom: 1px solid #e5e9f2; }
        .section-title {
            background: {{ $profile?->primary_color ?? '#4F46E5' }};
            color: #fff;
            padding: 3px 5px;
            font-size: 8.5px;
            text-transform: uppercase;
        }
        .amt { text-align: right; }
        .net {
            margin-top: 8px;
            padding: 6px 8px;
            background: {{ $profile?->accent_color ?? '#0EA5E9' }};
            color: #fff;
            font-size: 11px;
        }
        .fine { color: #94a3b8; font-size: 7.5px; margin-top: 8px; }
    </style>
</head>
<body>
    @if ($headerPath)
        <img class="header-img" src="{{ $headerPath }}" />
    @else
        <div class="bar"></div>
    @endif

    <table>
        <tr>
            <td><h1>Payslip · {{ date('M Y', strtotime($snapshot['period'].'-01')) }}</h1></td>
            <td style="text-align:right;color:#64748b;">{{ $snapshot['employee']['code'] }}</td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td class="lbl">Employee</td>
            <td><b>{{ $snapshot['employee']['name'] }}</b></td>
            <td class="lbl">Dept</td>
            <td>{{ $snapshot['employee']['department'] ?? '—' }}</td>
            <td class="lbl">Branch</td>
            <td>{{ $snapshot['employee']['branch'] ?? '—' }}</td>
        </tr>
    </table>

    <table class="grid">
        <tr>
            <td>
                <table>
                    <tr><td class="section-title" colspan="2">Earnings</td></tr>
                    @foreach ($snapshot['earnings'] as $line)
                        <tr class="lines">
                            <td>{{ $line['title'] }}</td>
                            <td class="amt">{{ number_format((float) $line['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="lines"><td><b>Gross</b></td><td class="amt"><b>{{ number_format((float) $snapshot['gross_pay'], 2) }}</b></td></tr>
                </table>
            </td>
            <td>
                <table>
                    <tr><td class="section-title" colspan="2">Deductions</td></tr>
                    @foreach ($snapshot['deductions'] as $line)
                        <tr class="lines">
                            <td>{{ $line['title'] }}</td>
                            <td class="amt">-{{ number_format((float) $line['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="lines"><td><b>Total</b></td><td class="amt"><b>{{ number_format((float) $snapshot['total_deductions'], 2) }}</b></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="net">
        Net Pay ({{ $snapshot['currency_code'] }}): <b>{{ number_format((float) $snapshot['net_pay'], 2) }}</b>
    </div>

    @if ($footerPath)
        <img class="footer-img" src="{{ $footerPath }}" />
    @endif

    <div class="fine">Issued by {{ $profile?->company_name ?? 'the company' }}. Snapshot locked at approval.</div>
</body>
</html>
