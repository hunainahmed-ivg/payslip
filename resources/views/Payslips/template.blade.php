<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip — {{ $snapshot['period'] }}</title>
    <style>
        @page {
            margin: {{ $profile?->page_margin ?? '18mm' }};
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #0f172a;
            font-size: 11px;
            line-height: 1.45;
            margin: 0;
        }
        .header-img { width: 100%; margin-bottom: 14px; }
        .footer-img { width: 100%; margin-top: 18px; }
        .accent-bar { height: 5px; background: {{ $profile?->primary_color ?? '#4F46E5' }}; margin-bottom: 16px; }
        h1 { font-size: 17px; margin: 0 0 2px 0; color: {{ $profile?->primary_color ?? '#4F46E5' }}; }
        .period { color: #64748b; font-size: 10px; }
        .conf { color: #b91c1c; font-size: 9px; text-transform: uppercase; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 3px 6px 3px 0; vertical-align: top; }
        .meta-table .lbl { color: #64748b; width: 100px; }
        .section-title {
            background: {{ $profile?->primary_color ?? '#4F46E5' }};
            color: #ffffff;
            padding: 5px 8px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .lines td { padding: 5px 8px; border-bottom: 1px solid #e5e9f2; }
        .amt { text-align: right; width: 120px; }
        .total-row td {
            padding: 6px 8px;
            font-weight: bold;
            background: #f1f5f9;
            border-top: 2px solid {{ $profile?->primary_color ?? '#4F46E5' }};
        }
        .net-box {
            padding: 10px 12px;
            background: {{ $profile?->accent_color ?? '#0EA5E9' }};
            color: #ffffff;
            font-size: 13px;
        }
        .net-box b { font-size: 16px; }
        .fine { color: #94a3b8; font-size: 8.5px; margin-top: 14px; }
        .watermark {
            position: fixed;
            top: 38%;
            left: 0;
            width: 100%;
            text-align: center;
            font-size: 52px;
            font-weight: bold;
            color: #0f172a;
            opacity: 0.07;
            letter-spacing: 6px;
        }
        .stamp-box {
            position: fixed;
            bottom: 45mm;
            right: 0;
            width: 250px;
            border: 3px double #16a34a;
            color: #16a34a;
            padding: 10px 12px;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stamp-title { font-weight: bold; font-size: 10px; }
        .stamp-main { font-weight: bold; font-size: 13px; margin: 3px 0; }
        .stamp-line { margin-top: 2px; }
        .stamp-sign {
            margin-top: 8px;
            border-top: 1px solid #16a34a;
            padding-top: 4px;
            font-style: italic;
            text-transform: none;
        }
        .two-col td { width: 50%; vertical-align: top; padding-right: 8px; }
    </style>
</head>
<body>
    {{-- Phase 1 letterhead boundary --}}
    @if ($headerPath)
        <img class="header-img" src="{{ $headerPath }}" />
    @else
        <div class="accent-bar"></div>
    @endif

    <table style="margin-bottom: 14px;">
        <tr>
            <td style="width: 70%;">
                <h1>Payslip Statement</h1>
                <div class="period">Pay period: {{ date('F Y', strtotime($snapshot['period'].'-01')) }}</div>
            </td>
            <td style="text-align: right;">
                <div class="conf">Confidential</div>
                <div class="period">Generated: {{ now()->toFormattedDateString() }}</div>
            </td>
        </tr>
    </table>

    <table class="meta-table" style="margin-bottom: 16px; border: 1px solid #e5e9f2;">
        <tr>
            <td class="lbl">Employee</td>
            <td><b>{{ $snapshot['employee']['name'] }}</b> ({{ $snapshot['employee']['code'] }})</td>
            <td class="lbl">Designation</td>
            <td>{{ $snapshot['employee']['designation'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Department</td>
            <td>{{ $snapshot['employee']['department'] ?? '—' }}</td>
            <td class="lbl">Branch</td>
            <td>{{ $snapshot['employee']['branch'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Base Salary</td>
            <td>{{ $snapshot['currency_code'] }} {{ number_format((float) $snapshot['base_salary'], 2) }}</td>
            <td class="lbl">Currency</td>
            <td>{{ $snapshot['currency_code'] }} (ISO 4217)</td>
        </tr>
    </table>

    <table class="two-col">
        <tr>
            <td>
                <table>
                    <tr><td class="section-title" colspan="2">Earnings</td></tr>
                    @foreach ($snapshot['earnings'] as $line)
                        <tr class="lines">
                            <td>{{ $line['title'] }}</td>
                            <td class="amt">{{ $snapshot['currency_code'] }} {{ number_format((float) $line['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td>Gross Pay</td>
                        <td class="amt">{{ $snapshot['currency_code'] }} {{ number_format((float) $snapshot['gross_pay'], 2) }}</td>
                    </tr>
                </table>
            </td>
            <td>
                <table>
                    <tr><td class="section-title" colspan="2">Deductions</td></tr>
                    @foreach ($snapshot['deductions'] as $line)
                        <tr class="lines">
                            <td>{{ $line['title'] }}</td>
                            <td class="amt">- {{ $snapshot['currency_code'] }} {{ number_format((float) $line['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td>Total Deductions</td>
                        <td class="amt">{{ $snapshot['currency_code'] }} {{ number_format((float) $snapshot['total_deductions'], 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top: 16px;">
        <tr>
            <td class="net-box">
                NET PAY: <b>{{ $snapshot['currency_code'] }} {{ number_format((float) $snapshot['net_pay'], 2) }}</b>
            </td>
        </tr>
    </table>

    @if ($footerPath)
        <img class="footer-img" src="{{ $footerPath }}" />
    @endif

    <div class="fine">
        This is a computer-generated payslip issued by {{ $profile?->company_name ?? 'the company' }} and does not require a physical signature.
        Snapshot frozen at payroll approval — historical figures remain immutable.
    </div>

    @if ($stamped ?? false)
        <div class="watermark">VERIFIED COPY</div>
        <div class="stamp-box">
            <div class="stamp-title">{{ $stamp['company'] }}</div>
            <div class="stamp-main">OFFICIALLY VERIFIED</div>
            <div class="stamp-line">Ref: {{ $stamp['reference'] }}</div>
            <div class="stamp-line">Purpose: {{ $stamp['reason'] }}</div>
            <div class="stamp-line">Approved: {{ $stamp['approved_at'] }}</div>
            <div class="stamp-line">By: {{ $stamp['approved_by'] }}</div>
            <div class="stamp-sign">Authorized Signatory — Digital Stamp</div>
        </div>
    @endif
</body>
</html>