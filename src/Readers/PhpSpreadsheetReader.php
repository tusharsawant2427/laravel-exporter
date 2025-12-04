<?php

namespace LaravelExporter\Readers;

use Generator;
use LaravelExporter\Contracts\FormatReaderInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel File Reader using PhpSpreadsheet
 *
 * Provides full Excel features including formula calculation
 * Use for smaller files or when formula calculation is needed
 */
class PhpSpreadsheetReader implements FormatReaderInterface
{
    /**
     * Read the Excel file and yield rows
     */
    public function read(string $filePath, array $options = []): Generator
    {
        $this->ensurePhpSpreadsheet();

        $sheetIndex = $options['sheet_index'] ?? 0;
        $sheetName = $options['sheet_name'] ?? null;
        $startRow = $options['start_row'] ?? 1;
        $limit = $options['limit'] ?? 0;
        $endColumn = $options['end_column'] ?? null;
        $calculateFormulas = $options['calculate_formulas'] ?? true;

        // Create reader
        $reader = IOFactory::createReaderForFile($filePath);

        if ($sheetName !== null) {
            $reader->setLoadSheetsOnly([$sheetName]);
        }

        $spreadsheet = $reader->load($filePath);

        // Get the sheet
        if ($sheetName !== null) {
            $worksheet = $spreadsheet->getSheetByName($sheetName);
        } else {
            $worksheet = $spreadsheet->getSheet($sheetIndex);
        }

        if ($worksheet === null) {
            throw new \RuntimeException("Sheet not found");
        }

        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $endColumn ?? $worksheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        $rowNumber = 0;
        $importedCount = 0;

        for ($row = 1; $row <= $highestRow; $row++) {
            $rowNumber++;

            // Skip rows before start row
            if ($rowNumber < $startRow) {
                continue;
            }

            $rowData = [];

            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cell = $worksheet->getCellByColumnAndRow($col, $row);

                if ($calculateFormulas) {
                    $value = $cell->getCalculatedValue();
                } else {
                    $value = $cell->getValue();
                }

                $rowData[] = $value;
            }

            yield $rowNumber => $rowData;

            $importedCount++;

            // Check limit
            if ($limit > 0 && $importedCount >= $limit) {
                break;
            }
        }

        // Clean up
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * Get row count for Excel file
     */
    public function getRowCount(string $filePath): ?int
    {
        $this->ensurePhpSpreadsheet();

        $reader = IOFactory::createReaderForFile($filePath);
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $count = $worksheet->getHighestRow();

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $count;
    }

    /**
     * Get all sheets in the Excel file
     */
    public function getSheets(string $filePath): array
    {
        $this->ensurePhpSpreadsheet();

        $reader = IOFactory::createReaderForFile($filePath);
        $spreadsheet = $reader->load($filePath);

        $sheets = [];
        foreach ($spreadsheet->getSheetNames() as $index => $name) {
            $sheets[$index] = $name;
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $sheets;
    }

    /**
     * Check supported extensions
     */
    public function supports(string $extension): bool
    {
        return in_array(strtolower($extension), ['xlsx', 'xls', 'xlsm', 'ods']);
    }

    /**
     * Ensure PhpSpreadsheet is available
     */
    protected function ensurePhpSpreadsheet(): void
    {
        if (!class_exists(IOFactory::class)) {
            throw new \RuntimeException(
                'PhpSpreadsheet is required for this reader. Install it with: composer require phpoffice/phpspreadsheet'
            );
        }
    }

    /**
     * Read specific cells (for WithMappedCells)
     */
    public function readCells(string $filePath, array $mapping, ?string $sheetName = null): array
    {
        $this->ensurePhpSpreadsheet();

        $reader = IOFactory::createReaderForFile($filePath);
        $spreadsheet = $reader->load($filePath);

        $worksheet = $sheetName !== null
            ? $spreadsheet->getSheetByName($sheetName)
            : $spreadsheet->getActiveSheet();

        if ($worksheet === null) {
            throw new \RuntimeException("Sheet not found");
        }

        $result = [];
        foreach ($mapping as $name => $cellAddress) {
            $result[$name] = $worksheet->getCell($cellAddress)->getCalculatedValue();
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $result;
    }
}
