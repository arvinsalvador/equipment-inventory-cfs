<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $definition['name'] }}</title>
    <style>
        body { background: #f8fafc; color: #111827; font-family: Arial, sans-serif; margin: 0; }
        main { margin: 0 auto; max-width: 1180px; padding: 28px; }
        header, section { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 16px; padding: 18px; }
        h1 { font-size: 24px; margin: 0 0 6px; }
        p { color: #4b5563; margin: 0; }
        form { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }
        label { color: #374151; display: grid; font-size: 12px; font-weight: 700; gap: 6px; text-transform: uppercase; }
        input, select { border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; padding: 9px 10px; }
        .actions { align-items: end; display: flex; flex-wrap: wrap; gap: 8px; }
        .button { background: #d97706; border: 1px solid #d97706; border-radius: 6px; color: #fff; display: inline-block; font-weight: 700; padding: 9px 12px; text-decoration: none; }
        .button.secondary { background: #fff; border-color: #d1d5db; color: #374151; }
        table { border-collapse: collapse; font-size: 13px; min-width: 100%; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 10px; text-align: left; vertical-align: top; }
        th { background: #f9fafb; color: #4b5563; font-size: 11px; text-transform: uppercase; white-space: nowrap; }
        .table-wrap { overflow-x: auto; }
        .muted { color: #6b7280; font-size: 13px; }
    </style>
</head>
<body>
    <main>
        <header>
            <p class="muted">CFS Equipment Inventory and Maintenance</p>
            <h1>{{ $definition['name'] }}</h1>
            <p>{{ $definition['description'] }}</p>
        </header>

        <section>
            <form method="GET" action="{{ route('reports.show', $slug) }}">
                @foreach ($definition['filters'] as $filter)
                    <label>
                        {{ str($filter)->replace('_', ' ')->title() }}
                        @if (in_array($filter, ['date_from', 'date_to'], true))
                            <input type="date" name="{{ $filter }}" value="{{ $filters[$filter] ?? '' }}">
                        @else
                            <select name="{{ $filter }}">
                                <option value="">All</option>
                                @foreach (($filterOptions[$filter] ?? []) as $value => $label)
                                    <option value="{{ $value }}" @selected(($filters[$filter] ?? '') == $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @endif
                    </label>
                @endforeach

                <div class="actions">
                    <button class="button" type="submit">Apply filters</button>
                    <a class="button secondary" href="{{ route('reports.show', $slug) }}">Reset</a>
                    <a class="button secondary" href="{{ route('reports.print', array_merge(['report' => $slug], $filters)) }}">Print</a>
                    <a class="button secondary" href="{{ route('reports.csv', array_merge(['report' => $slug], $filters)) }}">Export CSV</a>
                    <a class="button secondary" href="{{ url('/admin/reports') }}">Report Center</a>
                </div>
            </form>
        </section>

        <section>
            <p class="muted">{{ $rows->count() }} result{{ $rows->count() === 1 ? '' : 's' }}</p>
            <div class="table-wrap">
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
            </div>
        </section>
    </main>
</body>
</html>
