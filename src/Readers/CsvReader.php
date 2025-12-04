<?php

namespace LaravelExporter\Readers;

use Generator;
use LaravelExporter\Contracts\FormatReaderInterface;

/**
 * CSV File Reader
 *
 * Memory-efficient streaming CSV reader
 */
class CsvReader implements FormatReaderInterface
{
    /**
     * Read the CSV file and yield rows
     */
    public function read(string $filePath, array $options = []): Generator
    {
        $delimiter = $options['delimiter'] ?? ',';
        $enclosure = $options['enclosure'] ?? '"';
        $escape = $options['escape'] ?? '\\';
        $startRow = $options['start_row'] ?? 1;
        $limit = $options['limit'] ?? 0;
        $endColumn = $options['end_column'] ?? null;

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: {$filePath}");
        }

        try {
            $rowNumber = 0;
            $importedCount = 0;

            while (($row = fgetcsv($handle, 0, $delimiter, $enclosure, $escape)) !== false) {
                $rowNumber++;

                // Skip rows before start row
                if ($rowNumber < $startRow) {
                    continue;
                }

                // Apply column limit
                if ($endColumn !== null) {
                    $columnIndex = $this->columnLetterToIndex($endColumn);
                    $row = array_slice($row, 0, $columnIndex + 1);
                }

                yield $rowNumber => $row;

                $importedCount++;

                // Check limit
                if ($limit > 0 && $importedCount >= $limit) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Get row count for CSV file
     */
    public function getRowCount(string $filePath): ?int
    {
        $count = 0;
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            return null;
        }

        while (fgets($handle) !== false) {
            $count++;
        }

        fclose($handle);

        return $count;
    }

    /**
     * CSV files only have one "sheet"
     */
    public function getSheets(string $filePath): array
    {
        return [0 => 'Sheet1'];
    }

    /**
     * Check supported extensions
     */
    public function supports(string $extension): bool
    {
        return in_array(strtolower($extension), ['csv', 'txt', 'tsv']);
    }

    /**
     * Convert column letter to 0-based index
     */
    protected function columnLetterToIndex(string $letter): int
    {
        $letter = strtoupper($letter);
        $length = strlen($letter);
        $index = 0;

        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($letter[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }
}
