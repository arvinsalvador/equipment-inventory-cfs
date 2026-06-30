<?php

namespace App\Services\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvReportExporter
{
    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @param  iterable<int, array<string, mixed>>  $rows
     */
    public function stream(string $filename, array $columns, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns, $rows): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_column($columns, 'label'));

            foreach ($rows as $row) {
                fputcsv($handle, array_map(
                    fn (array $column) => $this->stringValue($row[$column['key']] ?? ''),
                    $columns
                ));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function stringValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return (string) $value;
    }
}
