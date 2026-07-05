<?php

namespace App\Services\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelReportExporter
{
    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @param  iterable<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $filters
     */
    public function stream(string $filename, string $title, array $columns, iterable $rows, array $filters = []): StreamedResponse
    {
        return response()->streamDownload(function () use ($title, $columns, $rows, $filters): void {
            echo "\xEF\xBB\xBF";
            echo '<html><head><meta charset="utf-8"><style>';
            echo 'body{font-family:Arial,sans-serif;} h1{font-size:20px;margin:0 0 6px;} p{color:#4b5563;margin:0 0 12px;}';
            echo 'table{border-collapse:collapse;} th{background:#f3f4f6;font-weight:700;} th,td{border:1px solid #d1d5db;padding:7px;vertical-align:top;}';
            echo '</style></head><body>';
            echo '<h1>'.$this->escape($title).'</h1>';
            echo '<p>Generated '.$this->escape(now()->format('Y-m-d H:i')).'</p>';

            if ($filters !== []) {
                echo '<table border="1"><tr><th>Applied filter</th><th>Value</th></tr>';

                foreach ($filters as $label => $value) {
                    echo '<tr><td>'.$this->escape($label).'</td><td>'.$this->escape($value).'</td></tr>';
                }

                echo '</table><br>';
            }

            echo '<table border="1"><thead><tr>';

            foreach ($columns as $column) {
                echo '<th>'.$this->escape($column['label']).'</th>';
            }

            echo '</tr></thead><tbody>';

            foreach ($rows as $row) {
                echo '<tr>';

                foreach ($columns as $column) {
                    echo '<td>'.$this->escape($this->stringValue($row[$column['key']] ?? '')).'</td>';
                }

                echo '</tr>';
            }

            echo '</tbody></table></body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
