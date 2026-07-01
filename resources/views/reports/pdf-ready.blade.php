<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $definition['name'] }} PDF Ready</title>
    <style>
        @page { margin: 14mm 12mm; size: landscape; }
        * { box-sizing: border-box; }
        body { color: #111827; font-family: Arial, sans-serif; font-size: 11px; margin: 0; }
        header { border-bottom: 2px solid #111827; margin-bottom: 14px; padding-bottom: 10px; }
        h1 { font-size: 20px; margin: 4px 0; }
        .system { color: #374151; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .meta { color: #4b5563; margin-top: 4px; }
        .toolbar { background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 6px; margin-bottom: 14px; padding: 10px; }
        .toolbar button { background: #d97706; border: 0; border-radius: 5px; color: #fff; cursor: pointer; font-weight: 700; padding: 8px 12px; }
        .filters { border: 1px solid #d1d5db; margin: 10px 0 14px; padding: 8px; }
        table { border-collapse: collapse; page-break-inside: auto; width: 100%; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        th, td { border: 1px solid #d1d5db; padding: 5px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; }
        footer { border-top: 1px solid #d1d5db; color: #6b7280; margin-top: 14px; padding-top: 8px; }
        @media print {
            .toolbar { display: none; }
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
        <span>Use the browser print dialog and choose Save as PDF.</span>
    </div>

    <header>
        <div class="system">CFS Equipment Inventory and Maintenance</div>
        <h1>{{ $definition['name'] }}</h1>
        <div class="meta">Generated {{ now()->format('Y-m-d H:i') }} | {{ $rows->count() }} record{{ $rows->count() === 1 ? '' : 's' }}</div>
    </header>

    <section class="filters">
        <strong>Applied filters:</strong>
        @forelse ($appliedFilters as $label => $value)
            {{ $label }}: {{ $value }}@if (! $loop->last); @endif
        @empty
            None
        @endforelse
    </section>

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

    <footer>
        PDF-ready template. Server-side PDF generation is deferred until a PDF package is approved.
    </footer>
</body>
</html>
