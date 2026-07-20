<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class OfficialEquipmentInventoryDocumentParser
{
    /** @var array<string, string> */
    public const APPROVED_CATEGORIES = [
        '10604010' => 'Building',
        '10604020' => 'School Buildings',
        '10604990' => 'Other Structures',
        '10605020' => 'Office Equipment',
        '10607010' => 'Furniture & Fixtures',
        '10605030' => 'Information and Communication Technology Equipment',
        '10607020' => 'Books',
        '10605040' => 'Agricultural, Fishery & Forestry Equipment',
        '10605110' => 'Medical, Dental & Laboratory Equipment',
        '10605140' => 'Technical & Scientific Equipment',
    ];

    /** @return array{rows: array<int, array<string, mixed>>, summary: array<string, mixed>} */
    public function parse(?string $path = null): array
    {
        $path ??= database_path('seeders/data/official-equipment-inventory.docx');

        if (! is_file($path)) {
            throw new RuntimeException("Official equipment inventory document not found at {$path}.");
        }

        $documentXml = $this->readDocumentXml($path);
        $document = new DOMDocument;
        $document->loadXML($documentXml);

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $body = $xpath->query('/w:document/w:body')->item(0);
        if (! $body instanceof DOMElement) {
            throw new RuntimeException('Unable to read the official equipment inventory document body.');
        }

        $currentSection = null;
        $rows = [];
        $summary = [
            'account_code_sections_found' => [],
            'rows_parsed' => 0,
            'rows_skipped' => 0,
            'missing_property_numbers' => 0,
            'unparsed_dates' => [],
            'malformed_unit_values' => [],
            'duplicate_conflicts' => [],
        ];

        foreach ($body->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            if ($node->localName === 'p') {
                $text = $this->nodeText($xpath, $node);
                if (preg_match('/Account\s+Code\s+(\d{8})\s*[-–]\s*(.+)/u', $text, $matches)) {
                    $accountCode = $matches[1];
                    $categoryName = self::APPROVED_CATEGORIES[$accountCode] ?? $this->cleanText($matches[2]);
                    $currentSection = [
                        'account_code' => $accountCode,
                        'category_name' => $categoryName,
                    ];
                    $summary['account_code_sections_found'][$accountCode] = $categoryName;
                }

                continue;
            }

            if ($node->localName !== 'tbl' || $currentSection === null) {
                continue;
            }

            foreach ($this->tableRows($xpath, $node, $currentSection, $summary) as $row) {
                $rows[] = $row;
            }
        }

        $summary['account_code_sections_found'] = array_values(array_map(
            fn (string $accountCode, string $categoryName): array => compact('accountCode', 'categoryName'),
            array_keys($summary['account_code_sections_found']),
            $summary['account_code_sections_found'],
        ));
        $summary['rows_parsed'] = count($rows);
        $summary['duplicate_conflicts'] = $this->duplicateConflicts($rows);

        return compact('rows', 'summary');
    }

    public function parseDate(?string $value): ?string
    {
        $value = $this->cleanText((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}$/', $value)) {
            return $value.'-01-01';
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2}|\d{4})$/', $value, $matches)) {
            $year = (int) $matches[3];
            if (strlen($matches[3]) === 2) {
                $year += $year <= 50 ? 2000 : 1900;
            }

            if (checkdate((int) $matches[1], (int) $matches[2], $year)) {
                return sprintf('%04d-%02d-%02d', $year, (int) $matches[1], (int) $matches[2]);
            }
        }

        $normalized = str_ireplace('Sept', 'Sep', $value);
        $normalized = preg_replace('/,\s*/', ', ', $normalized) ?: $normalized;

        foreach (['!d-M-y', '!M-j-Y', '!M-d-Y', '!M j, Y', '!F j, Y', '!F d, Y', '!M j,Y', '!F j,Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat($format, $normalized);
            } catch (\Throwable) {
                $date = false;
            }

            if ($date !== false) {
                return $date->toDateString();
            }
        }

        try {
            return CarbonImmutable::parse($normalized)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{0: ?string, 1: ?string} */
    public function parseUnitValue(?string $value): array
    {
        $original = $this->cleanText((string) $value);
        if ($original === '') {
            return [null, null];
        }

        $clean = trim(str_ireplace(['PHP', '₱', ' '], '', $original));
        $malformed = null;

        if (preg_match('/^\d{1,3}(,\d{3})+,\d{2}$/', $clean)) {
            $malformed = $original;
            $lastComma = strrpos($clean, ',');
            $clean = str_replace(',', '', substr($clean, 0, $lastComma)).'.'.substr($clean, $lastComma + 1);
        } else {
            $clean = str_replace(',', '', $clean);
        }

        if (! is_numeric($clean)) {
            return [null, $original];
        }

        return [number_format((float) $clean, 2, '.', ''), $malformed];
    }

    private function readDocumentXml(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException("Unable to open official equipment inventory document at {$path}.");
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('Unable to locate word/document.xml in the official equipment inventory document.');
        }

        return $xml;
    }

    /** @return array<int, array<string, mixed>> */
    private function tableRows(DOMXPath $xpath, DOMElement $table, array $section, array &$summary): array
    {
        $rawRows = [];
        foreach ($xpath->query('./w:tr', $table) as $tr) {
            if (! $tr instanceof DOMElement) {
                continue;
            }

            $cells = [];
            foreach ($xpath->query('./w:tc', $tr) as $tc) {
                if ($tc instanceof DOMElement) {
                    $cells[] = $this->cellText($xpath, $tc);
                }
            }
            $rawRows[] = $cells;
        }

        if ($rawRows === []) {
            return [];
        }

        $headers = array_map(fn (string $header): string => $this->normalizeHeader($header), $rawRows[0]);
        $rows = [];

        foreach (array_slice($rawRows, 1) as $index => $cells) {
            $joined = strtoupper(trim(implode(' ', $cells)));
            if ($joined === '' || str_contains($joined, 'TOTAL VALUE')) {
                $summary['rows_skipped']++;

                continue;
            }

            $source = $this->rowByHeaders($headers, $cells);
            $article = $source['article'] ?? $source['article_description'] ?? '';
            $description = $source['description'] ?? '';

            $article = $this->cleanText($article);
            $description = $this->cleanText($description);
            $propertyNumber = $this->cleanText($source['property_number'] ?? '');
            $sourceDate = $this->cleanText($source['date_acquired'] ?? '');
            $sourceValue = $this->cleanText($source['unit_value'] ?? '');
            $remarks = $this->cleanText($source['remarks'] ?? '');

            if ($article === '' && $description === '' && $propertyNumber === '') {
                $summary['rows_skipped']++;

                continue;
            }

            [$equipmentName, $normalizedDescription] = $this->normalizeArticle($article, $description);
            $parsedDate = $this->parseDate($sourceDate);
            [$parsedValue, $malformedValue] = $this->parseUnitValue($sourceValue);

            if ($propertyNumber === '') {
                $summary['missing_property_numbers']++;
            }

            if ($sourceDate !== '' && $parsedDate === null) {
                $summary['unparsed_dates'][] = ['value' => $sourceDate, 'article' => $article, 'account_code' => $section['account_code']];
            }

            if ($malformedValue !== null) {
                $summary['malformed_unit_values'][] = ['value' => $malformedValue, 'normalized' => $parsedValue, 'article' => $article, 'account_code' => $section['account_code']];
            }

            $rows[] = [
                'account_code' => $section['account_code'],
                'category_name' => $section['category_name'],
                'article' => $article,
                'equipment_name' => $equipmentName,
                'description' => $normalizedDescription,
                'source_description' => $description,
                'source_date_acquired' => $sourceDate,
                'acquisition_date' => $parsedDate,
                'property_number' => $propertyNumber,
                'source_unit_value' => $sourceValue,
                'acquisition_cost' => $parsedValue,
                'remarks' => $remarks,
                'source_row' => $index + 2,
            ];
        }

        return $rows;
    }

    /** @return array<string, string> */
    private function rowByHeaders(array $headers, array $cells): array
    {
        $row = [];
        foreach ($headers as $index => $header) {
            if ($header !== '') {
                $row[$header] = $this->cleanText($cells[$index] ?? '');
            }
        }

        return $row;
    }

    /** @return array{0: string, 1: string} */
    private function normalizeArticle(string $article, string $description): array
    {
        $details = trim($description);
        $text = preg_replace('/\s+/', ' ', trim($article)) ?: '';

        if (preg_match('/Laptop\s+ACER\s+Nitro\s+5\s+([^\s]+)\s+SN:\s*(.+)/i', $text, $matches)) {
            return ['Acer Nitro 5 Laptop', trim('Model '.$matches[1].'; Serial Number '.$matches[2].($details ? '; '.$details : ''))];
        }

        if (preg_match('/Laptop\s+MSI\s+([^\s]+).*SN:\s*(.+)/i', $text, $matches)) {
            return ['MSI '.$matches[1].' Laptop', trim($text.($details ? '; '.$details : ''))];
        }

        if (preg_match('/Lenovo\s+Laptop-?14\s+inches/i', $text)) {
            return ['Lenovo 14-inch Laptop', trim($text.($details ? '; '.$details : ''))];
        }

        if (preg_match('/Smart TV\s+85/i', $text) && stripos($text, 'Samsung') !== false) {
            $serial = trim(str_ireplace(['Smart TV 85” UHD 4K LED Samsung', 'Smart TV 85" UHD 4K LED Samsung'], '', $text));

            return ['Samsung 85-inch Smart TV', trim('UHD 4K LED'.($serial ? '; identifier/serial '.$serial : '').($details ? '; '.$details : ''))];
        }

        if (preg_match('/Air Conditioner,\s*Koppel\s+([\d.]+)TR\s+Floor Mounted,\s*Split Type Inverter/i', $text, $matches)) {
            return ['Koppel Floor-Mounted Air Conditioner', trim($matches[1].' TR, split-type inverter'.($details ? '; '.$details : ''))];
        }

        if (preg_match('/Wall Mounted Aircon Koppel/i', $text)) {
            return ['Koppel Wall-Mounted Air Conditioner', trim($text.($details ? '; '.$details : ''))];
        }

        if (preg_match('/Copier Machine,\s*Kyocera\s+SN:\s*(.+)/i', $text, $matches)) {
            return ['Kyocera Copier Machine', trim('Serial Number '.$matches[1].($details ? '; '.$details : ''))];
        }

        if (preg_match('/Flatscreen TV\s+75/i', $text)) {
            return ['75-inch Flatscreen TV', trim($text.($details ? '; '.$details : ''))];
        }

        return [$text, $details ?: $text];
    }

    private function normalizeHeader(string $header): string
    {
        $header = strtolower($this->cleanText($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?: '';
        $header = trim($header, '_');

        return match ($header) {
            'article' => 'article',
            'description' => 'description',
            'article_description' => 'article_description',
            'date_acquired' => 'date_acquired',
            'property_number' => 'property_number',
            'unit_value' => 'unit_value',
            'remarks' => 'remarks',
            default => $header,
        };
    }

    private function nodeText(DOMXPath $xpath, DOMNode $node): string
    {
        $parts = [];
        foreach ($xpath->query('.//w:t', $node) as $textNode) {
            $parts[] = $textNode->nodeValue;
        }

        return $this->cleanText(implode('', $parts));
    }

    private function cellText(DOMXPath $xpath, DOMElement $cell): string
    {
        $parts = [];
        foreach ($xpath->query('./w:p', $cell) as $paragraph) {
            $text = $this->nodeText($xpath, $paragraph);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return $this->cleanText(implode(' ', $parts));
    }

    private function cleanText(string $value): string
    {
        $value = str_replace(["\xc2\xa0", "\xe2\x80\x8b"], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?: '';

        return trim($value);
    }

    /** @return array<int, array<string, mixed>> */
    private function duplicateConflicts(array $rows): array
    {
        $seen = [];
        $duplicates = [];

        foreach ($rows as $row) {
            $key = implode('|', [
                $row['account_code'],
                $row['property_number'],
                strtolower($row['article']),
                $row['acquisition_date'],
            ]);

            if (isset($seen[$key])) {
                $duplicates[] = $row;

                continue;
            }

            $seen[$key] = true;
        }

        return $duplicates;
    }
}
