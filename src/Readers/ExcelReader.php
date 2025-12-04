<?php

namespace LaravelExporter\Readers;

use Generator;
use LaravelExporter\Contracts\FormatReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Common\Entity\Row;

/**
 * Excel File Reader using OpenSpout
 *
 * Memory-efficient streaming XLSX reader
 */
class ExcelReader implements FormatReaderInterface
{
    /**
     * Read the Excel file and yield rows
     */
    public function read(string $filePath, array $options = []): Generator
    {
        $this->ensureOpenSpout();

        $sheetIndex = $options['sheet_index'] ?? 0;
        $sheetName = $options['sheet_name'] ?? null;
        $startRow = $options['start_row'] ?? 1;
        $limit = $options['limit'] ?? 0;
        $endColumn = $options['end_column'] ?? null;
        $calculateFormulas = $options['calculate_formulas'] ?? false;

        $xlsxOptions = new XlsxOptions();
        $xlsxOptions->SHOULD_FORMAT_DATES = true;
        $xlsxOptions->SHOULD_PRESERVE_EMPTY_ROWS = false;

        $reader = new XlsxReader($xlsxOptions);
        $reader->open($filePath);

        try {
            $currentSheetIndex = 0;

            foreach ($reader->getSheetIterator() as $sheet) {
                // Find the right sheet
                if ($sheetName !== null && $sheet->getName() !== $sheetName) {
                    $currentSheetIndex++;
                    continue;
                }

                if ($sheetName === null && $currentSheetIndex !== $sheetIndex) {
                    $currentSheetIndex++;
                    continue;
                }

                $rowNumber = 0;
                $importedCount = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;

                    // Skip rows before start row
                    if ($rowNumber < $startRow) {
                        continue;
                    }

                    $cells = $row->getCells();
                    $rowData = [];

                    foreach ($cells as $cell) {
                        $value = $cell->getValue();

                        // Handle formulas
                        if ($calculateFormulas && is_string($value) && str_starts_with($value, '=')) {
                            // OpenSpout doesn't calculate formulas, return as-is
                            // For calculated values, use PhpSpreadsheet reader
                            $rowData[] = $value;
                        } else {
                            $rowData[] = $value;
                        }
                    }

                    // Apply column limit
                    if ($endColumn !== null) {
                        $columnIndex = $this->columnLetterToIndex($endColumn);
                        $rowData = array_slice($rowData, 0, $columnIndex + 1);
                    }

                    yield $rowNumber => $rowData;

                    $importedCount++;

                    // Check limit
                    if ($limit > 0 && $importedCount >= $limit) {
                        break 2;
                    }
                }

                break; // Only process one sheet
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * Get row count for Excel file
     */
    public function getRowCount(string $filePath): ?int
    {
        $this->ensureOpenSpout();

        $xlsxOptions = new XlsxOptions();
        $reader = new XlsxReader($xlsxOptions);
        $reader->open($filePath);

        $count = 0;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $count++;
                }
                break; // Only count first sheet
            }
        } finally {
            $reader->close();
        }

        return $count;
    }

    /**
     * Get all sheets in the Excel file
     */
    public function getSheets(string $filePath): array
    {
        $this->ensureOpenSpout();

        $xlsxOptions = new XlsxOptions();
        $reader = new XlsxReader($xlsxOptions);
        $reader->open($filePath);

        $sheets = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $sheets[$sheet->getIndex()] = $sheet->getName();
            }
        } finally {
            $reader->close();
        }

        return $sheets;
    }

    /**
     * Check supported extensions
     */
    public function supports(string $extension): bool
    {
        return in_array(strtolower($extension), ['xlsx', 'xls']);
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

    /**
     * Ensure OpenSpout is available
     */
    protected function ensureOpenSpout(): void
    {
        if (!class_exists(XlsxReader::class)) {
            throw new \RuntimeException(
                'OpenSpout is required for Excel imports. Install it with: composer require openspout/openspout'
            );
        }
    }
}
