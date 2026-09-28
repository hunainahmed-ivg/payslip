{{-- Custom HTML template with simple variable substitution --}}
@php
    $html = $profile?->custom_html ?? '';
    $replacements = [
        '{{employee_name}}' => $snapshot['employee']['name'] ?? '',
        '{{employee_code}}' => $snapshot['employee']['code'] ?? '',
        '{{designation}}' => $snapshot['employee']['designation'] ?? '',
        '{{department}}' => $snapshot['employee']['department'] ?? '',
        '{{branch}}' => $snapshot['employee']['branch'] ?? '',
        '{{period}}' => $snapshot['period'] ?? '',
        '{{basic_salary}}' => number_format((float) ($snapshot['base_salary'] ?? 0), 2),
        '{{gross_pay}}' => number_format((float) ($snapshot['gross_pay'] ?? 0), 2),
        '{{total_deductions}}' => number_format((float) ($snapshot['total_deductions'] ?? 0), 2),
        '{{net_pay}}' => number_format((float) ($snapshot['net_pay'] ?? 0), 2),
        '{{currency_code}}' => $snapshot['currency_code'] ?? '',
        '{{company_name}}' => $profile?->company_name ?? '',
    ];
    $rendered = strtr($html, $replacements);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip — {{ $snapshot['period'] }}</title>
</head>
<body>
    @if ($headerPath)
        <img src="{{ $headerPath }}" style="width:100%;margin-bottom:12px;" alt="Header" />
    @endif

    {!! $rendered !!}

    @if ($footerPath)
        <img src="{{ $footerPath }}" style="width:100%;margin-top:12px;" alt="Footer" />
    @endif
</body>
</html>
