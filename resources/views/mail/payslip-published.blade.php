<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip available</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color:#0f172a; background:#f8fafc; margin:0; padding:24px;">
    <div style="max-width:560px; margin:0 auto; background:#fff; border:1px solid #e5e9f2; border-radius:14px; padding:28px;">

        <h2 style="margin:0 0 6px; font-size:18px;">
            Hello {{ $employee?->full_name ?? 'there' }},
        </h2>

        <p style="margin:0 0 18px; font-size:14px; color:#475569;">
            Your payslip for <strong>{{ $periodLabel }}</strong> is now available in the employee portal.
        </p>

        <div style="background:#f1f5f9; border-radius:10px; padding:14px 16px; margin-bottom:18px;">
            <div style="font-size:12px; color:#64748b; text-transform:uppercase; letter-spacing:.04em;">Net pay</div>
            <div style="font-size:22px; font-weight:700; color:#059669;">
                {{ $payslip->snapshot['currency_code'] ?? '' }}
                {{ number_format((float) ($payslip->snapshot['net_pay'] ?? 0), 2) }}
            </div>
        </div>

        <p style="margin:0 0 18px;">
            <a href="{{ route('portal.payslips.view', $payslip) }}"
               style="display:inline-block; background:#4f46e5; color:#fff; text-decoration:none; padding:11px 18px; border-radius:9px; font-size:14px; font-weight:600;">
                View my payslip
            </a>
        </p>

        <p style="margin:0; font-size:12px; color:#94a3b8;">
            You may be asked to sign in to the portal first.
            This is a computer-generated notification; please do not reply.
        </p>

    </div>
</body>
</html>