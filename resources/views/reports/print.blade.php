<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $definition['name'] }} Print View</title>
    <style>
        @page { margin: 14mm 12mm; size: landscape; }
        body { color: #111827; font-family: Arial, sans-serif; font-size: 12px; margin: 24px; }
        h1 { font-size: 22px; margin: 6px 0; }
        .meta { color: #4b5563; margin-bottom: 16px; }
        .filters { border: 1px solid #d1d5db; margin: 12px 0 18px; padding: 10px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d1d5db; padding: 7px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: 10px; text-transform: uppercase; }
        .account-code-section { margin-top: 18px; page-break-inside: avoid; }
        .account-code-section h2 { font-size: 17px; font-weight: 400; margin: 0 0 8px; text-align: center; }
        .subtotal-row td { font-weight: 700; text-align: right; }
        .grand-total { border: 1px solid #111827; display: flex; font-weight: 700; justify-content: flex-end; gap: 28px; margin-top: 14px; padding: 8px 10px; }
        .currency { text-align: right; white-space: nowrap; }
        .wrap { overflow-wrap: anywhere; white-space: normal; }
        .account-code-empty { border: 1px solid #d1d5db; padding: 12px; }
        @media print {
            body { margin: 12mm; }
            a { display: none; }
        }
    </style>
</head>
<body>
    <p class="meta">{{ $reportHeaderName }}</p>
    <h1>{{ $definition['name'] }}</h1>
    <p class="meta">Generated {{ now()->format('Y-m-d H:i') }}@if ($includeGeneratedBy && auth()->user()) by {{ auth()->user()->name }}@endif</p>

    <div class="filters">
        <strong>Applied filters:</strong>
        @forelse ($appliedFilters as $label => $value)
            {{ $label }}: {{ $value }}@if (! $loop->last); @endif
        @empty
            None
        @endforelse
    </div>

    @if ($slug === 'equipment-by-account-code')
        @include('reports.partials.account-code-equipment-table')
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($columns as $column)
                            <td>{{ $row[$column['key']] ?? '' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}">No report records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif

    <p class="meta">{{ $reportFooterText }}</p>
</body>
</html>
