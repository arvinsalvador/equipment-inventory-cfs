@php
    $visibleKeys = collect($columns)->pluck('key')->all();
    $groups = $rows->groupBy(fn (array $row): string => ($row['account_code'] ?? 'Uncoded').'|'.($row['category'] ?? 'Uncategorized'));
    $grandTotal = $rows->sum(fn (array $row): float => (float) ($row['total_value_numeric'] ?? 0));
    $formatCurrency = fn (float|int $value): string => 'PHP '.number_format((float) $value, 2);
@endphp

@if ($rows->isEmpty())
    <section class="account-code-empty">No report records found.</section>
@else
    <div class="account-code-report">
        @foreach ($groups as $groupKey => $groupRows)
            @php
                [$accountCode, $category] = explode('|', $groupKey, 2);
                $subtotal = $groupRows->sum(fn (array $row): float => (float) ($row['total_value_numeric'] ?? 0));
            @endphp
            <section class="account-code-section">
                <h2>Account Code {{ $accountCode }} - {{ $category }}</h2>
                <table>
                    <thead>
                        <tr>
                            @foreach ($columns as $column)
                                <th @class(['currency' => in_array($column['key'], ['unit_value', 'total_value'], true)])>{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groupRows as $row)
                            <tr>
                                @foreach ($columns as $column)
                                    <td @class(['currency' => in_array($column['key'], ['unit_value', 'total_value'], true), 'wrap' => in_array($column['key'], ['article', 'description'], true)])>{{ $row[$column['key']] ?? '' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr class="subtotal-row">
                            <td colspan="{{ max(count($columns) - 1, 1) }}">TOTAL VALUE</td>
                            <td class="currency">{{ $formatCurrency($subtotal) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        @endforeach

        <section class="grand-total">
            <span>GRAND TOTAL</span>
            <strong>{{ $formatCurrency($grandTotal) }}</strong>
        </section>
    </div>
@endif
