<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip — {{ $snapshot['period'] }}</title>
    <style>
        @page { margin: {{ $profile?->page_margin ?? '20mm' }}; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Serif', 'Times New Roman', serif;
            color: #1a1a1a;
            font-size: 11px;
            line-height: 1.5;
            margin: 0;
        }
        .header-img { width: 100%; margin-bottom: 12px; }
        .footer-img { width: 100%; margin-top: 16px; }
        .letterhead { text-align: center; border-bottom: 2px solid #1a1a1a; padding-bottom: 12px; margin-bottom: 16px; }
        .letterhead h1 { font-size: 18px; margin: 0; letter-spacing: 1px; text-transform: uppercase; }
        .letterhead .sub { color: #555; font-size: 10px; margin-top: 4px; }
        .title-row { text-align: center; margin: 14px 0; }
        .title-row h2 { margin: 0; font-size: 14px; text-decoration: underline; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 4px 6px; border: 1px solid #ccc; }
        .meta .lbl { width: 28%; background: #f5f5f5; font-weight: bold; }
        .section { margin-top: 14px; }
        .section th { background: #1a1a1a; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; text-transform: uppercase; }
        .section td { padding: 5px 8px; border-bottom: 1px solid #ddd; }
        .amt { text-align: right; }
        .net { margin-top: 14px; border: 2px solid #1a1a1a; padding: 10px; text-align: center; font-size: 13px; }
        .fine { color: #777; font-size: 8.5px; margin-top: 14px; text-align: center; }
    </style>
</head>
<body>
    @if ($headerPath)
        <img class="header-img" src="{{ $headerPath }}" />
    @else
        <div class="letterhead">
            <h1>{{ $profile?->company_name ?? 'Company' }}</h1>
            <div class="sub">{{ $profile?->address ?? '' }}</div>
        </div>
    @endif

    <div class="title-row">
        <h2>Official Payslip</h2>
        <div>Pay Period: {{ date('F Y', strtotime($snapshot['period'].'-01')) }}</div>
    </div>

    <table class="meta">
        <tr>
            <td class="lbl">Employee Name</td>
            <td>{{ $snapshot['employee']['name'] }}</td>
            <td class="lbl">Employee Code</td>
            <td>{{ $snapshot['employee']['code'] }}</td>
        </tr>
        <tr>
            <td class="lbl">Designation</td>
            <td>{{ $snapshot['employee']['designation'] ?? '—' }}</td>
            <td class="lbl">Department</td>
            <td>{{ $snapshot['employee']['department'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Branch</td>
            <td>{{ $snapshot['employee']['branch'] ?? '—' }}</td>
            <td class="lbl">Currency</td>
            <td>{{ $snapshot['currency_code'] }}</td>
        </tr>
    </table>

    <table class="section">
        <tr><th colspan="2">Earnings</th></tr>
        @foreach ($snapshot['earnings'] as $line)
            <tr>
                <td>{{ $line['title'] }}</td>
                <td class="amt">{{ $snapshot['currency_code'] }} {{ number_format((float) $line['amount'], 2) }}</td>
            </tr>
        @endforeach
        <tr>
            <td><strong>Gross Pay</strong></td>
            <td class="amt"><strong>{{ $snapshot['currency_code'] }} {{ number_format((float) $snapshot['gross_pay'], 2) }}</strong></td>
        </tr>
    </table>

    <table class="section">
        <tr><th colspan="2">Deductions</th></tr>
        @foreach ($snapshot['deductions'] as $line)
            <tr>
                <td>{{ $line['title'] }}</td>
                <td class="amt">- {{ $snapshot['currency_code'] }} {{ number_format((float) $line['amount'], 2) }}</td>
            </tr>
        @endforeach
        <tr>
            <td><strong>Total Deductions</strong></td>
            <td class="amt"><strong>{{ $snapshot['currency_code'] }} {{ number_format((float) $snapshot['total_deductions'], 2) }}</strong></td>
        </tr>
    </table>

    <div class="net">
        Net Pay Payable: <strong>{{ $snapshot['currency_code'] }} {{ number_format((float) $snapshot['net_pay'], 2) }}</strong>
    </div>

    @if ($footerPath)
        <img class="footer-img" src="{{ $footerPath }}" />
    @endif

    <div class="fine">
        This is a computer-generated payslip issued by {{ $profile?->company_name ?? 'the company' }}.
        Figures are frozen at payroll approval and remain unchanged by later salary updates.
    </div>
</body>
</html>
