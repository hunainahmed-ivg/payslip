<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Salary Increments {{ $year }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { margin-bottom: 16px; color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>Yearly Salary Increments</h1>
    <div class="meta">{{ $company_name }} — {{ $year }}</div>
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Previous</th>
                <th>Type</th>
                <th>Value</th>
                <th>New</th>
                <th>Effective</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['employee_code'] }}</td>
                    <td>{{ $row['full_name'] }}</td>
                    <td>{{ number_format((float) $row['previous_basic_salary'], 2) }}</td>
                    <td>{{ $row['increment_type'] }}</td>
                    <td>{{ $row['value'] }}</td>
                    <td>{{ number_format((float) $row['new_basic_salary'], 2) }}</td>
                    <td>{{ $row['effective_date'] }}</td>
                    <td>{{ $row['note'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No increments for this year.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
