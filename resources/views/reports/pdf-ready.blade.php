<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $definition['name'] }} PDF Ready</title>
    <style>
        @page {
            margin: 14mm 12mm;
            size: landscape;
            @bottom-right {
                content: "Page " counter(page) " of " counter(pages);
                color: #6b7280;
                font-size: 9px;
            }
        }
        * { box-sizing: border-box; }
        body { color: #111827; font-family: Arial, sans-serif; font-size: 11px; margin: 0; }
        header { align-items: center; border-bottom: 2px solid #111827; display: flex; gap: 12px; margin-bottom: 14px; padding-bottom: 10px; }
        h1 { font-size: 20px; margin: 4px 0; }
        .brand-mark { align-items: center; border: 1px solid #111827; border-radius: 6px; display: flex; font-size: 10px; font-weight: 700; height: 42px; justify-content: center; width: 42px; }
        .header-copy { flex: 1; }
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
        .account-code-section { margin-top: 18px; page-break-inside: avoid; }
        .account-code-section h2 { font-size: 16px; font-weight: 400; margin: 0 0 8px; text-align: center; }
        .subtotal-row td { font-weight: 700; text-align: right; }
        .grand-total { border: 1px solid #111827; display: flex; font-weight: 700; justify-content: flex-end; gap: 28px; margin-top: 14px; padding: 8px 10px; }
        .currency { text-align: right; white-space: nowrap; }
        .wrap { overflow-wrap: anywhere; white-space: normal; }
        .account-code-empty { border: 1px solid #d1d5db; padding: 12px; }
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
        <div class="brand-mark">CFS</div>
        <div class="header-copy">
            <div class="system">{{ $reportHeaderName }}</div>
            <h1>{{ $definition['name'] }}</h1>
            <div class="meta">Generated {{ now()->format('Y-m-d H:i') }}@if ($includeGeneratedBy && auth()->user()) by {{ auth()->user()->name }}@endif | {{ $rows->count() }} record{{ $rows->count() === 1 ? '' : 's' }} | Selected columns: {{ collect($columns)->pluck('label')->join(', ') }}</div>
        </div>
    </header>

    <section class="filters">
        <strong>Applied filters:</strong>
        @forelse ($appliedFilters as $label => $value)
            {{ $label }}: {{ $value }}@if (! $loop->last); @endif
        @empty
            None
        @endforelse
    </section>

    @if ($slug === 'equipment-by-account-code')
        @include('reports.partials.account-code-equipment-table')
    @elseif ($rows->isEmpty())
        <section class="filters">
            No records matched the selected filters.
        </section>
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
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($columns as $column)
                            <td>{{ $row[$column['key']] ?? '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <footer>
        {{ $reportFooterText }}
    </footer>
</body>
</html>
